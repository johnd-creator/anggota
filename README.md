# SIM-SP (Sistem Informasi Manajemen Serikat Pekerja)

Sistem manajemen keanggotaan serikat pekerja yang komprehensif, dibangun dengan **Laravel 12 + Inertia.js + Vue 3 + Tailwind CSS**.

## Fitur Utama

### Autentikasi & Multi-Role
- **SSO**: Google OAuth & Microsoft Azure AD
- **Roles**: `super_admin`, `admin_pusat`, `admin_unit`, `bendahara`, `bendahara_pusat`, `pengurus`, `anggota`, `reguler`
- Whitelist domain untuk auto-role assignment (lihat `LoginController`)

### Manajemen Keanggotaan
- **CRUD Anggota**: Wizard 3-step dengan validasi lengkap
- **Status**: Aktif, Cuti, Suspended, Resign, Pensiun
- **Onboarding**: Pending members approval workflow
- **Import**: CSV/XLS/XLSX dengan template, preview & error handling
- **Export**: Multi-format dengan filter kustom

### Mutasi & Update Requests
- **Mutasi**: Request mutasi unit dengan approval super_admin
- **Update Requests**: Request perubahan data anggota dengan workflow approval
- **Tracking**: Status history dan notifications

### Kartu Digital
- Layout portrait dengan QR code untuk verifikasi
- Preview real-time
- PDF export (opsional, saat ini disabled karena design mismatch)

### Notification Center
- Tab: All, Mutations, Updates, Onboarding, Security
- Badge unread di navbar
- Mark read/unread, batch actions
- Real-time updates

### Modul Keuangan (Feature Flag)
- **Ledger**: Transaksi keuangan dengan approval workflow
- **Categories**: Kategori pendapatan/pengeluaran
- **Dues**: Pembayaran iuran anggota dengan mass update
- **Export**: CSV export untuk ledger & categories

### Modul Letters (Surat)
- **Workflow**: Draft → Submit → Approve/Revise/Reject → Send → Archive
- **Categories**: Kategori surat dengan approvers assignment
- **Attachments**: Upload dan download file
- **QR Verification**: Public verification page untuk scan QR
- **PDF Export**: Generate PDF dari template

### Aspirasi
- **Member**: Submit aspirasi dengan dukungan (support)
- **Admin**: Management categories, merge aspirasi, update status
- **Voting**: Fitur dukungan anggota terhadap aspirasi

### Announcements (Feature Flag)
- **Create**: Rich text editor dengan attachments
- **Targeting**: Broadcast ke semua role
- **Pin & Toggle**: Pin penting & active toggle
- **Dismiss**: Member bisa dismiss announcement

### Reports & Analytics
- **Growth**: Statistik pertumbuhan keanggotaan
- **Mutations**: Laporan mutasi
- **Members**: Export data anggota terfilter
- **Aspirations**: Rekapitulasi aspirasi
- **Dues**: Laporan pembayaran iuran
- **Finance**: Laporan keuangan (jika module aktif)
- **Public API**: Token-based API untuk integrasi eksternal

### Admin Tools
- **Audit Log**: Log aktivitas pengguna
- **Activity Log**: Log sistem dengan filter
- **Active Sessions**: Monitor & terminate sessions (super_admin)
- **Ops Center**: Backup management & DR runbook
- **Settings**: Profile, password, notification preferences, sessions

### Fitur Lainnya
- **Global Search**: Pencarian cepat anggota & konten
- **Image Optimization**: Dynamic image serving dengan responsive sizes
- **Help Center**: Dokumentasi inline untuk user
- **Feedback System**: Rating & feedback form
- **GDPR**: Export & delete request untuk compliance

## Persiapan & Instalasi

```bash
# Clone repository
git clone https://github.com/your-repo/sim-sp.git
cd sim-sp

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure database (.env)
# DB_CONNECTION=sqlite (default) atau MySQL/PostgreSQL

# Run migrations
php artisan migrate --seed

# Build assets
npm run build

# Run development server
composer dev
# atau manual:
# php artisan serve
# npm run dev
# php artisan queue:work
```

### Composer Scripts (Custom)
```bash
composer setup      # Full setup baru
composer dev        # Run semua services (server, queue, logs, vite)
composer test       # Run tests
```

## Konfigurasi Penting

### Environment Variables
```bash
# Database
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel

# Super Admin (ubah di production)
SUPERADMIN_EMAIL=superadmin@waspro.com
SUPERADMIN_PASSWORD=password123

# Google SSO
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URL=${APP_URL}/auth/google/callback

# Microsoft SSO (opsional)
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URL=${APP_URL}/auth/microsoft/callback

# Redis (opsional, untuk cache & queue)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Feature Flags
Enable/disable modul via environment:
- `FEATURE_FINANCE=true` - Modul keuangan
- `FEATURE_REPORTS=true` - Reports module
- `FEATURE_ANNOUNCEMENTS=true` - Announcements module

### Queue & Scheduler
```bash
# Queue worker untuk notifikasi & jobs
php artisan queue:work

# Cron job untuk scheduler
* * * * * php artisan schedule:run
```

## Navigasi Per Role

### Super Admin
- Master data (units, union positions, roles, letter categories)
- Manage users & sessions
- All members, mutations, onboarding, updates
- Finance (categories, ledgers, dues)
- Letters (create, approve, send)
- Aspirations management
- Reports & analytics
- Audit & activity logs
- Ops Center (backup)
- Announcements management

### Admin Pusat
- Members, mutations, updates
- Letters (create, approve, send)
- Finance (categories, ledgers, dues)
- Aspirations
- Reports (pusat level)
- Announcements

### Admin Unit
- Members (unit scope only)
- Mutations (request & approve internal)
- Import members
- Letters (create for unit)
- Finance (unit scope only)
- Reports (unit level)
- Announcements (unit level)

### Bendahara / Bendahara Pusat
- Finance ledgers & categories
- Dues management
- Member dues view
- Finance reports

### Pengurus
- Letters (create, approve)
- Aspirations (create, view)
- Member portal access
- Notifications

### Anggota
- Profile & digital card
- Request data updates
- Aspirations (submit & support)
- Letters inbox
- Dues (iuran saya)
- Notifications

## Rute Penting

### Autentikasi
- `/login` - Login page
- `/auth/google` - Google OAuth
- `/auth/microsoft` - Microsoft OAuth
- `/logout` - Logout

### Dashboard & Core
- `/dashboard` - Dashboard utama
- `/settings` - Pengaturan profil & notifikasi
- `/notifications` - Notification center
- `/search` - Global search

### Admin
- `/admin/members` - Manajemen anggota
- `/admin/members/import` - Import anggota
- `/admin/units` - Master unit
- `/admin/union-positions` - Master jabatan
- `/admin/roles` - Role management
- `/admin/mutations` - Mutasi requests
- `/admin/onboarding` - Onboarding approvals
- `/admin/updates` - Update requests
- `/admin/sessions` - Active sessions
- `/admin/activity-logs` - Activity logs
- `/admin/aspirations` - Aspirasi management
- `/admin/aspiration-categories` - Kategori aspirasi
- `/admin/letter-categories` - Kategori surat
- `/admin/letter-approvers` - Approver surat
- `/admin/announcements` - Manajemen announcement

### Finance
- `/finance/categories` - Kategori keuangan
- `/finance/ledgers` - Ledger transaksi
- `/finance/dues` - Iuran anggota

### Letters
- `/letters/inbox` - Kotak masuk
- `/letters/outbox` - Kotak keluar
- `/letters/approvals` - Approval queue
- `/letters/create` - Buat surat baru
- `/letters/{id}` - Detail surat
- `/letters/{id}/preview` - Preview dengan QR
- `/letters/{id}/pdf` - Export PDF
- `/letters/verify/{token}` - Public verification (QR scan)

### Member Portal
- `/member/profile` - Profil anggota
- `/member/portal` - Portal self-service
- `/member/aspirations` - Aspirasi saya
- `/member/dues` - Iuran saya
- `/verify-card/{token}` - Verify card QR

### Reports
- `/reports/growth` - Laporan pertumbuhan
- `/reports/mutations` - Laporan mutasi
- `/reports/members` - Export anggota
- `/reports/aspirations` - Laporan aspirasi
- `/reports/dues` - Laporan iuran
- `/reports/finance` - Laporan keuangan
- `/reports/export` - Unified export

### Public API
- `/api/reports/growth` - Growth data (token required)
- `/api/reports/mutations` - Mutations data (token required)
- `/api/members/search` - Member search autocomplete

## Tech Stack

### Backend
- **Laravel 12** - PHP Framework
- **PHP 8.2+** - Runtime
- **SQLite/MySQL/PostgreSQL** - Database
- **Redis** - Cache & Queue (opsional)

### Frontend
- **Inertia.js** - SPA bridge
- **Vue 3** - Frontend framework
- **Vite** - Build tool
- **Tailwind CSS 4.x** - Styling
- **Alpine.js** - Interactive components
- **Heroicons** - Icons
- **TipTap** - Rich text editor

### Key Packages
- `laravel/socialite` - OAuth (Google, Microsoft)
- `inertiajs/inertia-laravel` - Inertia adapter
- `simplesoftwareio/simple-qrcode` - QR Code generation
- `dompdf/dompdf` - PDF generation
- `maatwebsite/excel` - Excel import/export
- `box/spout` - Spreadsheet handling
- `intervention/image-laravel` - Image processing
- `predis/predis` - Redis client

## Testing

```bash
# Run tests
composer test
# atau
php artisan test

# Run Pint (code style)
./vendor/bin/pint

# Type checking (jika ada)
npm run typecheck
```

## Deployment Checklist

- [ ] Set `APP_ENV=production` & `APP_DEBUG=false`
- [ ] Generate strong `APP_KEY`
- [ ] Configure database (MySQL/PostgreSQL recommended)
- [ ] Set secure `SUPERADMIN_EMAIL` & `SUPERADMIN_PASSWORD`
- [ ] Configure OAuth credentials (Google/Microsoft)
- [ ] Configure Redis for cache & queue
- [ ] Set up queue worker (Supervisor recommended)
- [ ] Configure cron job for scheduler
- [] Set up backups (Ops Center)
- [ ] Configure mail settings for notifications
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Build assets: `npm run build`
- [ ] Set up proper file permissions

## Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Queue Not Processing
```bash
# Check queue status
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear cache
php artisan cache:clear
php artisan config:clear
```

### Build Issues
```bash
# Clear node modules & reinstall
rm -rf node_modules package-lock.json
npm install
npm run build
```

## Kontribusi

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## Lisensi

MIT License - lihat file LICENSE untuk detail

## Support & Dokumentasi

- **Help Center**: `/help` (in-app documentation)
- **Security Review**: `/docs/security/review` (super_admin only)
- **Ops Runbook**: `/docs/ops/backup-dr` (super_admin only)
- **Launch Checklist**: `/docs/release/launch-checklist` (super_admin only)

---

**Version**: 1.0.0  
**Last Updated**: 2025-02-19
