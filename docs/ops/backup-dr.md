# Backup & Disaster Recovery Runbook

## Tujuan
- Menjamin ketersediaan data melalui backup terjadwal dan prosedur restore.

## Lokasi Backup
- `storage/app/backups/` atau S3 bucket (konfigurasi via `.env`).

## Jadwal
- Harian pukul 02:00, retensi 7 hari, cleanup otomatis.

## Prosedur Backup Manual
1. Jalankan `php artisan backup:database`.
2. Verifikasi file terbuat di lokasi backup.

## Prosedur Restore
1. Pastikan layanan aplikasi dihentikan.
2. Unggah file backup ke server DB.
3. Jalankan restore sesuai engine (PostgreSQL/MySQL).
4. Validasi integritas dan jalankan aplikasi.

## RPO/RTO
- RPO: ≤ 24 jam.
- RTO: ≤ 2 jam.

## Alert & Monitoring
- Alert jika backup gagal (log + notifikasi admin).
- Metrik: jumlah backup terbaru, usia backup terakhir.

