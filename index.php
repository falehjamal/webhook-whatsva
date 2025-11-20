<?php
declare(strict_types=1);

function respond(int $httpCode, array $body): void
{
    header('Content-Type: application/json');
    http_response_code($httpCode);
    echo json_encode($body);
    exit;
}

function escape(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST' && $method !== 'GET') {
    header('Allow: GET, POST');
    respond(405, [
        'status'  => 'error',
        'message' => 'Metode tidak didukung. Gunakan GET atau POST.',
    ]);
}

// --- Konfigurasi database ---
$dbHost = 'jalakencana.id';
$dbName = 'jalakenc_whatsva';
$dbUser = 'jalakenc_whatsva';
$dbPass = 'pass_whatsva';
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    respond(500, [
        'status'  => 'error',
        'message' => 'Koneksi database gagal',
        'detail'  => $e->getMessage(),
    ]);
}

/*
 Jalankan sekali di database Anda sebelum menerima webhook:

 CREATE TABLE `whatsapp_webhooks` (
     `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     `remote_jid` VARCHAR(255) NOT NULL,
     `from_me` TINYINT(1) NOT NULL,
     `message_id` VARCHAR(255) NOT NULL,
     `participant` VARCHAR(255) DEFAULT NULL,
     `message_timestamp` BIGINT NOT NULL,
     `push_name` VARCHAR(255) DEFAULT NULL,
     `message_text` TEXT,
     `source` ENUM('personal','group','unknown') NOT NULL DEFAULT 'unknown',
     `raw_payload` JSON NOT NULL,
     `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     KEY `idx_remote_jid` (`remote_jid`),
     KEY `idx_message_timestamp` (`message_timestamp`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

if ($method === 'GET') {
    try {
        $stmt = $pdo->query(
            'SELECT remote_jid,
                    push_name,
                    message_text,
                    message_timestamp
             FROM whatsapp_webhooks
             ORDER BY remote_jid ASC, message_timestamp DESC'
        );
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Gagal mengambil data: ' . $e->getMessage();
        exit;
    }

    $grouped = [];
    foreach ($rows as $row) {
        $remote = $row['remote_jid'] ?? '-';
        if (!isset($grouped[$remote])) {
            $grouped[$remote] = [];
        }
        $grouped[$remote][] = $row;
    }

    header('Content-Type: text/html; charset=utf-8');

    $totalNomor = count($grouped);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Ringkasan Pesan WhatsApp</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 2rem; background: #f7f7f7; }
            table { border-collapse: collapse; width: 100%; background: #fff; }
            th, td { padding: 0.75rem 1rem; border: 1px solid #e0e0e0; vertical-align: top; }
            th { background: #fafafa; text-align: left; }
            caption { text-align: left; font-size: 1.2rem; margin-bottom: 1rem; }
            small { color: #666; }
            tr.remote-header td { background: #f0f6ff; font-weight: bold; }
        </style>
    </head>
    <body>
    <h1>Ringkasan Pesan WhatsApp</h1>
    <p>Total nomor unik: <strong><?php echo $totalNomor; ?></strong></p>
    <table>
        <caption>Pesan per nomor (group by nomor HP)</caption>
        <thead>
        <tr>
            <th>Nomor / remote_jid</th>
            <th>Nama Pengirim</th>
            <th>Isi Pesan</th>
            <th>Waktu</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($totalNomor === 0): ?>
            <tr>
                <td colspan="4">Belum ada data tersimpan.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($grouped as $remoteJid => $messages): ?>
                <?php $rowspan = count($messages); ?>
                <?php foreach ($messages as $index => $message): ?>
                    <?php
                    $timestamp = $message['message_timestamp']
                        ? date('Y-m-d H:i:s', (int) $message['message_timestamp'])
                        : '-';
                    ?>
                    <tr>
                        <?php if ($index === 0): ?>
                            <td rowspan="<?php echo $rowspan; ?>">
                                <?php echo escape($remoteJid); ?>
                            </td>
                        <?php endif; ?>
                        <td><?php echo escape($message['push_name'] ?? '-'); ?></td>
                        <td><?php echo nl2br(escape($message['message_text'] ?? '-')); ?></td>
                        <td><small><?php echo escape($timestamp); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </body>
    </html>
    <?php
    exit;
}

// --- Baca payload JSON dari php://input ---
$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    respond(400, ['status' => 'error', 'message' => 'Body request kosong']);
}

$data = json_decode($rawInput, true);
if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    respond(400, ['status' => 'error', 'message' => 'Payload bukan JSON valid']);
}

// --- Normalisasi data penting ---
$clean = [
    'remote_jid'        => $data['key']['remoteJid'] ?? null,
    'from_me'           => !empty($data['key']['fromMe']) ? 1 : 0,
    'message_id'        => $data['key']['id'] ?? null,
    'participant'       => $data['key']['participant'] ?? ($data['participant'] ?? null),
    'message_timestamp' => $data['messageTimestamp'] ?? null,
    'push_name'         => $data['pushName'] ?? null,
    'message_text'      => $data['message']['conversation']
        ?? $data['pesan']
        ?? ($data['message']['extendedTextMessage']['text'] ?? null),
    'source'            => in_array($data['source'] ?? 'unknown', ['personal', 'group'], true)
        ? $data['source']
        : 'unknown',
];

if (!$clean['remote_jid'] || !$clean['message_id'] || !$clean['message_timestamp']) {
    respond(422, [
        'status'  => 'error',
        'message' => 'Field wajib hilang (remoteJid/id/timestamp)',
    ]);
}

// --- Simpan ke database menggunakan prepared statement ---
$stmt = $pdo->prepare(
    'INSERT INTO whatsapp_webhooks
        (remote_jid, from_me, message_id, participant, message_timestamp, push_name, message_text, source, raw_payload)
     VALUES
        (:remote_jid, :from_me, :message_id, :participant, :message_timestamp, :push_name, :message_text, :source, :raw_payload)'
);

try {
    $stmt->execute([
        ':remote_jid'        => $clean['remote_jid'],
        ':from_me'           => $clean['from_me'],
        ':message_id'        => $clean['message_id'],
        ':participant'       => $clean['participant'],
        ':message_timestamp' => $clean['message_timestamp'],
        ':push_name'         => $clean['push_name'],
        ':message_text'      => $clean['message_text'],
        ':source'            => $clean['source'],
        ':raw_payload'       => $rawInput,
    ]);
} catch (PDOException $e) {
    respond(500, [
        'status'  => 'error',
        'message' => 'Gagal menyimpan data',
        'detail'  => $e->getMessage(),
    ]);
}

respond(201, [
    'status'  => 'success',
    'message' => 'Payload webhook tersimpan',
    'data'    => [
        'remote_jid' => $clean['remote_jid'],
        'message_id' => $clean['message_id'],
        'source'     => $clean['source'],
    ],
]);
