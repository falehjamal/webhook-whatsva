# Panduan Integrasi Webhook WhatsVA ke Database

Panduan ini menjelaskan cara menghubungkan WhatsVA dengan endpoint webhook PHP (`index.php`) sehingga setiap pesan WhatsApp yang diterima perangkat Anda akan otomatis tersimpan ke database MySQL.

## 1. Prasyarat
- Perangkat WhatsApp sudah terdaftar di [https://whatsva.id/](https://whatsva.id/) dan dalam kondisi **Connected**.
- Web server dengan PHP 8+ dan ekstensi PDO MySQL (contoh: `https://faleh.id/webhook-whatsva/`).
- Akses ke database MySQL dan kemampuan menjalankan perintah SQL.

## 2. Menyiapkan Database
Jalankan sekali perintah berikut di database target Anda:

```
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
```

## 3. Konfigurasi File `index.php`
- Simpan file `index.php` di server Anda (contoh folder `public_html/webhook-whatsva/`).
- Sesuaikan konfigurasi PDO pada bagian:
  ```php
  $dbHost = 'jalakencana.id';
  $dbName = 'jalakenc_whatsva';
  $dbUser = 'jalakenc_whatsva';
  $dbPass = 'pass_whatsva';
  ```
  ganti dengan kredensial milik Anda.
- Endpoint ini mendukung:
  - `POST /index.php` untuk menerima payload webhook dan menyimpan ke tabel.
  - `GET /index.php` untuk menampilkan tabel HTML berisi seluruh pesan yang digrup berdasarkan nomor.

## 4. Membuat Webhook di WhatsVA
1. Masuk ke [https://whatsva.id/](https://whatsva.id/) menggunakan akun Anda.
2. Pastikan perangkat WhatsApp berada pada status **Connected**.
3. Klik menu **Webhook**.
4. Tekan tombol **Add** kemudian isi:
   - **Device**: pilih perangkat yang ingin menerima webhook.
   - **Keyword**: masukkan `*` agar semua pesan dilewatkan ke webhook.
   - **URL**: `https://faleh.id/webhook-whatsva/` (atau domain Anda sendiri).
5. Simpan perubahan. Setiap pesan baru kini akan dikirim ke endpoint tersebut dan otomatis tersimpan ke database.

## 5. Menguji Webhook
Gunakan file `postman.rest` atau alat lain seperti Postman/cURL:
```
POST https://faleh.id/webhook-whatsva/
Content-Type: application/json

{ ...payload WhatsVA... }
```
Contoh payload personal dan grup terdapat di `postman.rest`. Setelah request berhasil (`201 success`), data baru bisa dilihat melalui:
- `GET https://faleh.id/webhook-whatsva/` → tabel ringkasan.
- Query langsung ke tabel `whatsapp_webhooks`.

## 6. Monitoring & Troubleshooting
- Cek log server atau respon JSON jika WhatsVA menampilkan error saat mengirim.
- Jika tabel kosong, pastikan database kredensial benar dan device WhatsVA masih terhubung.
- Gunakan `GET` view untuk memastikan pesan tersimpan sesuai nomor dan urutan waktu.

Dengan langkah di atas, integrasi WhatsVA webhook ke database Anda siap digunakan dan setiap pesan yang masuk akan tercatat secara otomatis.

