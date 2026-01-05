# Panduan Export CSV Reports System

Fitur ini memungkinkan pengguna untuk mengunduh laporan sistem dalam format CSV. Export dilakukan secara streaming untuk menangani volume data yang besar.

## Peran & Hak Akses

Akses fitur export dibatasi berdasarkan peran pengguna:

- **Super Admin & Admin Pusat**:
  - Dapat mengakses semua laporan secara global.
  - Dapat memfilter berdasarkan unit tertentu (`unit_id`).
- **Admin Unit & Bendahara**:
  - Hanya dapat mengakses laporan untuk unit mereka sendiri.
  - Parameter `unit_id` akan dipaksa ke unit pengguna jika mencoba mengakses unit lain.

## Endpoint & Penggunaan

URL Dasar: `/reports/export`

### Parameter Umum

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `type` | string (wajib) | Jenis laporan (lihat daftar di bawah). |
| `unit_id` | integer | Filter unit (opsional untuk Admin Pusat). |
| `q` | string | Pencarian umum (nama, KTA, deskripsi). |

### Jenis Laporan

#### 1. Members (`members`)
Laporan data anggota lengkap.
- **Filter Tambahan**:
  - `status`: Filter status anggota.
  - `date_start` / `date_end`: Filter tanggal bergabung (`join_date`).
  - `union_position_id`: Filter posisi serikat.
  - `include_documents`: `1` untuk menyertakan status dokumen (Ya/Tidak).

#### 2. Aspirations (`aspirations`)
Laporan aspirasi yang masuk.
- **Filter Tambahan**:
  - `status`: Status aspirasi (`open`, `closed`, dll).
  - `include_member`: `1` (default) sertakan data pelapor.
  - `include_user`: `1` sertakan data user related.

#### 3. Dues Per Period (`dues_per_period`)
Rincian iuran anggota per periode tertentu. Mencakup semua anggota aktif, baik yang sudah bayar maupun belum.
- **Filter Tambahan**:
  - `period`: Format `YYYY-MM` (contoh: `2025-01`). Default bulan ini.
  - `status`: `paid` atau `unpaid`.
  - `include_notes`: `1` untuk menyertakan catatan pembayaran (Audit Log mencatat ini).

#### 4. Dues Summary (`dues_summary`)
Ringkasan pembayaran iuran per unit.
- **Filter Tambahan**:
  - `period`: `YYYY-MM`.
  - `amount_default`: Nilai asumsi iuran untuk estimasi unpaid (default 30000).

#### 5. Audit Logs Iuran (`dues_audit`)
Riwayat aktivitas perubahan status iuran (mark paid/unpaid).
- **Filter Tambahan**:
  - `actor_user_id`: ID user yang melakukan aksi.
  - `date_start` / `date_end`: Filter rentang waktu aktivitas.

#### 6. Finance Ledgers (`finance_ledgers`)
Data buku besar keuangan (pemasukan/pengeluaran).
- **Filter Tambahan**:
  - `ledger_type`: `income` atau `expense`.
  - `status`: `approved`, `pending`, `rejected`.
  - `category_id`: Filter kategori keuangan.
  - `include_attachment_url`: `1` (default) sertakan link bukti transfer.

#### 7. Finance Monthly Summary (`finance_monthly_summary`)
Rekapitulasi keuangan bulanan per unit.
- **Filter Tambahan**:
  - `year`: Tahun laporan (default tahun ini).
  - `only_approved`: `1` (default) hanya transaksi yang disetujui.

## Monitoring Status Export

Endpoint: `/reports/export/status`

Mengembalikan status export terakhir pengguna dalam format JSON. Berguna untuk menampilkan indikator loading pada UI.

Contoh Response:
```json
{
    "status": "completed",
    "type": "members",
    "started_at": "2025-01-01T10:00:00+07:00",
    "finished_at": "2025-01-01T10:00:05+07:00",
    "row_count": 1500,
    "filename": "report_members_global_20250101.csv"
}
```

## Troubleshooting

- **503 Service Unavailable**: Fitur sedang dinonaktifkan (Feature Flag `finance` atau `reports` off).
- **403 Forbidden**: Anda tidak memiliki hak akses untuk laporan atau unit tersebut.
- **File Kosong**: Coba periksa rentang tanggal atau filter status Anda.
