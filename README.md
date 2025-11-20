# Aktivasi Webhook Whatsva

Panduan singkat untuk menghubungkan Whatsva ke endpoint webhook (contoh `https://faleh.id/webhook-whatsva/`) agar setiap pesan WhatsApp otomatis diteruskan ke server Anda.

## Langkah-Langkah
1. Buka `https://whatsva.id/` dan login.
2. Pastikan perangkat WhatsApp Anda berstatus **Connect**.
3. Masuk ke menu **Webhook**.
4. Klik **Add** lalu isi:
   - **Device**: pilih perangkat yang ingin menerima webhook.
   - **Keyword**: isi `*` supaya semua pesan diteruskan.
   - **URL**: `https://faleh.id/webhook-whatsva/` (atau URL endpoint Anda sendiri).
5. Klik **Save**.

Sesaat setelah disimpan, setiap pesan yang masuk ke perangkat tersebut akan dikirim ke URL webhook Anda dan bisa langsung disimpan ke database.

