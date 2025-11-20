<?php
declare(strict_types=1);

header('Content-Type: application/json');

function respond(int $httpCode, array $body): void
{
    http_response_code($httpCode);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'status'  => 'error',
        'message' => 'Gunakan metode POST untuk endpoint webhook ini.',
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
