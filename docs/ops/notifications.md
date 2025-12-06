# Notifikasi & SLA Reminder

## Channels
- In-app: disimpan di tabel notifications
- Email: mailable antrian, subject `SIM-SP Notification`
- Webhook/WA: job stub log ke `services.notifications.webhook_endpoint`

## Templates
- Mutasi diajukan, disetujui/ditolak
- Onboarding approve/reject
- Update data approve/reject
- Backup gagal

## SLA
- Mutasi pending >3 hari: status `warning`
- Mutasi pending >5 hari: status `breach`, eskalasi
- Ringkasan harian ke Super Admin/Admin Unit

## Preferensi
- Tabel `notification_preferences` menyimpan channel per event dan opsi digest
- UI di Settings: toggle channel dan digest, timestamp terakhir diubah

## Scheduler
- Harian 08:00: reminder mutasi dan penandaan SLA
- Harian 09:00: reminder onboarding dan update requests
- Harian 09:30: reminder update data (eskalasi jika diperlukan)
- Harian 18:00: digest harian ke pengguna yang mengaktifkan opsi

## Webhook
- Konfigurasi melalui `.env`:
  - `WEBHOOK_URL=https://example.com/webhook`
  - `WEBHOOK_TOKEN=secret-token`
- Job `SendWebhookNotification` membaca nilai dari `config/services.php` dan mencatat respons/galat.
- Kegagalan job akan dicatat di `activity_logs` melalui handler queue failing.
