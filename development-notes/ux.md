# UX notes (local-only)

> This file is local-only (ignored by git). Do not put secrets here.

## Log

- Date: 2025-12-22 16:00
  - Scope: ux
  - Summary: No UX changes yet
  - Files: none
  - Commands: none
  - Decisions/Risks: none
  - Next: Identify key flows to validate locally (login, member list/detail, forms)

- Date: 2025-12-23 23:30
- Scope: ux
- Summary:
  - Updated UI to treat `admin_unit`/`bendahara` as “anggota” for member portal access (Profile + KTA Digital links visible).
  - Adjusted Dashboard UX for member-facing quick links and role-based cards.
- Files:
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Dashboard.vue
- Commands: none
- Decisions/Risks:
  - UX now assumes role hierarchy where `admin_unit`/`bendahara` still need member portal entry points; backend access remains enforced by policies/middleware.
- Next:
  - Validate dropdown + Dashboard with 3 roles (`anggota`, `admin_unit`, `bendahara`) to ensure links/routes match permissions.

- Date: 2025-12-24 14:30
- Scope: ux
- Summary:
  - Added admin UI for Letter Approvers and extended Letter Categories form with template/default fields.
  - Added SLA overdue/filter UX in approvals, apply-template in letter form, and `letters` notification toggle in settings.
- Files:
  - resources/js/Pages/Admin/LetterApprovers/Index.vue
  - resources/js/Pages/Admin/LetterApprovers/Form.vue
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Admin/LetterCategories/Index.vue
  - resources/js/Pages/Admin/LetterCategories/Form.vue
  - resources/js/Pages/Letters/Approvals.vue
  - resources/js/Pages/Letters/Form.vue
  - resources/js/Pages/Settings/Index.vue
- Commands: see `development-notes/testing.md`
- Decisions/Risks:
  - UI lists supported template placeholders; keep it in sync with `LetterTemplateRenderer` placeholders.
- Next:
  - Manual UX pass for `/admin/letter-approvers` CRUD and `/letters/approvals` SLA filter behavior.

- Date: 2025-12-24 18:10
- Scope: ux
- Summary:
  - Point D: Added import wizard page and linked it from sidebar and member list to replace the modal upload flow.
- Files:
  - resources/js/Pages/Admin/Members/Import.vue
  - resources/js/Pages/Admin/Members/Index.vue
  - resources/js/Layouts/AppLayout.vue
- Commands: none
- Decisions/Risks:
  - Member list now routes operators to the import wizard for preview/commit visibility.
- Next: none

- Date: 2026-01-07 20:37
- Scope: ux
- Summary:
  - AlertBanner now supports auto-dismiss and self-dismiss without parent state updates.
  - Settings security feedback and member import errors use consistent UI alerts.
- Files:
  - resources/js/Components/UI/AlertBanner.vue
  - resources/js/Pages/Settings/Index.vue
  - resources/js/Pages/Admin/Members/Import.vue
- Commands: none
- Decisions/Risks:
  - Success alerts auto-dismiss after 4s when dismissible; other types remain manual.
- Next: none

- Date: 2026-01-07 19:57
- Scope: ux
- Summary:
  - Standardized Member Aspirations buttons to UI component styles (primary CTA, secondary link, icon button).
- Files:
  - resources/js/Pages/Member/Aspirations/Index.vue
- Commands: none
- Decisions/Risks:
  - IconButton uses custom background classes to preserve support-state coloring.
- Next: none

- Date: 2026-01-07 19:21
- Scope: ux
- Summary:
  - Added safer word-wrapping for letter body in preview and PDF to prevent text overflow.
- Files:
  - resources/css/app.css
  - resources/views/letters/pdf.blade.php
- Commands: none
- Decisions/Risks:
  - Word-breaking may split very long tokens; helps keep content inside page width.
- Next: none

- Date: 2026-01-07 18:52
- Scope: ux
- Summary:
  - Removed "Opsional" wording from secondary signer label and external address helper text.
- Files:
  - resources/js/Pages/Letters/Form.vue
- Commands: none
- Decisions/Risks:
  - External address remains optional without explicit label.
- Next: none

- Date: 2026-01-07 10:23
- Scope: ux
- Summary:
  - Fixed issue where UI changes (including Rich Text Editor) didn't appear due to service worker caching stale `/build/manifest.json`.
  - Service worker now treats `/build/manifest.json` as network-first and bumps cache version to force refresh.
- Files:
  - public/service-worker.js
- Commands: none
- Decisions/Risks:
  - Build manifest is no longer precached; deployments/builds should reflect immediately without manual cache clearing.
- Next: none

- Date: 2026-01-07 10:03
- Scope: ux
- Summary:
  - Optimized Vite build output by enabling lazy-loaded Inertia pages (route-level code splitting).
  - Reduced initial JS payload; pages now load as separate chunks on-demand.
- Files:
  - resources/js/app.js
- Commands:
  - npm run build
- Decisions/Risks:
  - Build now produces many page-level chunks; ensure server caches `public/build/assets/*` correctly in production.
- Next: none

- Date: 2026-01-05 06:18
- Scope: ux
- Summary:
  - Menambahkan menu `Reports` di sidebar untuk role `bendahara` (dan admin) dengan link ke `Export CSV` docs.
  - Active state/expand section Reports juga mengenali path `/docs/reports/*`.
- Files:
  - resources/js/Layouts/AppLayout.vue
- Commands: none
- Decisions/Risks:
  - Di mobile sidebar, item Reports diarahkan ke docs CSV (bukan ke halaman growth/mutations).
- Next: none

- Date: 2026-01-05 06:34
- Scope: ux
- Summary:
  - Memindahkan akses dokumentasi Export CSV ke Help Center (menu `/help`), menambah link baru “Reports → Panduan Export CSV”.
  - Sidebar Reports dikembalikan fokus ke halaman reports lama (`/reports/*`) untuk admin saja (dokumen tidak lagi ditaruh di menu Reports).
- Files:
  - resources/js/Pages/Help/Index.vue
  - resources/js/Layouts/AppLayout.vue
- Commands: none
- Decisions/Risks:
  - Untuk role `bendahara`, dokumen export CSV sekarang diakses via Help Center, bukan sidebar Reports.
- Next: none

- Date: 2026-01-04 21:05
- Scope: ux
- Summary:
  - Added close (dismiss) button on pinned announcement cards in dashboard.
  - Closing a card hides it for the current user only (persists across refresh).
- Files:
  - resources/js/Pages/Dashboard.vue
- Commands: none
- Decisions/Risks:
  - Close action triggers an Inertia POST and re-renders dashboard with updated props.
- Next: none

- Date: 2026-01-04 20:50
- Scope: ux
- Summary:
  - Improved `letters/{id}` detail page actions: added explicit `Preview` button and upgraded `Kembali` to a consistent button style.
  - Back behavior now prefers browser history; falls back to approvals/outbox/inbox based on context.
- Files:
  - resources/js/Pages/Letters/Show.vue
- Commands: none
- Decisions/Risks:
  - Console error “Could not establish connection…” is commonly caused by browser extensions; no `chrome.runtime` usage found in app code.
- Next: none

- Date: 2026-01-04 20:39
- Scope: ux
- Summary:
  - Search dropdown now routes Pengumuman items to edit page only when the user can update that announcement; otherwise opens read-only page.
- Files:
  - app/Services/SearchService.php
- Commands: none
- Decisions/Risks: none
- Next: none

- Date: 2025-12-31 15:54
- Scope: ux
- Summary:
  - Fixed Announcements create/edit submit button so clicking “Buat Pengumuman / Simpan Perubahan” actually submits (PrimaryButton defaults to `type="button"`).
- Files:
  - resources/js/Pages/Admin/Announcements/Form.vue
- Commands:
  - npm run build
- Decisions/Risks:
  - Always set `type="submit"` explicitly when using `PrimaryButton` inside forms to avoid silent no-op.
- Next:
  - Manual browser test: create + edit announcement (super_admin & admin_unit) and confirm flash success appears on `/admin/announcements`.

- Date: 2025-12-31 15:30
- Scope: ux
- Summary:
  - Added safe fallback so the create button still renders if `can` props are missing (route is already role-guarded).
- Files:
  - resources/js/Pages/Admin/Announcements/Index.vue
- Commands: none
- Decisions/Risks:
  - UI uses `can?.create !== false` to avoid hiding actions due to missing props while still respecting an explicit deny.
- Next: none

- Date: 2025-12-31 15:40
- Scope: ux
- Summary:
  - Matched Announcements “Tambah Pengumuman” header/button layout and styling to `/letters/outbox` for consistency.
  - Improved create form feedback by showing a generic error banner and surfacing unit-scope validation when unit selector is hidden.
- Files:
  - resources/js/Pages/Admin/Announcements/Index.vue
  - resources/js/Pages/Admin/Announcements/Form.vue
- Commands:
  - npm run build
- Decisions/Risks:
  - Form now communicates validation failures explicitly to avoid “no response” confusion.
- Next: none

- Date: 2025-12-31 15:28
- Scope: ux
- Summary:
  - Fixed Announcements admin page header/action placement by using `AppLayout`’s `page-title` + `actions` slot (previously used non-existent `header` slot).
  - Ensured “Tambah Pengumuman” button renders for authorized users on `/admin/announcements` (desktop topbar + mobile actions area).
- Files:
  - resources/js/Pages/Admin/Announcements/Index.vue
  - resources/js/Pages/Admin/Announcements/Form.vue
- Commands: none
- Decisions/Risks:
  - Use `AppLayout` contract (`page-title`, `actions`) consistently across admin pages.
- Next: none

- Date: 2025-12-30 20:30
- Scope: ux
- Summary:
  - Forum Integration: Created `AnnouncementCard.vue` component for displaying forum announcements on dashboard.
  - Added "Forum" menu item to desktop and mobile sidebar navigation in `AppLayout.vue`.
  - Integrated `AnnouncementCard` into `Dashboard.vue` with role-based filtering.
  - Forum uses Livewire/Blade while rest of app uses Inertia/Vue (hybrid approach).
- Files:
  - resources/js/Components/UI/AnnouncementCard.vue (NEW)
  - resources/js/Pages/Dashboard.vue
  - resources/js/Layouts/AppLayout.vue
- Commands: none
- Decisions/Risks:
  - Forum link uses `<a href>` instead of Inertia `<Link>` because forum is Blade-based (full page reload).
  - Announcement card shows category badges with color from forum category settings.
- Next:
  - Verify forum styling matches app design system after Livewire is installed.

- Date: 2025-12-30 21:10
- Scope: ux
- Summary:
  - Phase 8: Forum UX Integration completed.
  - Created `app-integrated` Blade layout to mirror main SPA layout (sidebar/header).
  - Updated Forum CSS to use Brand Colors.
  - Restyled Forum Components (Cards, Posts, Quick Reply) to match App Design System.
- Files:
  - resources/forum/livewire-tailwind/views/layouts/app-integrated.blade.php (NEW)
  - resources/forum/livewire-tailwind/views/layouts/main.blade.php
  - resources/forum/livewire-tailwind/css/forum.css
  - resources/forum/livewire-tailwind/views/components/category/card.blade.php
  - resources/forum/livewire-tailwind/views/components/post/card.blade.php
  - resources/forum/livewire-tailwind/views/pages/category/index.blade.php
  - resources/forum/livewire-tailwind/views/pages/thread/show.blade.php
- Decisions/Risks:
  - Forum operates as a separate Blade-based "app" within the main app shell, sharing navigation visual structure but requiring page reloads to switch context.
  - Dark mode support is partial (classes present but no toggle in integrated layout).
- Next: none
- Date: 2025-12-31 05:18
- Scope: ux
- Summary:
  - Forum UX cleanup: removed duplicate navigation and sticky headers from category index page.
  - Added "Kategori" navigation link to layout (desktop + mobile) with active state highlighting.
  - Moved search bar from sticky header to non-sticky content area below page title.
  - Fixed loading overlay icon sizing typo (w-16 w-16 → w-16 h-16).
- Files:
  - resources/forum/livewire-tailwind/views/layouts/app-integrated.blade.php
  - resources/forum/livewire-tailwind/views/pages/category/index.blade.php
  - resources/forum/livewire-tailwind/views/components/loading-overlay.blade.php
- Commands: none
- Decisions/Risks:
  - UX decision: Navigation tabs (Kategori/Recent/Unread) only exist in layout header, not in individual pages.
  - Removed nested max-w-7xl containers to prevent layout narrowing issues.
  - Search functionality preserved with wire:model.live.debounce.300ms.
- Next: Manual verification of all forum routes (/forum, /forum/recent, /forum/unread, category/thread detail pages).

- Date: 2025-12-31 05:48
- Scope: ux
- Summary:
  - CORRECTION: Forum UX cleanup applied to correct layout file (main.blade.php, not app-integrated.blade.php).
  - Added "Kategori" navigation link to desktop and mobile headers in main.blade.php.
  - Added "KTA Digital" link to profile dropdown (Profil Saya, KTA Digital, Logout).
  - Removed unused app-integrated.blade.php file.
  - Stats grid already correctly configured (grid-cols-2 sm -> grid-cols-4 lg).
- Files:
  - resources/forum/livewire-tailwind/views/layouts/main.blade.php (CORRECTED - this is the actual forum layout)
  - resources/forum/livewire-tailwind/views/layouts/app-integrated.blade.php (DELETED - was not used)
  - resources/forum/livewire-tailwind/views/pages/category/index.blade.php (already clean from previous work)
- Commands: none
- Decisions/Risks:
  - Forum Livewire components hardcode layout('forum::layouts.main'), so main.blade.php is the correct file to edit.
  - app-integrated.blade.php was created but never used by forum components.
  - Navigation architecture: Kategori/Recent/Unread tabs only in layout header.
  - Profile dropdown now includes KTA Digital link (/member/portal).
- Next: Manual verification of /forum routes to confirm layout changes work correctly.

- Date: 2025-12-31 10:55
- Scope: ux
- Summary:
  - Forum UX Rollback: Removed all Forum-related UI elements to restore clean state.
  - Removed "Forum" sidebar links (desktop + mobile) and top-bar integration.
  - Removed `AnnouncementCard` from Dashboard and associated logic.
- Files:
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Dashboard.vue
  - resources/js/Components/UI/AnnouncementCard.vue (DELETED)
- Commands: none
- Decisions/Risks:
  - Dashboard restored to standard widget layout.
  - AppLayout navigation cleaned of forum references.
- Next: none


- Date: 2025-12-31 13:45
- Scope: admin ui
- Summary:
  - Created Inertia pages for Announcements:
    - `Index.vue`: List with filters (Scope, Status, Pinned) and actions.
    - `Form.vue`: Dynamic scope selection based on role permissions.
  - Added "Pengumuman" to Sidebar (Desktop & Mobile) in `AppLayout.vue`.
- Files:
  - resources/js/Pages/Admin/Announcements/Index.vue (NEW)
  - resources/js/Pages/Admin/Announcements/Form.vue (NEW)
  - resources/js/Layouts/AppLayout.vue

- Date: 2025-12-31 14:05
- Scope: attachments ui
- Summary:
  - Updated `Admin/Announcements/Form.vue`:
    - Edit mode: Shows attachment list + Download/Delete actions.
    - Edit mode: Shows file input for multiple uploads (with loading state).
    - Create mode: Shows tip to save first.
  - Added `download_url` accessor to `AnnouncementAttachment` model for easy frontend linking.

- Date: 2025-12-31 14:15
- Scope: dashboard widget & public page
- Summary:
  - Dashboard: Added `Pengumuman Penting` widget (top of page).
  - Public Page: Created `Announcements/Index.vue` (cards, search, pagination).
  - Navigation: Added 'Pengumuman' link for all authenticated users in Sidebar.
- Files:
  - resources/js/Pages/Dashboard.vue
  - resources/js/Pages/Announcements/Index.vue (NEW)
  - resources/js/Layouts/AppLayout.vue

- Date: 2025-12-31 15:19
- Scope: ux
- Summary:
  - Improved “Tambah Pengumuman” button UX on `/admin/announcements` (responsive layout, icon, consistent styling).
  - Fixed pagination previous label text.
- Files:
  - resources/js/Pages/Admin/Announcements/Index.vue
- Commands: none
- Decisions/Risks:
  - Create button now respects `can.create` so it won’t show for unauthorized roles.
- Next: none

- Date: 2025-12-31 17:00
- Scope: ux
- Summary:
  - Fixed announcement display width imbalance on Dashboard by restructuring container hierarchy (moved max-w-7xl inside outer wrapper).
  - Improved announcement form UX: added descriptive placeholders to title/body inputs, made labels bold (font-semibold), added helper text for better user guidance.
- Files:
  - resources/js/Pages/Dashboard.vue
  - resources/js/Pages/Admin/Announcements/Form.vueGAP-FIX PROMPT (menutup gap Prompt 1–3: iuran generator + iuran saya + hardening)

KONTEKS
- Repo: Laravel + Inertia + Vue.
- Fitur iuran sudah ada:
  - `/finance/dues` list + filter + update + mass update (bendahara/admin_unit).
  - `dues_payments` (unique member_id+period).
  - `config/dues.php`, command `dues:generate`, page `Member/Dues.vue`, route `/member/dues`, dashboard prop `my_dues`, policy `DuesPaymentPolicy`, audit event `dues.*`.
- Target: rapikan gap vs rencana Prompt 1–3 agar behavior sesuai kebutuhan operasional.

ATURAN MAIN (WAJIB)
1) Baca `AGENTS.md`, lalu baca entry terakhir:
   - `development-notes/backend.md`
   - `development-notes/ux.md`
   - `development-notes/security.md`
   - `development-notes/testing.md`
2) Setelah selesai, append catatan ke notes yang relevan (tanpa secrets/PII).
3) Perubahan harus minimal, fokus gap-fix iuran.

TUJUAN GAP-FIX (RINGKAS)
A) Prompt 1 gap-fix (generator)
- Schedule `dues:generate` harus memakai konfigurasi (`DUES_GENERATE_ON_DAY`) bukan hardcode.
- Generator dan “tunggakan” tidak boleh menghitung bulan sebelum anggota join (join_date-based) bila field tersedia.
- (Optional tapi disarankan) audit event untuk generator (`dues.generate`) agar ada jejak.

B) Prompt 2 gap-fix (Iuran Saya + dashboard)
- Dashboard “Iuran Saya” tampilkan daftar 3 bulan tunggakan (chips) menggunakan `my_dues.unpaid_periods`.
- Nominal iuran di UI tidak hardcode 30000; ambil dari backend/config (`config('dues.default_amount')`).
- Pastikan amount yang tampil konsisten: jika row unpaid amount null, tampilkan default_amount.
- Route `/member/dues` tidak terlalu sempit (kalau ada role lain); prefer `auth` + empty-state jika tak punya member_id (tetap guard `feature:finance`).

C) Prompt 3 gap-fix (hardening + feature flag)
- Tegaskan siapa yang boleh “mark paid”:
  - Default: hanya `bendahara` + global (super_admin/admin_pusat) boleh update iuran.
  - `admin_unit` boleh lihat rekap/list tapi tidak mengubah status (kecuali kamu memang ingin admin_unit bisa).
- Terapkan `feature:finance` juga ke group `/finance/*` (agar disable global benar-benar menutup akses).
- Konsistensi authorize:
  - `MemberDuesController` tidak perlu menerima member_id dari request (sudah), tapi pastikan query range join_date tidak salah.

LANGKAH EKSEKUSI DETAIL

1) Prompt 1 GAP-FIX — Schedule pakai config + join_date-aware generator
1.1 Update schedule (bootstrap)
- File: `bootstrap/app.php`
- Ubah schedule:
  - Dari: `->monthlyOn(1, '00:10')`
  - Ke: `->monthlyOn((int) config('dues.generate_on_day', 1), '00:10')`
- Pastikan config terbaca di konteks schedule closure.

1.2 Join-date aware (generator)
- File: `app/Console/Commands/GenerateMonthlyDues.php`
- Cek apakah `members` memiliki field `join_date` (atau alternatif `created_at`):
  - Jika ada `join_date`:
    - Saat generate untuk period P, EXCLUDE anggota yang join_date > endOfMonth(P).
    - Artinya anggota baru tidak dibuatkan row untuk bulan sebelum join.
  - Jika tidak ada `join_date`, gunakan `created_at` sebagai fallback (lebih baik daripada none).
- Implementasi:
  - Hitung `$periodEnd = Carbon::createFromFormat('Y-m', $period)->endOfMonth();`
  - Query members aktif dengan tambahan `whereDate('join_date', '<=', $periodEnd)` (atau created_at fallback).
- Pastikan tetap idempotent dan tidak overwrite row paid.

1.3 (Optional) Audit generator
- Jika ada `AuditService` yang sudah dipakai untuk `dues.mark_*`, tambahkan audit log di command:
  - Event: `dues.generate`
  - Payload: period(s), created_count, skipped_count, dry_run, backfill_start (jika ada)
  - subject: null, unit: null
- Pastikan tidak memerlukan user_id (command dijalankan scheduler). Kalau schema audit butuh user_id, gunakan null/0 sesuai implementasi existing.

1.4 Update/adjust tests bila perlu
- File: `tests/Feature/DuesGenerateCommandTest.php`
- Jika join_date filter diaktifkan:
  - Tambah 1 test: member join_date setelah period end → tidak dibuat record.
- Pastikan semua test lama tetap pass.

2) Prompt 2 GAP-FIX — “Iuran Saya” nominal dari config + tunggakan chips di dashboard + role coverage
2.1 Backend: supply default amount ke Inertia props
- File: `app/Http/Controllers/Member/MemberDuesController.php`
  - Tambahkan prop `default_amount` ke response:
    - `default_amount` => (int) config('dues.default_amount', 30000)
  - Saat mapping record:
    - Jika `$p->amount` null (terutama unpaid), gunakan default_amount untuk display supaya tidak Rp0.
- File: `app/Http/Controllers/DashboardController.php`
  - Di `getMyDuesSummary()` tambahkan `default_amount` juga (opsional) atau cukup pakai di halaman `/member/dues`.
  - Perbaiki perhitungan unpaid_periods agar join_date-aware:
    - Ambil join_date dari `Member` user (load minimal: id, join_date).
    - Range 6 bulan terakhir tetapi mulai dari bulan join (max(join_month, now-5)).
    - Missing record tetap dianggap unpaid (OK), tapi jangan sebelum join.

2.2 Frontend: Member Dues page jangan hardcode 30000
- File: `resources/js/Pages/Member/Dues.vue`
  - Tambah prop `default_amount` (Number).
  - Ganti `formatCurrency(30000)` menjadi `formatCurrency(default_amount)`.
  - Untuk render tiap row: jika `payment.amount` null/0 untuk unpaid, pastikan backend sudah isi; atau fallback di UI.

2.3 Frontend: Dashboard tampilkan chips 3 bulan tunggakan
- File: `resources/js/Pages/Dashboard.vue`
  - Di personal dues card, tambahkan UI kecil:
    - Jika `myDues.unpaid_periods.length > 0`, render chips list 3 periode (format `MMM YYYY`).
    - Gunakan `myDues.unpaid_periods` yang sudah ada di prop.
  - Pastikan tetap guard `features.finance !== false`.

2.4 Route coverage `/member/dues`
- File: `routes/web.php`
  - Saat ini route `/member/dues` memakai `role:anggota,bendahara,super_admin,admin_pusat,admin_unit`.
  - Untuk “semua user adalah anggota”, ubah jadi:
    - middleware: `['feature:finance', 'auth']`
  - Controller sudah handle empty-state bila tidak ada member_id, jadi aman.

3) Prompt 3 GAP-FIX — Permission final + feature flag finance untuk /finance + tests
3.1 Tegaskan otoritas update iuran
- File: `app/Policies/DuesPaymentPolicy.php`
  - Putuskan policy final (default yang diminta):
    - `update()` dan `updateForMember()`:
      - allow jika `hasGlobalAccess()`
      - allow jika role `bendahara` dan unit match
      - deny untuk `admin_unit` (view-only)
  - Pastikan `view()` tetap:
    - member self-access by member_id
    - bendahara/admin_unit unit-scope view (OK)

3.2 Terapkan `feature:finance` untuk seluruh /finance routes
- File: `routes/web.php`
  - Tambahkan middleware `feature:finance` pada group `Route::prefix('finance')...`
  - Pastikan saat feature finance false:
    - `/finance/*` return 503 (sesuai middleware EnsureFeatureEnabled)
    - Menu finance sudah hidden (cek existing UI; tidak perlu ubah besar).

3.3 Pastikan controller enforcement tetap konsisten
- File: `app/Http/Controllers/Finance/FinanceDuesController.php`
  - Setelah policy diperketat (admin_unit tidak boleh update):
    - `update()` dan `massUpdate()` akan otomatis 403 untuk admin_unit (OK).
  - Pastikan message 403 tetap jelas.

3.4 Update tests agar sesuai policy final
- File: `tests/Feature/DuesAuthorizationTest.php`
  - Jika admin_unit sekarang tidak boleh update, pastikan ada test yang mengunci itu (403).
  - Pastikan test untuk audit log tetap pass.

VALIDASI (WAJIB JALANKAN)
- `php artisan test --filter DuesGenerateCommandTest`
- `php artisan test --filter MemberDuesPageTest`
- `php artisan test --filter DuesAuthorizationTest`

DOKUMENTASI (WAJIB UPDATE)
- Append sesuai template AGENTS.md ke:
  - `development-notes/backend.md` (schedule+generator join_date, route, controller props)
  - `development-notes/ux.md` (dashboard chips + dues page amount)
  - `development-notes/security.md` (policy final: siapa boleh update)
  - `development-notes/testing.md` (commands dan hasil test)

ACCEPTANCE CRITERIA
- Schedule iuran tidak hardcode tanggal 1; pakai config.
- Anggota tidak dianggap menunggak sebelum join (kalau join_date tersedia).
- “Iuran Saya” nominal mengikuti config, bukan hardcode.
- Dashboard personal dues menampilkan 3 bulan tunggakan (chips).
- Admin_unit tidak bisa mark paid (kecuali diputuskan sebaliknya), bendahara bisa.
- `/finance/*` ikut mati saat `FEATURE_FINANCE=false`.
- Semua tests terkait iuran lulus.

- Commands: none
- Decisions/Risks:
  - Dashboard announcement section now properly constrained to match other sections' width.
  - Form inputs now have clear placeholders and guidance to prevent user confusion.
- Next: none

- Date: 2025-12-31 17:18
- Scope: ux
- Summary:
  - Final fix for announcement width: wrapped both announcement and grid sections in shared `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` container.
  - Ensures announcement card width exactly matches the 5-column grid cards below for perfect visual balance.
- Files:
  - resources/js/Pages/Dashboard.vue
- Commands: none
- Decisions/Risks:
  - Both sections now share the same parent container, guaranteeing identical width constraints.
- Next: none


- Date: 2025-12-31 17:53
- Scope: ux
- Summary:
  - Restored full width for announcement and grid cards by removing max-w-7xl wrapper.
  - User clarified that Kartu Digital and Profil Anggota cards (2-column grid) are the correct width reference.
  - All dashboard sections now use consistent full-width layout.
- Files:
  - resources/js/Pages/Dashboard.vue
- Commands: none
- Decisions/Risks:
  - Dashboard sections now match AppLayout's natural content width without artificial constraints.
- Next: none


- Date: 2026-01-03 05:52
- Scope: ux
- Summary:
  - Feature Toggle UI: Hide announcement menus when FEATURE_ANNOUNCEMENTS=false.
  - AppLayout.vue: Added feature flag checks to public and admin announcement links (desktop + mobile).
  - Dashboard.vue: Added feature flag check to hide pinned announcements section.
  - Use `$page.props.features?.announcements !== false` pattern for fail-safe defaults.
- Files:
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Dashboard.vue
- Decisions/Risks:
  - Menu items hidden but backend still guards routes with 503 for direct access.
  - Missing props treated as enabled (fail-safe).
- Next: none

- Date: 2026-01-03 19:22
- Scope: ux
- Summary:
  - Fixed QR code display in letter preview: QR now only shows for final letters (approved/sent/archived).
  - Non-final letters show "Menunggu Persetujuan" placeholder instead of QR.
  - Added `isFinal` prop from backend to control conditional rendering.
  - QR image now has `block bg-white` class for visibility; fallback link styled with `text-blue-600`.
- Files:
  - resources/js/Pages/Letters/Preview.vue
- Decisions/Risks:
  - Removed redundant computed `isFinal` since it's now a prop from backend.
  - "Menunggu Persetujuan" message provides clear feedback for pending letters.
- Next: none

- Date: 2026-01-03 20:12
- Scope: ux
- Summary:
  - Implemented "Iuran Saya" page showing personal dues history (12 months).
  - Added summary card with current month status and unpaid count.
  - Table displays period, status badge (green/red), amount, paid_at, notes.
  - Added "Iuran Saya" menu item to sidebar (guarded by `features.finance`).
  - Added personal dues card to Dashboard for users with member linkage.
- Files:
  - resources/js/Pages/Member/Dues.vue (NEW)
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Dashboard.vue
- Decisions/Risks:
  - Empty state shown for users without member_id.
  - Menu visible for all users when finance feature enabled.
- Next: none
- Date: 2026-01-04 07:51
- Scope: ux
- Summary:
  - Dashboard KPI cards “Total Unit” and “Total Anggota” now always show nationwide totals for all roles to reinforce organizational scale.
  - Unit-scoped “Total Anggota” card remains available separately (members_unit_total).
- Files:
  - none
- Commands: none
- Decisions/Risks:
  - This is a visibility change only; existing navigation remains role-gated.
- Next: none

- Date: 2026-01-04 08:28
- Scope: ux
- Summary:
  - Fixed Admin Members "Import Anggota" button not navigating by adding `href` support to `SecondaryButton` (renders Inertia `Link` when href is provided).
- Files:
  - resources/js/Components/UI/SecondaryButton.vue
- Commands: none
- Decisions/Risks:
  - Existing `SecondaryButton` usages with `href` now work (Import pages); disabled/loading prevents navigation.
- Next: none

- Date: 2026-01-04 11:49
- Scope: ux
- Summary:
  - Added PWA icons at `public/icons/icon-192.png` and `public/icons/icon-512.png` to fix manifest 404.
- Files:
  - public/icons/icon-192.png
  - public/icons/icon-512.png
- Commands: none
- Decisions/Risks:
  - Icons are generated from `public/img/logo.png` via GD resize.
- Next: none

- Date: 2026-01-04 12:12
- Scope: ux
- Summary:
  - Letter preview QR now renders via SVG data URI when PNG/Imagick is unavailable (should display QR instead of "QR unavailable").
- Files:
  - resources/js/Pages/Letters/Preview.vue
- Commands: none
- Decisions/Risks:
  - Uses `qrMime` prop to select image mime; fallback remains verify link on error.
- Next: none

- Date: 2026-01-04 18:01
- Scope: ux
- Summary:
  - Removed missing grid SVG background from login screen to stop 404 requests.
- Files:
  - resources/js/Pages/Auth/Login.vue
- Commands: none
- Decisions/Risks:
  - Login left panel no longer uses the subtle grid pattern.
- Next: none

- Date: 2026-01-04 19:20
- Scope: ux
- Summary:
  - Implemented Topbar Typeahead Search.
  - Interaction:
    - Input debounce (300ms).
    - Dropdown with categorized results.
    - Keyboard navigation (Arrows, Enter to visit, Esc to close).
    - Loading spinner state inside input.
  - Component: `TopbarSearch.vue` integrates into `AppLayout`.
  - Responsive: Hidden on mobile (default layout behavior maintained).
- Decisions:
  - Used existing `neutral-50` background for input to match previous design.
  - Added "Enter to see all" hint in dropdown footer.
- Next: none

- Date: 2026-01-04 19:35
- Scope: ux
- Summary:
  - Implemented full Search Page (`/search`).
  - Layout:
    - Search Bar at top.
    - Horizontal Tabs for Category Filtering (All, Announcement, Letter, etc.).
    - Filter logic: Switching tab updates URL `?type=...` and reloads results.
  - Grid Layout for Results.
  - Pagination:
    - Visible only when a specific category is selected.
    - Custom `Pagination.vue` component used.
  - Empty States:
    - "Please enter search term" (initial).
    - "No results found" (global or per category).
- Components:
  - `Resources/js/Pages/Search/Index.vue`
- Next: none

- Date: 2026-01-04 20:32
- Scope: ux
- Summary:
  - Added `Admin/Users/Show` page for viewing user + linked member details from search results.
  - Added subtle highlight for finance ledger row when opened via `?focus=` (used by search results).
- Files:
  - resources/js/Pages/Admin/Users/Show.vue
  - resources/js/Pages/Finance/Ledgers/Index.vue
- Commands: none
- Decisions/Risks:
  - `?focus=` only brings target row to top of current query; pagination/filters still apply.
- Next: none

- Date: 2026-01-04 20:56
- Scope: ux
- Summary:
  - Added pagination to user list in Role Detail (`Admin/Roles/Show.vue`) using standard `Pagination` component.
  - Ensures large user lists (e.g. for `anggota` role) are manageable.
- Files:
  - resources/js/Pages/Admin/Roles/Show.vue
- Commands: none
- Decisions/Risks:
  - Backend `RoleController::show` was already paginating; frontend now exposes the controls properly.
- Next: none

- Date: 2026-01-05 09:42
- Scope: ux
- Summary:
  - Settings Page Cleanup:
    - Hidden "Integrasi/API" and "Bahasa" tabs (stub features).
    - Hidden MFA toggle and disabled "Reset Password" button (Coming Soon).
    - Made "Profil Pengguna" name read-only with helper text.
    - Restricted "Privasi & Data" buttons to show only for members; others see info text.
- Files:
  - resources/js/Pages/Settings/Index.vue
- Decisions/Risks:
  - "Simpan Semua" button removed as it was non-functional.
  - Privacy Policy link updated to point to Help Center.
- Next: none

- Date: 2026-01-05 09:49
- Scope: ux
- Summary:
  - Settings Profile:
    - Enabled "Nama" field editing.
    - Added "Simpan Profil" button with success/fail feedback.
- Files: resources/js/Pages/Settings/Index.vue
- Next: none

- Date: 2026-01-05 09:53
- Scope: ux
- Summary:
  - Settings Security Tab:
    - Added "Sesi Saya" section listing user sessions.
    - Added "Logout Semua Device Lain" button.
    - Shows IP, User Agent (truncated), and Last Activity.
- Files: resources/js/Pages/Settings/Index.vue
- Next: none

- Date: 2026-01-05 09:58
- Scope: ux
- Summary:
  - Settings Notifications:
    - Added new categories: Pengumuman Penting, Iuran & Keuangan, Laporan, Keuangan (Admin).
    - Updated toggle list order.
- Files: resources/js/Pages/Settings/Index.vue
- Next: none

- Date: 2026-01-05 10:15
- Scope: ux
- Summary:
  - Reports Growth/Mutations sekarang menampilkan data real dari props (bukan dummy) dan filter bekerja via query.
  - Tombol Export di Growth/Mutations memakai POST CSV legacy endpoint dengan filter aktif.
- Files:
  - resources/js/Pages/Reports/Growth.vue
  - resources/js/Pages/Reports/Mutations.vue
- Commands: none
- Decisions/Risks:
  - Export Growth/Mutations masih memakai endpoint legacy `/reports/{type}/export`.
- Next: none

- Date: 2026-01-05 10:21
- Scope: ux
- Summary:
  - Memperbaiki error Settings page (watch/onMounted tidak ter-import).
- Files:
  - resources/js/Pages/Settings/Index.vue
- Commands: none
- Decisions/Risks:
  - None.
- Next: none

- Date: 2026-01-05 10:32
- Scope: ux
- Summary:
  - Enabled Reset Password modal in Settings security tab with current/new/confirm inputs.
  - Hooked modal submit to backend password update endpoint.
- Files:
  - resources/js/Pages/Settings/Index.vue
- Commands: none
- Decisions/Risks:
  - Error feedback is single-message; field-level validation is not shown yet.
- Next: none

- Date: 2026-01-05 11:28
- Scope: ux
- Summary:
  - Settings actions now send CSRF token header for fetch requests (reset password, profile, notifications, revoke sessions).
- Files:
  - resources/js/Pages/Settings/Index.vue
- Commands: none
- Decisions/Risks:
  - None.
- Next: none

- Date: 2026-01-05 10:38
- Scope: ux
- Summary:
  - Dashboard: Connected Recent Mutations and Activity to real backend data.
  - Removed mock data from Dashboard.vue.
- Files: resources/js/Pages/Dashboard.vue
- Next: none

- Date: 2026-01-07 09:21
- Scope: ux
- Summary:
  - TE1: Integrated Tiptap Rich Text Editor for letter body field.
  - Created reusable `RichTextEditor.vue` component with toolbar (Bold, Italic, Underline, Lists, Headings H2/H3, Alignment, Link, Undo/Redo).
  - Replaced textarea in `Letters/Form.vue` with RichTextEditor.
  - Updated `applyTemplate` to convert plain text templates to HTML paragraphs.
  - Added 140+ lines of Tiptap styling to `app.css` (toolbar, content typography, alignment).
- Files:
  - resources/js/Components/UI/RichTextEditor.vue (NEW)
  - resources/js/Pages/Letters/Form.vue
  - resources/css/app.css
- Commands:
  - npm i @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-underline @tiptap/extension-link @tiptap/extension-text-align
  - npm run build
- Decisions/Risks:
  - Link validation requires https:// prefix.
  - Template content now stored as HTML in `form.body`.
- Next: none

- Date: 2026-01-07 09:40
- Scope: ux
- Summary:
  - TE3: Render HTML in letter Show/Preview/PDF views.
  - Updated LetterController to pass `bodyHtml` (sanitized) prop to views.
  - Show.vue and Preview.vue now use `v-html` for rich HTML rendering.
  - pdf.blade.php uses `{!! $bodyHtml !!}` for PDF rendering.
  - Added 90 lines of `.letter-body` CSS styling (p, ul, ol, li, a, h2, h3, blockquote, text-align).
- Files:
  - app/Http/Controllers/LetterController.php
  - resources/js/Pages/Letters/Show.vue
  - resources/js/Pages/Letters/Preview.vue
  - resources/views/letters/pdf.blade.php
  - resources/css/app.css
- Commands:
  - npm run build
- Decisions/Risks:
  - All HTML is sanitized server-side before rendering (XSS safe).
  - Plain text in old letters auto-converts to HTML paragraphs.
- Next: none

- Date: 2026-01-07 09:47
- Scope: ux
- Summary:
  - TE4: Rich Text Editor Usage Documentation.
  - **Cara Pakai Editor:**
    - Toolbar tersedia untuk: Bold, Italic, Underline, Bullet List, Numbered List, Heading 2/3, Alignment, Link.
    - Klik tombol toolbar untuk mengaktifkan/deaktifkan format.
    - Untuk link: pilih teks → klik Link → masukkan URL (harus dimulai dengan http:// atau https://).
    - Tekan Ctrl+Z/Ctrl+Y untuk Undo/Redo.
  - **Batasan:**
    - Tidak support custom margins (layout A4 dari Preview/PDF mengatur margin).
    - Tidak support custom fonts atau font sizes (mengikuti standar surat).
    - Style lain seperti background color, border akan di-strip.
    - Hanya text-align (left/center/right/justify) yang diperbolehkan.
    - Link eksternal hanya https:// atau mailto:.
- Files:
  - development-notes/ux.md (documentation update)
- Decisions/Risks:
  - Whitelist approach untuk keamanan.
- Next: none

- Date: 2026-01-07 18:25
- Scope: ux
- Summary:
  - F1: Added multi-line address field for external recipients.
  - Form.vue: Added textarea for `to_external_address` with placeholder.
  - Preview.vue: Updated `recipientName` computed to include address (joined by newlines).
  - pdf.blade.php: Added address with `nl2br(e(...))` for safe HTML rendering.
  - Field cleared when user switches to_type away from 'eksternal'.
- Files:
  - database/migrations/2026_01_07_182400_add_to_external_address_to_letters_table.php (NEW)
  - app/Models/Letter.php
  - app/Http/Controllers/LetterController.php
  - resources/js/Pages/Letters/Form.vue
  - resources/js/Pages/Letters/Preview.vue
  - resources/views/letters/pdf.blade.php
- Commands:
  - php artisan migrate
  - npm run build
- Decisions/Risks:
  - Address is optional (nullable in DB).
  - Line breaks preserved in Preview and PDF.
- Next: none

- Date: 2026-01-08 09:00
- Scope: ux
- Summary:
  - Removed duplicate alert on `letters/outbox` by only rendering one banner (success takes priority; error uses flash).
- Files:
  - resources/js/Pages/Letters/Outbox.vue
- Commands:
  - npm run build
- Decisions/Risks:
  - Outbox no longer reads `errors.letter`; use `flash.error` for outbox-level failures if needed.
- Next: none

- Date: 2026-01-08 10:13
- Scope: ux
- Summary:
  - Moved global flash messages into `AppLayout` using `AlertBanner` to standardize style + dismiss/auto-dismiss.
  - Removed per-page flash banners in Letters pages to prevent duplicate alerts (Inbox/Outbox/Approvals/Show; create form keeps validation summary only).
- Files:
  - resources/js/Layouts/AppLayout.vue
  - resources/js/Pages/Letters/Inbox.vue
  - resources/js/Pages/Letters/Outbox.vue
  - resources/js/Pages/Letters/Approvals.vue
  - resources/js/Pages/Letters/Show.vue
  - resources/js/Pages/Letters/Form.vue
- Commands: none (build blocked by local permissions; rerun `npm run build` manually)
- Decisions/Risks:
  - Flash rendering is now centralized in layout; pages should avoid duplicating `flash.success/error`.
- Next: If any other pages still show double alerts, remove their local flash banners and rely on layout.

- Date: 2026-01-08 10:22
- Scope: ux
- Summary:
  - Removed duplicate flash banner from Finance Ledgers page to rely on AppLayout alert.
- Files:
  - resources/js/Pages/Finance/Ledgers/Index.vue
- Commands: none
- Decisions/Risks:
  - Keeps one global alert path for flash messages.
- Next: none

- Date: 2026-01-08 11:30
- Scope: ux
- Summary:
  - Removed remaining per-page flash alerts/toasts to avoid double notifications; rely on `AppLayout` for `flash.success/error`.
  - Updated Master Data, Finance, and Update Requests pages; kept non-flash validation/error banners where applicable.
- Files:
  - resources/js/Pages/Admin/LetterApprovers/Index.vue
  - resources/js/Pages/Admin/LetterCategories/Index.vue
  - resources/js/Pages/Admin/LetterCategories/Form.vue
  - resources/js/Pages/Admin/Units/Index.vue
  - resources/js/Pages/Admin/Roles/Show.vue
  - resources/js/Pages/Admin/UnionPositions/Form.vue
  - resources/js/Pages/Admin/Updates/Index.vue
  - resources/js/Pages/Finance/Categories/Index.vue
  - resources/js/Pages/Finance/Categories/Form.vue
  - resources/js/Pages/Finance/Dues/Index.vue
  - resources/js/Pages/Finance/Ledgers/Form.vue
  - resources/js/Pages/Member/Profile.vue
- Commands: none
- Decisions/Risks:
  - Flash messages now have a single render path (layout). Any page-level “success/error” UI should not mirror flash.
- Next: Manually spot-check `onboarding`, `master data`, and `update requests` flows for single alert behavior after create/update/delete.
- Date: 2026-01-08 10:44
- Scope: ux
- Summary:
  - Mutation Cancel Button: Added "Batalkan" button in mutations index for pending rows.
  - Confirm dialog before cancel action.
  - Loading state while cancel is processing ("Membatalkan...").
  - Cancelled mutations display with neutral gray badge and Indonesian label "Dibatalkan".
  - Status labels now use Indonesian: Menunggu, Disetujui, Ditolak, Dibatalkan.
  - Flash messages handled via existing global layout (no duplicate alerts).
- Files:
  - resources/js/Pages/Admin/Mutations/Index.vue
- Commands: none
- Decisions/Risks:
  - Cancel button only appears for pending status.
  - Uses `router.post()` with `preserveScroll: true` for smooth UX.
- Next: none
