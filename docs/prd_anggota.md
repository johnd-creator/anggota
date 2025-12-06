# Product Requirements Document (PRD)

**Nama Proyek:** Sistem Informasi Manajemen Anggota Serikat Pekerja (SIM-SP)
**Versi:** 1.0
**Platform:** Web Application (Responsive)
**Tech Stack:** Laravel (Backend), MySQL (Database), Inertia.js + Vue / Blade (Frontend - Opsional)

---

## 1. Pendahuluan

### 1.1 Latar Belakang
Serikat Pekerja membutuhkan sistem terpusat untuk mengelola data anggota yang tersebar di berbagai unit/organisasi. Saat ini, pengelolaan perpindahan (**mutasi**) anggota dan penomoran identitas masih manual atau tidak terintegrasi, menyulitkan pelacakan sejarah dan status aktif anggota.

### 1.2 Tujuan
* **Sentralisasi** data anggota dari berbagai unit/sekretariat.
* **Otomatisasi** pembuatan Nomor Anggota Unik (**NRA**) dengan logika cerdas.
* Memfasilitasi proses **mutasi anggota** antar unit dengan persetujuan pusat.
* Menyediakan **Kartu Anggota Digital** yang dapat diakses mandiri oleh anggota melalui SSO.

---

## 2. Aktor dan Hak Akses (User Roles)

| Role | Deskripsi & Hak Akses |
| :--- | :--- |
| **Super Admin (Pusat)** | Mengelola Master Data Organisasi, **Menyetujui/Menolak Mutasi**, Melihat Statistik Global. |
| **Admin Sekretariat (Unit)** | Mengelola data anggota di unitnya sendiri, Menambahkan anggota baru, **Mengajukan permohonan mutasi anggota keluar**. |
| **Anggota (User)** | Login via **Google SSO**. Hanya bisa melihat Profil Diri dan **Kartu Anggota Digital**. |
| **Bendahara (Unit)** | Mencatat pemasukan/pengeluaran dan saldo unitnya sendiri; hanya melihat data keuangan unitnya; tidak bisa mengelola anggota/role; tetap mendapatkan menu Anggota (Profil, Kartu Digital, Notifikasi). *(Rencana pengembangan baru.)* |

---

## 3. Fitur Utama & Fungsional

### 3.1 Otentikasi & Keamanan
* **Google Single Sign-On (SSO):** Semua *role* dapat login menggunakan akun Google. Sistem memvalidasi email untuk penentuan *role*.
* **Role Management:** Pembatasan akses ke fitur/halaman berdasarkan *role*.
* **Mapping Role Otomatis:** Domain email atau daftar putih email tertentu dipetakan ke Super Admin/Admin Unit; akun baru tanpa mapping otomatis diminta melengkapi form onboarding sebelum diberi hak akses. *(Di aplikasi: whitelist contoh sudah ada, tapi enforcement domain Workspace belum ketat.)*
* **Session & Recovery Controls:** Durasi sesi 12 jam, dukungan *forced logout* oleh Super Admin, serta fallback login manual (username/password) hanya untuk kondisi darurat dengan MFA. *(Belum tersedia; saat ini hanya SSO Google + login form biasa, tanpa MFA/forced logout UI.)*
* **Monitoring Percobaan Login:** Simpan log autentikasi (berhasil/gagal), sertakan alamat IP dan perangkat untuk kebutuhan audit keamanan.
* **Role Reguler (Pending Anggota):** 
  * Jika pengguna login memakai akun Google (mis. `@gmail.com`) yang belum terdaftar sebagai anggota, sistem otomatis memberi role `Reguler`.
  * Role ini hanya dapat mengakses halaman informasi sederhana (contoh halaman `itworks`) dan tidak memiliki akses ke data anggota atau fitur lain.
  * Pada halaman tersebut ditampilkan instruksi untuk menghubungi Admin Unit masing-masing (atau tautan kontak) agar proses verifikasi dan registrasi anggota dapat dilakukan sebelum akses penuh diberikan.
  * Setelah Admin Unit menyetujui dan melengkapi data, role pengguna diperbarui menjadi `Anggota`.

### 3.2 Manajemen Master Data (Role: Super Admin)
* **Master Organisasi/Unit:** CRUD Data Unit (Nama Unit, **Kode Unit 3 digit**, Alamat).

### 3.3 Manajemen Keanggotaan (Role: Admin Unit)
* **Input Anggota Baru:** Form isian Anggota (Nama, NIK Karyawan, Email Google, Tgl. Bergabung).
* **Generate ID Otomatis:** Sistem akan membuat NRA unik saat anggota baru disimpan (sesuai Logika NRA).
* **List Anggota:** Melihat daftar anggota di unitnya sendiri.
* **Adopsi Pengguna Role Reguler:**
  * Admin memiliki daftar permintaan dari pengguna role Reguler yang memilih unit atau menghubungi admin; daftar ini tampil dalam modul onboarding.
  * Admin memverifikasi data minimal (nama, email, NIK, dokumen pendukung) dan dapat meminta perbaikan via catatan kepada pengguna.
  * Tombol `Terima sebagai Anggota` mengkonversi akun Reguler menjadi Anggota: sistem membuat entri anggota lengkap, menetapkan unit, dan mengirim notifikasi ke pengguna, Admin Unit, dan Super Admin.
  * Penolakan wajib menyertakan alasan; pengguna menerima notifikasi berisi instruksi lanjutan (mis. lampiran dokumen).
* **Atribut Anggota yang Dikelola:** 
  * Data personal: tempat & tanggal lahir, alamat domisili, kontak darurat, jenis pekerjaan/jabatan, status keanggotaan (aktif/resign/pensiun), status kepgawaian (Organik/TKWT), tanggal efektif keanggotaan.
  * Data organisasi: unit saat ini, riwayat unit sebelumnya, tanggal masuk/keluar unit, nomor kartu fisik (jika ada), unggahan dokumen (surat penugasan, form mutasi).
* **Status & Lifecycle:** Admin bisa mengubah status anggota menjadi Cuti, Suspended, Resign, atau Pensiun dengan alasan dan tanggal efektif; perubahan status otomatis mengunci akses anggota tertentu (mis. Resign/Pensiun tidak dapat login kecuali untuk melihat riwayat). *(Belum tersedia; saat ini status hanya label, belum mengunci akses.)*
* **Validasi Data:** 
  * Email wajib domain Google Workspace serikat, nomor telepon mengikuti format internasional. *(Partial; belum enforce domain ketat.)*
  * Sistem menolak duplikasi NIK/email antar anggota dan menandai entri ganda untuk review pusat. *(Belum tersedia; belum ada penolakan duplikasi lintas unit.)*

### 3.4 Kartu Anggota Digital (Role: Anggota)
* Tampilan **responsive** yang menunjukkan data keanggotaan terbaru (termasuk unit dan NRA yang valid saat ini).
* Menampilkan NRA, Unit Kerja, Tgl. Bergabung.
* Memuat foto anggota terkini, kode QR unik untuk verifikasi keaslian kartu, dan tombol unduh PDF bagi anggota.
* Endpoint verifikasi QR menampilkan nama singkat, unit, serta status aktif/non-aktif sehingga pihak eksternal dapat mengecek keabsahan anggota.

### 3.5 Portal Self-Service Anggota
* Anggota dapat memperbarui data pribadi terbatas (alamat, nomor telepon, foto) yang kemudian harus disetujui Admin Unit.
* Menyediakan riwayat mutasi dan status terbaru pengajuan mutasi atau perubahan data. *(Belum tersedia; riwayat status ada, pengajuan mutasi dari anggota belum.)*
* Menyediakan tautan unduh kartu digital, buku pedoman serikat, serta FAQ kebijakan keanggotaan. *(Buku pedoman/FAQ belum tersedia.)*
* Sistem menampilkan notifikasi *pending action* (mis. dokumen wajib belum diunggah) agar anggota segera melengkapi persyaratan. *(Belum tersedia.)*

### 3.6 Notifikasi & Komunikasi
* Notifikasi dikirim via email, *in-app notification*, dan opsional webhook/WhatsApp Gateway (jika tersedia) dengan template standar (pengajuan mutasi, persetujuan, penolakan, status suspend). *(Saat ini hanya in-app notification; email/WA/webhook belum tersedia.)*
* Admin dapat mengatur preferensi frekuensi notifikasi (real-time vs ringkasan harian). *(Belum tersedia.)*
* Reminder otomatis dikirim bila pengajuan mutasi belum diproses >3 hari kerja; eskalasi ke Super Admin bila >5 hari. *(Belum tersedia.)*
* Semua notifikasi tercatat dalam log sehingga dapat diaudit kapan dan kepada siapa pesan terkirim. *(Partial; in-app tersimpan, tapi kanal lain belum ada.)*

### 3.7 Pelaporan & Dashboard Operasional
* Dashboard Super Admin menampilkan statistik agregat (jumlah anggota aktif per unit, pertumbuhan bulanan, rasio mutasi masuk/keluar, status approval).
* Admin Unit mendapatkan ringkasan khusus unitnya (anggota baru minggu ini, dokumen kedaluwarsa, status kartu digital yang belum diunduh).
* Setiap laporan dapat difilter berdasarkan rentang tanggal, unit, status keanggotaan, serta diekspor ke CSV/Excel/PDF. *(Ekspor CSV/Excel/PDF belum tersedia; filtering basic ada di beberapa modul.)*

### 3.8 Manajemen Keuangan Bendahara (Rencana)
* **Scope & Hak Akses:** Role Bendahara hanya untuk keuangan unitnya sendiri; tidak bisa mengelola anggota atau role lain; Super Admin dapat melihat semua unit; tetap mendapatkan menu Anggota (Profil, Kartu Digital, Notifikasi), serta dashboard ringkas keuangan unitnya saja.
* **Kategori Keuangan (CRUD per Unit):** Tabel `finance_categories` (organization_unit_id, nama, tipe pemasukan/pengeluaran, deskripsi, created_by). Bendahara hanya bisa kategori unitnya; Super Admin bisa menambah kategori global/lintas unit.
* **Ledger Unit:** Tabel `finance_ledgers` (organization_unit_id, finance_category_id, tipe, nominal, tanggal, deskripsi, attachment, created_by, optional approved_by/status draft/approved) untuk pencatatan pemasukan/pengeluaran, saldo berjalan per unit.
* **Kontrol & Audit:** Setiap transaksi mencatat pembuat + timestamp; edit/hapus hanya oleh pembuat atau Super Admin; audit log wajib.
* **Dashboard Bendahara:** Ringkasan saldo unit, total pemasukan/pengeluaran per periode, daftar transaksi terbaru; fokus unit, tanpa statistik global.
* **Persetujuan (opsional):** Status draft/menunggu/approved; approver Admin Unit atau Super Admin bila diperlukan.
* **Keamanan & Validasi:** Nominal wajib angka positif, pembatasan tipe/ukuran file bukti, rate limit upload; tidak ada akses ke unit lain.
* **Integrasi:** Modul berdiri sendiri, tidak mengubah logika anggota/NRA; ekspor CSV sederhana untuk rekonsiliasi bila diperlukan.

---

## 4. Logika Penomoran Anggota (Smart ID)

Format Nomor Anggota: **`KKK-OOO-TTNNN`**

| Bagian | Deskripsi | Contoh |
| :--- | :--- | :--- |
| **KKK** | **Kode Unit/Organisasi** (3 digit) | `010` |
| **OOO** | **Kode Organisasi Default/Induk** (Statis) | `SPPIPS` |
| **TT** | **Tahun Bergabung** (2 digit) | `24` (untuk 2024) |
| **NNN** | **Nomor Urut** (3 digit, *increment* per tahun **per unit**) | `003` |

### Logika Mutasi (Transfer Anggota)

Saat anggota pindah dari Unit A ke Unit B:
1.  **Kode Unit (KKK)** berubah menjadi Kode Unit B (Organisasi Tujuan).
2.  **Tahun Bergabung (TT)** **TETAP** (tidak berubah).
3.  **Nomor Urut (NNN)** di-*reset* dan di-*increment* berdasarkan nomor urut terakhir di **Unit B** pada **Tahun Bergabung yang sama**.

*Contoh Mutasi:*
* **Lama:** `010-SPPIPS-24003` (Unit 010)
* **Mutasi ke Unit 020.** Di Unit 020, anggota terakhir yang bergabung tahun 2024 adalah nomor `005`.
* **Baru:** `020-SPPIPS-24006`

### 4.1 Status Keanggotaan & Lifecycle
* **Aktif:** Anggota sah dengan akses penuh ke portal dan kartu digital.
* **Cuti/Suspended:** NRA tetap, tetapi kartu menampilkan status sementara; akses portal dibatasi hanya untuk melihat data diri.
* **Resign:** NRA dibekukan; anggota tidak dapat login namun riwayat tetap tersimpan. Pengaktifan ulang memerlukan approval pusat.
* **Pensiun:** NRA dialihfungsikan sebagai nomor arsip; kartu digital menampilkan status Pensiun dan tidak valid untuk verifikasi keanggotaan aktif.
* Perubahan status wajib mencantumkan alasan, tanggal efektif, dan bukti pendukung; sistem otomatis membuat entri audit.

### 4.2 Riwayat Mutasi & Audit Trail
* Setiap mutasi atau perubahan data menciptakan entri riwayat yang menampilkan: tanggal, unit asal/tujuan, petugas yang memproses, alasan, dan dokumen pendukung.
* Riwayat dapat ditampilkan sebagai timeline pada detail anggota, dapat difilter, dan diekspor ke CSV.
* Audit log disediakan untuk Super Admin yang menampilkan perubahan kritis (ubah data personal, perubahan role, login sensitif).

---

## 5. Fitur Mutasi / Transfer Anggota (Workflow)

| Tahap | Aktor | Aktivitas |
| :--- | :--- | :--- |
| 1. Pengajuan | Admin Unit Asal | Mengajukan transfer Anggota X, memilih Unit Tujuan. Status: *Waiting for Approval*. |
| 2. Persetujuan | Super Admin (Pusat) | Menerima notifikasi, memverifikasi, dan **Menyetujui** atau **Menolak**. |
| 3. Eksekusi | Sistem Laravel | Jika disetujui, sistem menjalankan fungsi otomatis: *Update* `organization_id` anggota; *Generate* NRA Baru (Sesuai Logika Mutasi); Mencatat Riwayat Mutasi. |
| 4. Notifikasi | Sistem | Mengirim notifikasi ke Admin Unit Tujuan bahwa ada anggota baru masuk. |

**Tambahan Aturan Operasional:**
* Pengajuan harus menyertakan alasan tertulis, tanggal efektif, dan dokumen (surat rekomendasi/berita acara) sebelum dapat disubmit.
* SLA persetujuan maksimal 3 hari kerja; sistem menandai pengajuan lewat SLA dan menambahkan catatan pada dashboard Super Admin.
* Keputusan *approve/reject* wajib menyertakan komentar sehingga dapat dilihat Admin pengaju dan anggota terkait.
* Setelah mutasi disetujui dan dieksekusi, notifikasi otomatis dikirim ke anggota, Admin unit asal, dan unit tujuan lengkap dengan NRA baru serta timeline riwayat.

---

## 6. Pelaporan & Analitik

1. **Laporan Pertumbuhan Anggota:** Jumlah anggota per unit per bulan, termasuk filter status (aktif, cuti, resign).
2. **Laporan Mutasi:** Daftar mutasi masuk/keluar per unit, SLA penyelesaian, sebab mutasi paling umum.
3. **Monitoring Dokumen:** Daftar anggota dengan dokumen kedaluwarsa atau belum lengkap (Surat rekomendasi).
4. **Export & Integrasi HR:** Seluruh laporan bisa diekspor CSV/Excel; API atau webhook disiapkan untuk sinkronisasi dengan HRIS / payroll jika dibutuhkan di tahap berikutnya.
5. **Dashboard Alarm:** Menampilkan indikator peringatan (mutasi pending > SLA, banyak login gagal dari satu IP, dsb.) untuk memudahkan tindakan cepat.

---

## 7. Requirements Non-Fungsional

1.  **Audit Trail:** Setiap tindakan penting (Mutasi, Edit Data Anggota) harus dicatat log-nya (siapa, kapan, perubahan apa).
2.  **Keamanan:** Penggunaan **Laravel Socialite** untuk integrasi SSO Google yang aman. Memastikan NRA tidak mudah diakses atau ditebak secara sekuensial oleh publik.
3.  **Kinerja:** *Query* untuk NRA harus dioptimalkan (menggunakan *index* pada `join_year`, `organization_id`, dan `sequence_number`). Target waktu respon halaman <2 detik untuk daftar anggota 10.000 baris.
4.  **Integrasi:** Aplikasi harus siap untuk *export* data keanggotaan (CSV/Excel) untuk kebutuhan pelaporan.
5.  **Ketersediaan:** Target uptime 99%, backup database harian dengan retensi 30 hari, serta rencana *disaster recovery* (RPO 24 jam, RTO 4 jam).
6.  **Kepatuhan & Privasi:** Terapkan prinsip minimasi data, enkripsi data sensitif saat transit & saat tersimpan, dan patuhi kebijakan perlindungan data karyawan.
7.  **Observability:** Logging terstruktur (JSON) dan integrasi dengan tool monitoring (mis. Laravel Telescope / Sentry) untuk menangkap error, metrik performa, dan alert otomatis bila terjadi lonjakan login gagal.

### Fitur yang Sudah Ada di Aplikasi (belum tercantum di PRD)
* **Import Anggota (Admin Unit):** Upload CSV/XLS/XLSX dengan template bawaan, validasi per baris, ringkasan sukses/gagal.
* **Notification Center (in-app):** Tab kategori (All/Mutations/Updates/Onboarding/Security), mark read/unread, badge unread di navbar, dropdown recent.
* **Audit & Monitoring:** Audit Log, Activity Log, Active Sessions (Super Admin).
* **Ops Center:** Menu khusus Super Admin.
* **Onboarding Reguler:** pending_members dengan status share; admin dapat terima/tolak user reguler.
* **Dashboard Counters:** total unit, total anggota, mutations pending, onboarding pending, updates pending, anggota unit saya (admin_unit).
* **Kartu Digital Anggota:** Desain portrait, QR untuk verifikasi, tombol unduh PDF.
* **Template Upload:** Unduh template XLSX untuk impor anggota.

---

## 8. Indikator Keberhasilan (KPI)

* 95% data anggota aktif tervalidasi lengkap (atribut wajib + dokumen) dalam 3 bulan setelah peluncuran.
* Waktu rata-rata persetujuan mutasi <3 hari kerja.
* ≥80% anggota aktif mengunduh atau membuka kartu digital minimal sekali per tahun.
* Penurunan duplikasi data anggota (NIK/email) hingga <1% dari total populasi.
* Kepuasan admin (survey internal) >4 dari skala 5 terkait kemudahan input dan monitoring.

---

## 9. Roadmap Implementasi Bertahap

1. **Fase 1 - Pondasi (MVP):** Autentikasi SSO, master data organisasi, input anggota dasar, generate NRA, kartu digital sederhana.
2. **Fase 2 - Mutasi & Self-Service:** Workflow mutasi penuh, portal anggota untuk update data & unduh kartu, notifikasi multi-kanal, riwayat mutasi.
3. **Fase 3 - Pelaporan & Integrasi:** Dashboard statistik, laporan ekspor lanjutan, API/webhook untuk HRIS, peningkatan keamanan lanjutan (MFA darurat, alert anomaly).
4. **Fase 4 - Optimalisasi:** Otomasi audit compliance, analitik prediktif (tren mutasi), integrasi ke aplikasi mobile bila dibutuhkan.
