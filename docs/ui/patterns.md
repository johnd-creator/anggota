# Pola UX Modul Anggota & Onboarding

## Form Multi-Step
- Langkah: Data Personal → Data Organisasi → Dokumen
- Indikator progres 3 langkah, tombol Next/Back, Save di langkah terakhir
- Validasi inline: NIK 16 digit, email valid, phone format internasional
- Upload: preview daftar file, hapus item, progress bar per unggahan

## Daftar Anggota
- Tabel responsif dengan sticky header
- Sorting: Nama, Status, Tgl Bergabung
- Filter: status dan multi unit dengan chips
- Pencarian ber-debounce 350ms
- Empty state dan state “tidak ada data” untuk filter
- Quick actions: Detail, Edit, Mutasi (stub)

## Detail Anggota
- Tab: Profil, Dokumen, Riwayat
- Profil: ringkasan (foto, NRA, unit, status badge)
- Dokumen: grid kartu dengan ikon, metadata, unduh
- Riwayat: timeline vertikal (tanggal, status/aksi, catatan)
- CTA header: Edit, Ubah Status, Ajukan Mutasi (stub)

## Onboarding Queue
- Tampilan kartu/kanban per pengajuan
- Panel slide-over (modal) untuk detail dan aksi
- Approve/Reject dengan modal konfirmasi dan catatan wajib
- Toast sukses/gagal (komponen siap)
- Filter status/unit dan pencarian

## Self Portal Reguler
- Alert status pengajuan (pending/rejected) di landing ItWorks
- Link ke halaman onboarding untuk melihat status

## Aksesibilitas
- Kontras warna mengikuti WCAG AA
- Focus ring di tombol dan input
- Responsif: grid collapse ke satu kolom, panel menjadi modal di mobile
## Dashboard Analitik
- Grid kartu untuk chart, panel filter sticky di sisi kanan/kiri
- Hierarki visual: headline, deskripsi, legend inline
- Tooltips bermakna pada elemen data, skeleton loading saat fetching
- State "tidak ada data" per chart
- Gunakan data props dari server; hindari dummy array
- Alarm dashboard: badge SLA breach, banner aksi cepat
## Laporan
- Header berisi filter, CTA export (CSV/Excel), ringkasan KPI
- Tabel data dengan sticky header/kolom, row expansion untuk detail
- Breadcrumbs, information banner ("data terupdate s/d …"), insight panel di kanan
- Empty state ramah: tampilkan pesan dan CTA ubah filter
- Export menghormati filter aktif; stream file
## Export
- Modal konfirmasi menampilkan ringkasan filter dan format file
- Toast/status progress "sedang menyiapkan laporan…" dan notifikasi saat siap
## Notification Center
- Tabs: All, Mutations, Updates, Onboarding, Security
- Item: ikon, judul, waktu relatif, badge status (baru/dibaca), CTA
- Filter tanggal dan pencarian; pagination server-side
- Bulk actions: "Tandai semua sudah dibaca", empty state ramah, preferensi toggle per jenis
- Pill counter pada icon bell di header
- Quick view dropdown di header menampilkan 5 notifikasi terbaru
## Mutasi Workflow
- Wizard: Pilih Anggota → Unit Tujuan → Dokumen → Review
- Persetujuan: layout dua kolom (detail + detail pengajuan), timeline SLA, catatan, preview dokumen
- Tombol Approve/Reject memunculkan modal dengan textarea komentar, toast & notifikasi in-app
 - Workflow dapat dimulai dari Daftar Anggota: tombol "Mutasi" pada baris anggota akan mengarahkan ke `/admin/mutations?member_id=<ID>`; dropdown anggota terisi otomatis dan langsung ke langkah Unit Tujuan
## Alert / SLA
- Komponen alert di dashboard dan modul mutasi untuk request melewati SLA (amber/red)
- Link aksi "Tinjau sekarang"
## Portal Anggota
- Header: avatar, nama, status badge, unit
- Progress bar kelengkapan data + CTA "Lengkapi Profil"
- Panel slide-over untuk edit alamat/telepon/foto, status pending
- Change History ringkas di panel
- Timeline event dengan ikon/warna per jenis event; filter tahun & pencarian
- Kartu digital: background brand, QR, status chip, Unduh PDF, Bagikan link
 - Halaman verifikasi publik: summary card, status indicator, timestamp scan, CTA bantuan

## Settings Hub
- Sidebar tabs: Profil, Notifikasi, Keamanan, Integrasi, Privasi, Bahasa
- Breadcrumbs dan tombol "Simpan Semua" di header
- Card per tab dengan header, deskripsi, dan aksi
- Mobile: jadikan tabs sebagai tombol/accordion
- Preferensi Notifikasi: toggle channel (email, in-app, WA) + opsi digest harian
- Tampilkan timestamp terakhir diubah pada preferensi

## Privacy Dialogs
- Modal konfirmasi untuk Export dan Hapus Data
- Copy ramah, tampilkan SLA penanganan
- Tautan ke kebijakan privasi

## API Token UI
- Tabel: label, status, kadaluarsa, last used, aksi
- Tombol Regenerate/Deactivate dengan modal konfirmasi
- Banner peringatan menjaga kerahasiaan token

## Viewer Dokumen Ops
- Sidebar Daftar Isi (heading markdown) + pencarian
- Badge "Updated" bila dokumen baru diubah
- Konten markdown ditampilkan dengan whitespace-pre-wrap
