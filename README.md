# SIM-SP (Sistem Informasi Manajemen Serikat Pekerja)

Laravel 11 + Inertia.js + Vue 3 + Tailwind untuk pengelolaan anggota serikat: onboarding, mutasi, kartu digital, notifikasi, dan admin tools. Disiapkan untuk peran pusat (super admin), unit, anggota, serta rencana bendahara unit.

## Fitur Utama
- **Autentikasi & Role**: Google SSO, login form biasa; role `super_admin`, `admin_unit`, `anggota`, `reguler`, dan rencana `bendahara` (akses keuangan unit + menu anggota). Contoh whitelist domain ada di `LoginController`.
- **Keanggotaan**: CRUD anggota (wizard 3 step), status aktif/cuti/suspended/resign/pensiun, filter unit (admin_unit hanya melihat unitnya). Onboarding reguler (pending_members) untuk pengguna baru yang belum jadi anggota.
- **Import Anggota**: Upload CSV/XLS/XLSX dengan template bawaan; ringkasan sukses/gagal ditampilkan ke admin_unit.
- **Mutasi & Update Request**: Permintaan mutasi (admin_unit → super_admin) dan permintaan pembaruan data anggota dengan approval.
- **Kartu Digital**: Layout portrait, QR untuk verifikasi, unduh PDF; halaman portal anggota menampilkan kartu digital.
- **Notification Center**: In-app notification dengan tab (All/Mutations/Updates/Onboarding/Security), badge unread di navbar, mark read/unread, dropdown recent.
- **Dashboard & Counters**: Total unit, total anggota, mutasi pending, onboarding pending, update pending, anggota unit saya (admin_unit).
- **Admin Tools**: Audit Log, Activity Log, Active Sessions (super_admin - monitor & terminate sessions), Ops Center (super_admin).
- **Reports**: Halaman laporan Growth, Mutations, Documents dengan filter dasar.

## Persiapan & Instalasi
```bash
git clone https://github.com/your-repo/sim-sp.git
cd sim-sp
composer install
npm install
cp .env.example .env
php artisan key:generate
# set DB_*, GOOGLE_CLIENT_ID/SECRET/REDIRECT, APP_URL
php artisan migrate
# (opsional) php artisan db:seed
npm run dev          # atau npm run build
php artisan serve
```

## Konfigurasi Penting
- **Google SSO**: set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL=${APP_URL}/auth/google/callback`.
- **Role Mapping**: contoh whitelist domain ada di `app/Http/Controllers/Auth/LoginController.php`.
- **Queue & Scheduler**: jalankan worker jika memakai notifikasi async (`php artisan queue:work`); aktifkan scheduler via cron `* * * * * php artisan schedule:run`.

## Navigasi Utama
- **Super Admin**: Master data unit & jabatan serikat, anggota, mutasi, onboarding, update requests, laporan, notifikasi, audit/activity log, active sessions, Ops Center.
- **Admin Unit**: Anggota (unit sendiri), mutasi anggota, onboarding reguler, update requests, import anggota, laporan unit, notifikasi, dashboard ringkas.
- **Anggota**: Profil, Kartu Digital, Notifikasi; portal self-service untuk update terbatas.
- **Bendahara (rencana)**: Modul keuangan unit (ledger & kategori), plus menu anggota; scope hanya unit sendiri.

## Rute Penting (contoh)
- Auth: `/auth/google`, `/auth/google/callback`, `/login`
- Dashboard: `/dashboard`
- Anggota: `/admin/members`, `/admin/members/create`, `/admin/members/{id}/edit`
- Mutasi: `/admin/mutations`
- Onboarding: `/admin/onboarding`
- Update Requests: `/admin/updates`
- Notifications: `/notifications`
- Portal Anggota: `/member/profile`, `/member/portal`
- Reports: `/reports/growth`, `/reports/mutations`, `/reports/documents`
- Tools: `/admin/activity-logs`, `/admin/sessions`, `/ops` (super_admin)

## Tech Stack
- Laravel 11, PHP 8.2+
- Inertia.js, Vue 3, Vite
- Tailwind CSS
- SQLite/MySQL (tergantung .env)

## Lisensi
Sesuaikan dengan kebutuhan proyek (isi di LICENSE jika diperlukan).
