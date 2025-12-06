# Launch Checklist

## Pre-Launch
- Konfigurasi `.env` (API_TOKEN, S3, LOG_STACK=structured).
- Jalankan migrasi dan verifikasi health `/health`.
- Pastikan queue worker berjalan.

## Release
- Build frontend: `npm run build`.
- Deploy dan cek `/metrics` + dashboard.

## Post-Launch Monitoring
- Periksa error logs (JSON), request ID korelasi.
- Cek backup harian dan alert.

