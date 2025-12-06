# SIM-SP (Sistem Informasi Manajemen Serikat Pekerja)

## Setup### Installation

1.  **Clone the repository**
    ```bash
    git clone https://github.com/your-repo/sim-sp.git
    cd sim-sp
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Setup**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    - Reguler

    And initial users:
    - `superadmin@example.com` (Super Admin)
    - `adminunit@example.com` (Admin Unit)

4.  **Run Application**
    ```bash
    npm run dev
    php artisan serve
    ```

## Features

- **Google SSO**: Login with Google.
- **Role-Based Access Control**:
    - **Super Admin / Admin Unit**: Access to Dashboard and Audit Logs.
    - **Reguler**: Restricted to "It Works" page.
- **Relasi User ↔ Member**:
    - Tabel `users` memiliki `member_id` (nullable) ke `members`.
    - Tabel `members` memiliki `user_id` (nullable) ke `users`.
    - Helper `User::assignMember(Member $member)` akan menghubungkan keduanya dan men-set role menjadi `anggota` bila user sebelumnya `reguler`.
    - Perintah sinkronisasi: `php artisan link:users-members` mencocokkan user-member berdasarkan email.
- **Audit Logging**: Tracks login events (Success/Failure, IP, User Agent).
- **Domain Whitelist**:
    - `superadmin.com` -> Super Admin
    - `adminunit.com` -> Admin Unit
    - Others -> Reguler
    (Configured in `app/Http/Controllers/Auth/LoginController.php`)

## Tech Stack
- Laravel 11
- Inertia.js
- Vue 3
- Tailwind CSS

## Notifications & Scheduler

- Configure webhook integration via `.env`:
  - `WEBHOOK_URL` and `WEBHOOK_TOKEN`
- Run queue worker to process mails/webhooks:
  - `php artisan queue:work`
- Enable Laravel scheduler via cron:
  - `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- Scheduled commands (configured in `bootstrap/app.php`):

## Reports & Export

- Reports UI: `/reports/growth`, `/reports/mutations`, `/reports/documents` dengan filter tanggal/unit/status.
- Export CSV dari halaman laporan: kirim `POST /reports/{type}/export` dengan body filter aktif.
- API integrasi (JSON) untuk HRIS:
  - Endpoint: `GET /api/reports/growth`, `GET /api/reports/mutations`, `GET /api/reports/documents`
  - Auth: header `X-API-Token: <token>`; set `APP_API_TOKEN` di `.env`.
  - Contoh: `curl -H "X-API-Token: $APP_API_TOKEN" https://your-app/api/reports/growth`
  - `sla:remind-mutations` daily at 08:00
  - `sla:remind-onboarding` daily at 09:00
  - `sla:remind-updates` daily at 09:30
  - `notifications:digest` daily at 18:00
- Field Anggota baru:
  - `members.kta_number`, `members.nip`, `members.union_position_id` (selain `email` yang sudah ada)
  - `members` belongsTo `union_positions` via `union_position_id`; relasi tersedia sebagai `member.unionPosition`
  - Form Admin Anggota menggunakan dropdown Jabatan Serikat (master data), dan export menyertakan KTA/NIP/Email/Jabatan.

## Master Data: Jabatan Serikat

- Tabel `union_positions` (id, name, code, description, timestamps)
- CRUD Admin: `/admin/union-positions` (akses Super Admin)
- Seeder default: `Ketua`, `Sekretaris`, `Bendahara`, `Anggota`
