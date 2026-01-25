# Member Import Preview 500 Error - Brainstorm

## Goal
Resolve the 500 Internal Server Error yang terjadi saat user mengklik tombol "Preview" pada halaman member import (`/admin/members/import`). Error message di UI: "Preview gagal, periksa format file".

## Constraints
1. **Minimal Downtime** - Fix harus cepat dan tidak mengganggu operasi yang sedang berjalan
2. **Data Integrity** - Tidak boleh merusak data existing
3. **Backward Compatibility** - Flow import yang sudah ada harus tetap berfungsi

## Known Context
1. **Log Analysis**: Laravel log menunjukkan:
   ```
   SQLSTATE[HY000]: General error: 1 no such table: import_batches
   ```

2. **Code Flow**:
   - `MemberImportController@preview()` (line 70) → `$this->importService->preview()`
   - `MemberImportService@preview()` (line 269) → `ImportBatch::create()`
   - Model `ImportBatch` requires table `import_batches` yang **belum ada di database**

3. **Migration Files Exist** (belum dijalankan):
   - `database/migrations/2025_12_24_152000_create_import_batches_table.php`
   - `database/migrations/2025_12_24_152001_create_import_batch_errors_table.php`
   - `database/migrations/2025_12_24_154000_add_commit_columns_to_import_batches_table.php`

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Migration gagal karena dependencies | Medium | High | Run `php artisan migrate --pretend` dulu |
| Foreign key constraint error | Low | Medium | Cek `users` dan `organization_units` table exists |
| Konflik dengan migration lain | Low | Low | `php artisan migrate:status` |

## Options (3)

### Option 1: Run Pending Migrations ⭐ Recommended
**Effort**: Low (5 menit)
```bash
php artisan migrate
```

### Option 2: Run Specific Migrations Only
**Effort**: Low (5 menit)
```bash
php artisan migrate --path=database/migrations/2025_12_24_152000_create_import_batches_table.php
php artisan migrate --path=database/migrations/2025_12_24_152001_create_import_batch_errors_table.php
php artisan migrate --path=database/migrations/2025_12_24_154000_add_commit_columns_to_import_batches_table.php
```

### Option 3: Create Tables via Raw SQL
**Effort**: Medium | **Risk**: Medium | **Not Recommended**

## Recommendation

**Option 1: Run `php artisan migrate`**

Ini solusi paling simple. Migration files sudah lengkap dan tested.

## Acceptance Criteria

- [ ] Table `import_batches` exists in database
- [ ] Table `import_batch_errors` exists in database  
- [ ] User dapat upload file di `/admin/members/import`
- [ ] Klik "Preview" tidak menghasilkan 500 error
- [ ] Preview response mengembalikan batch data
