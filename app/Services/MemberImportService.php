<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MemberImportService
{
    /**
     * Valid status values for members.
     */
    private const VALID_STATUSES = ['aktif', 'cuti', 'suspended', 'resign', 'pensiun'];

    /**
     * Valid employment types.
     */
    private const VALID_EMPLOYMENT_TYPES = ['organik', 'tkwt'];

    /**
     * Valid gender values.
     */
    private const VALID_GENDERS = ['L', 'P'];

    /**
     * Create structured field-level error.
     *
     * @param  string  $field  Nama field yang error
     * @param  string  $severity  'critical' atau 'warning'
     * @param  string|null  $currentValue  Nilai saat ini yang invalid
     * @param  string  $message  Pesan error dalam Bahasa Indonesia
     * @param  string  $expectedFormat  Format/contoh yang benar
     * @return array Structured error object
     */
    private function createFieldError(
        string $field,
        string $severity,
        ?string $currentValue,
        string $message,
        string $expectedFormat
    ): array {
        return [
            'field' => $field,
            'severity' => $severity,
            'current_value' => $currentValue,
            'message' => $message,
            'expected_format' => $expectedFormat,
        ];
    }

    /**
     * Normalize row keys/shape into the canonical schema used by this service.
     * Supports legacy templates that use `personal_*` and `company_email`.
     */
    private function normalizeRow(array $row): array
    {
        $normalized = $row;

        // Name
        if (empty($normalized['full_name']) && ! empty($normalized['personal_full_name'])) {
            $normalized['full_name'] = $normalized['personal_full_name'];
        }

        // Primary member email in this app historically corresponds to personal email (if available).
        // Fall back to company_email when personal email is not provided.
        if (empty($normalized['email'])) {
            if (! empty($normalized['personal_email'])) {
                $normalized['email'] = $normalized['personal_email'];
            } elseif (! empty($normalized['company_email'])) {
                $normalized['email'] = $normalized['company_email'];
            }
        }

        // Phone / NIP / dates
        if (empty($normalized['phone']) && ! empty($normalized['personal_phone'])) {
            $normalized['phone'] = $normalized['personal_phone'];
        }
        if (empty($normalized['nip']) && ! empty($normalized['personal_nip'])) {
            $normalized['nip'] = $normalized['personal_nip'];
        }
        if (empty($normalized['birth_place']) && ! empty($normalized['personal_birth_place'])) {
            $normalized['birth_place'] = $normalized['personal_birth_place'];
        }
        if (empty($normalized['birth_date']) && ! empty($normalized['personal_birth_date'])) {
            $normalized['birth_date'] = $normalized['personal_birth_date'];
        }
        if (empty($normalized['gender']) && ! empty($normalized['personal_gender'])) {
            $normalized['gender'] = $normalized['personal_gender'];
        }

        return $normalized;
    }

    /**
     * Validate and return error list for rows (includes internal duplicate detection).
     *
     * @return array<int,array{row_number:int,errors:array<int,array>}>
     */
    public function collectValidationErrors(array $rows, ?int $unitId): array
    {
        $errors = [];
        $seenNip = [];
        $seenEmail = [];
        $seenNra = [];

        foreach ($rows as $index => $rawRow) {
            $row = $this->normalizeRow($rawRow);
            $rowNumber = $index + 2; // header is row 1

            $rowErrors = $this->validateRow($row, $rowNumber, $unitId);

            $nip = isset($row['nip']) ? strtoupper(trim((string) $row['nip'])) : null;
            $nra = isset($row['nra']) ? strtoupper(trim((string) $row['nra'])) : null;
            $email = isset($row['email']) ? strtolower(trim((string) $row['email'])) : null;

            // Internal duplicates (file-level) - Critical severity
            if ($nip) {
                if (isset($seenNip[$nip])) {
                    $rowErrors[] = $this->createFieldError(
                        'nip',
                        'critical',
                        $nip,
                        "NIP '{$nip}' duplikat dengan baris {$seenNip[$nip]}",
                        'NIP harus unik dalam file import'
                    );
                } else {
                    $seenNip[$nip] = $rowNumber;
                }
            }

            if ($nra) {
                if (isset($seenNra[$nra])) {
                    $rowErrors[] = $this->createFieldError(
                        'nra',
                        'critical',
                        $nra,
                        "NRA '{$nra}' duplikat dengan baris {$seenNra[$nra]}",
                        'NRA harus unik dalam file import'
                    );
                } else {
                    $seenNra[$nra] = $rowNumber;
                }
            }

            if ($email) {
                if (isset($seenEmail[$email])) {
                    $rowErrors[] = $this->createFieldError(
                        'email',
                        'critical',
                        $this->maskEmail($email),
                        "Email duplikat dengan baris {$seenEmail[$email]}",
                        'Email harus unik dalam file import'
                    );
                } else {
                    $seenEmail[$email] = $rowNumber;
                }
            }

            // DB conflicts: prevent cross-unit IDOR and avoid violating unique constraints.
            // Determine effective unit for this row.
            $effectiveUnitId = $unitId;
            if ($effectiveUnitId === null) {
                $effectiveUnitId = isset($row['organization_unit_id']) ? (int) trim((string) $row['organization_unit_id']) : null;
            }
            if ($effectiveUnitId) {
                $rowErrors = array_merge($rowErrors, $this->dbConflictErrors($row, $effectiveUnitId));
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'errors' => $rowErrors,
                ];
            }
        }

        return $errors;
    }

    /**
     * Detect conflicts with existing DB records that would violate constraints or enable cross-unit IDOR.
     *
     * @return array<int,array> Array of structured field errors
     */
    private function dbConflictErrors(array $row, int $effectiveUnitId): array
    {
        $errors = [];

        $nra = isset($row['nra']) ? strtoupper(trim((string) $row['nra'])) : null;
        $email = isset($row['email']) ? strtolower(trim((string) $row['email'])) : null;
        $nip = isset($row['nip']) ? strtoupper(trim((string) $row['nip'])) : null;
        $ktaNumber = isset($row['kta_number']) ? strtoupper(trim((string) $row['kta_number'])) : null;

        if ($nra) {
            $existing = Member::where('nra', $nra)->first(['id', 'organization_unit_id']);
            if ($existing && (int) $existing->organization_unit_id !== (int) $effectiveUnitId) {
                $errors[] = $this->createFieldError(
                    'nra',
                    'critical',
                    $nra,
                    "NRA '{$nra}' sudah digunakan oleh unit lain",
                    'Gunakan NRA yang unik atau import ke unit yang sesuai'
                );
            }
        }

        if ($email) {
            $existing = Member::where('email', $email)->first(['id', 'organization_unit_id']);
            if ($existing && (int) $existing->organization_unit_id !== (int) $effectiveUnitId) {
                $errors[] = $this->createFieldError(
                    'email',
                    'critical',
                    $this->maskEmail($email),
                    'Email sudah digunakan oleh unit lain',
                    'Gunakan email yang berbeda atau import ke unit yang sesuai'
                );
            }
        }

        if ($nip) {
            $existing = Member::where('nip', $nip)->first(['id', 'organization_unit_id']);
            if ($existing && (int) $existing->organization_unit_id !== (int) $effectiveUnitId) {
                $errors[] = $this->createFieldError(
                    'nip',
                    'critical',
                    $nip,
                    "NIP '{$nip}' sudah digunakan oleh unit lain",
                    'Gunakan NIP yang unik atau import ke unit yang sesuai'
                );
            }
        }

        if ($ktaNumber) {
            $existing = Member::where('kta_number', $ktaNumber)->first(['id', 'organization_unit_id']);
            if ($existing && (int) $existing->organization_unit_id !== (int) $effectiveUnitId) {
                $errors[] = $this->createFieldError(
                    'kta_number',
                    'critical',
                    $ktaNumber,
                    "KTA '{$ktaNumber}' sudah digunakan oleh unit lain",
                    'Gunakan nomor KTA yang unik atau import ke unit yang sesuai'
                );
            }
        }

        return $errors;
    }

    /**
     * Preview an import file without committing.
     * Returns batch with validation results.
     */
    public function preview(UploadedFile $file, ?int $unitId, User $actor): ImportBatch
    {
        // Store file securely (not public)
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $storedPath = $file->storeAs('imports', $filename, 'local');
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Create batch record
        $batch = ImportBatch::create([
            'actor_user_id' => $actor->id,
            'organization_unit_id' => $unitId,
            'status' => 'draft',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => $fileHash,
        ]);

        // Parse file from storage (after it's stored)
        $rows = $this->parseStoredFile($storedPath);

        if (empty($rows)) {
            $batch->markPreviewed(0, 0, 0);

            return $batch;
        }

        // Validate all rows (including internal duplicate detection).
        // Note: existing members in DB are treated as updates (not preview errors).
        $allErrors = $this->collectValidationErrors($rows, $unitId);
        $invalidRows = count($allErrors);
        $validRows = max(0, count($rows) - $invalidRows);

        // Store errors (max 100 to avoid bloat)
        foreach (array_slice($allErrors, 0, 100) as $error) {
            ImportBatchError::create([
                'import_batch_id' => $batch->id,
                'row_number' => $error['row_number'],
                'errors_json' => $error['errors'],
            ]);
        }

        // Mark as previewed
        $batch->markPreviewed(count($rows), $validRows, $invalidRows);

        return $batch;
    }

    /**
     * Validate a single row.
     * Returns array of structured field-level errors (empty if valid).
     *
     * Each error is an array with: field, severity, current_value, message, expected_format.
     * Severity: 'critical' for required fields, 'warning' for optional field format issues.
     *
     * SECURITY: For global batches (unitId null), organization_unit_id is required per row.
     */
    public function validateRow(array $row, int $rowNumber, ?int $unitId): array
    {
        $errors = [];

        // Normalize field values
        $fullName = isset($row['full_name']) ? trim($row['full_name']) : '';
        $status = isset($row['status']) ? strtolower(trim($row['status'])) : '';
        $email = isset($row['email']) ? strtolower(trim($row['email'])) : '';
        $phone = isset($row['phone']) ? trim($row['phone']) : '';
        $nra = isset($row['nra']) ? strtoupper(trim($row['nra'])) : '';
        $nip = isset($row['nip']) ? trim($row['nip']) : '';
        $joinDate = isset($row['join_date']) ? trim($row['join_date']) : '';
        $birthDate = isset($row['birth_date']) ? trim($row['birth_date']) : '';
        $gender = isset($row['gender']) ? strtoupper(trim((string) $row['gender'])) : '';
        $orgUnitId = isset($row['organization_unit_id']) ? trim($row['organization_unit_id']) : '';
        $employmentType = isset($row['employment_type']) ? strtolower(trim((string) $row['employment_type'])) : '';
        $unionPosCode = isset($row['union_position_code']) ? trim((string) $row['union_position_code']) : '';

        // ========== REQUIRED FIELDS (Critical Severity) ==========

        // full_name: Wajib diisi, min 2 karakter, max 255 karakter
        if (empty($fullName)) {
            $errors[] = $this->createFieldError(
                'full_name',
                'critical',
                null,
                'Nama lengkap wajib diisi',
                'Minimal 2 karakter, contoh: Budi Santoso'
            );
        } elseif (mb_strlen($fullName) < 2) {
            $errors[] = $this->createFieldError(
                'full_name',
                'critical',
                $fullName,
                'Nama lengkap terlalu pendek (minimal 2 karakter)',
                'Contoh: Budi Santoso'
            );
        } elseif (mb_strlen($fullName) > 255) {
            $errors[] = $this->createFieldError(
                'full_name',
                'critical',
                mb_substr($fullName, 0, 50).'...',
                'Nama lengkap terlalu panjang (maksimal 255 karakter)',
                'Maksimal 255 karakter'
            );
        }

        // status: Wajib diisi, salah satu dari: aktif, cuti, suspended, resign, pensiun
        if (empty($status)) {
            $errors[] = $this->createFieldError(
                'status',
                'critical',
                null,
                'Status keanggotaan wajib diisi',
                'Gunakan salah satu: '.implode(', ', self::VALID_STATUSES)
            );
        } elseif (! in_array($status, self::VALID_STATUSES, true)) {
            $errors[] = $this->createFieldError(
                'status',
                'critical',
                $status,
                "Status '{$status}' tidak valid",
                'Gunakan salah satu: '.implode(', ', self::VALID_STATUSES)
            );
        }

        // ========== OPTIONAL FIELDS (Warning Severity if format invalid) ==========

        // email: Format email valid (hanya validasi jika diisi)
        if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->createFieldError(
                'email',
                'warning',
                $email,
                'Format email tidak valid',
                'Contoh: nama@domain.com'
            );
        }

        // phone: Format telepon valid (digit, spasi, +, -, (, ))
        if (! empty($phone) && ! preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            $errors[] = $this->createFieldError(
                'phone',
                'warning',
                $phone,
                'Format nomor telepon tidak valid',
                'Contoh: +62 812-3456-7890 atau 081234567890'
            );
        }

        // birth_date: Format tanggal valid
        if (! empty($birthDate) && ! $this->parseDate($birthDate)) {
            $errors[] = $this->createFieldError(
                'birth_date',
                'warning',
                $birthDate,
                'Format tanggal lahir tidak valid',
                'Gunakan format: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, atau YYYY/MM/DD'
            );
        }

        // gender: L atau P
        if (! empty($gender) && ! in_array($gender, self::VALID_GENDERS, true)) {
            $errors[] = $this->createFieldError(
                'gender',
                'warning',
                $gender,
                "Jenis kelamin '{$gender}' tidak valid",
                'Gunakan: L (Laki-laki) atau P (Perempuan)'
            );
        }

        // join_date: Format tanggal valid
        if (! empty($joinDate) && ! $this->parseDate($joinDate)) {
            $errors[] = $this->createFieldError(
                'join_date',
                'warning',
                $joinDate,
                'Format tanggal bergabung tidak valid',
                'Gunakan format: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, atau YYYY/MM/DD'
            );
        }

        // employment_type: organik atau tkwt
        if (! empty($employmentType) && ! in_array($employmentType, self::VALID_EMPLOYMENT_TYPES, true)) {
            $errors[] = $this->createFieldError(
                'employment_type',
                'warning',
                $employmentType,
                "Tipe kepegawaian '{$employmentType}' tidak valid",
                'Gunakan: organik atau tkwt'
            );
        }

        // nip: Format alphanumeric (was numeric only)
        if (! empty($nip) && ! preg_match('/^[A-Za-z0-9]+$/', $nip)) {
            $errors[] = $this->createFieldError(
                'nip',
                'warning',
                $nip,
                'Format NIP tidak valid (hanya boleh huruf dan angka)',
                'Contoh: 19901234567890B'
            );
        }

        // nra: Format UNIT-YEAR-SEQUENCE (pattern: ^[A-Z]-\d{4}-\d{3,4}$)
        if (! empty($nra) && ! preg_match('/^[A-Z]+-\d{4}-\d{3,4}$/', $nra)) {
            $errors[] = $this->createFieldError(
                'nra',
                'warning',
                $nra,
                'Format NRA tidak valid',
                'Contoh: A-2024-001 atau UP-2024-0012'
            );
        }

        // union_position_code: Validasi exist di database
        if (! empty($unionPosCode)) {
            $posExists = \App\Models\UnionPosition::where('code', $unionPosCode)
                ->orWhere('name', $unionPosCode)
                ->exists();
            if (! $posExists) {
                // Try case-insensitive search or suggest checking Master Data
                $errors[] = $this->createFieldError(
                    'union_position_code',
                    'warning',
                    $unionPosCode,
                    "Jabatan serikat '{$unionPosCode}' tidak ditemukan",
                    'Gunakan kode atau nama jabatan yang terdaftar di Master Data > Jabatan Serikat'
                );
            }
        }

        // ========== CONDITIONAL REQUIRED: organization_unit_id (Critical for global batch) ==========
        // If batch is global (unitId is null), then per-row unit ID is required.
        // If batch is unit-scoped (unitId provided), then per-row unit ID is optional (will fallback to batch unit).
        if ($unitId === null) {
            // Global batch: organization_unit_id is REQUIRED per row
            if (empty($orgUnitId)) {
                $errors[] = $this->createFieldError(
                    'organization_unit_id',
                    'critical',
                    null,
                    'Unit organisasi wajib diisi untuk import global',
                    'Isi dengan ID unit organisasi yang valid'
                );
            } elseif (! OrganizationUnit::where('id', $orgUnitId)->exists()) {
                $errors[] = $this->createFieldError(
                    'organization_unit_id',
                    'critical',
                    $orgUnitId,
                    "Unit organisasi dengan ID '{$orgUnitId}' tidak ditemukan",
                    'Gunakan ID unit organisasi yang terdaftar di sistem'
                );
            }
        } else {
            // Unit-scoped batch: Check if row tries to inject different unit
            if (! empty($orgUnitId) && (int) $orgUnitId !== (int) $unitId) {
                $errors[] = $this->createFieldError(
                    'organization_unit_id',
                    'critical',
                    $orgUnitId,
                    "ID Unit tidak sesuai dengan wewenang Anda (Unit {$unitId})",
                    'Kosongkan kolom ini atau isi dengan ID unit Anda sendiri'
                );
            }
        }

        return $errors;
    }

    /**
     * Parse file to rows array.
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->parseCsv($file);
        }

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseSpreadsheet($file->getPathname());
        }

        return [];
    }

    /**
     * Parse CSV file.
     */
    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [];
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [];
        }

        // Normalize header (trim, lowercase, remove BOM)
        $header = array_map(function ($h) {
            $h = trim($h);
            // Remove BOM
            if (str_starts_with($h, "\xEF\xBB\xBF")) {
                $h = substr($h, 3);
            }

            return strtolower($h);
        }, $header);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (! trim(implode('', $row))) {
                continue;
            }

            // Map to associative array
            $mapped = [];
            foreach ($header as $idx => $key) {
                $mapped[$key] = $row[$idx] ?? null;
            }
            $rows[] = $mapped;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse date string to Y-m-d format or null.
     */
    private function parseDate(string $dateStr): ?string
    {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $dateStr);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Mask email for error messages (privacy).
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = substr($local, 0, 2).'***';

        return $maskedLocal.'@'.$domain;
    }

    /**
     * Commit/execute an import batch.
     * Returns array with created_count, updated_count, error_count.
     */
    public function commit(ImportBatch $batch): array
    {
        set_time_limit(300);

        $result = [
            'created_count' => 0,
            'updated_count' => 0,
            'error_count' => 0,
        ];

        // Parse stored file
        $rows = $this->parseStoredFile($batch->stored_path);

        if (empty($rows)) {
            return $result;
        }

        $unitId = $batch->organization_unit_id;
        $invalidRowNumbers = [];
        foreach ($this->collectValidationErrors($rows, $unitId) as $e) {
            $invalidRowNumbers[(int) $e['row_number']] = true;
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $row = $this->normalizeRow($row);

            // Skip rows with validation errors (including internal duplicates)
            if (isset($invalidRowNumbers[$rowNumber])) {
                $result['error_count']++;

                continue;
            }

            // Upsert member
            $upsert = $this->upsertMember($row, $unitId);
            $action = $upsert['action'] ?? 'error';
            $member = $upsert['member'] ?? null;

            if ($action === 'created') {
                $result['created_count']++;
            } elseif ($action === 'updated') {
                $result['updated_count']++;
            } else {
                $result['error_count']++;
            }

            // Best-effort user linkage for legacy templates (company_email/personal_email).
            // Does not override an existing user's unit if it differs (prevents hijack).
            if ($member) {
                $this->syncUserForMember($member, $row, $unitId);
            }
        }

        return $result;
    }

    /**
     * Parse file from storage path.
     */
    public function parseStoredFile(string $storedPath): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($storedPath)) {
            return [];
        }
        $fullPath = $disk->path($storedPath);

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parseCsvFromPath($fullPath);
        }

        if (in_array($extension, ['xlsx', 'xls'])) {
            $rows = $this->parseXlsxWithSpout($fullPath);

            if (empty($rows)) {
                \Log::warning('Spout returned empty rows, trying PhpSpreadsheet fallback', [
                    'path' => $fullPath,
                ]);
                $rows = $this->parseSpreadsheet($fullPath);
            }

            return $rows;
        }

        return [];
    }

    /**
     * Parse XLSX/XLS file using PhpSpreadsheet (fallback).
     */
    private function parseSpreadsheet(string $path): array
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, true);

            if (empty($data)) {
                return [];
            }

            // First row is header
            $headerRow = array_shift($data);

            // Normalize header (trim, lowercase)
            $header = [];
            foreach ($headerRow as $key => $value) {
                $normalized = strtolower(trim((string) $value));
                if (! empty($normalized)) {
                    $header[$key] = $normalized;
                }
            }

            if (empty($header)) {
                return [];
            }

            // Map data rows to associative arrays
            $rows = [];
            foreach ($data as $dataRow) {
                // Skip empty rows
                $nonEmpty = array_filter($dataRow, fn ($v) => ! empty(trim((string) $v)));
                if (empty($nonEmpty)) {
                    continue;
                }

                $mapped = [];
                foreach ($header as $colKey => $colName) {
                    $mapped[$colName] = isset($dataRow[$colKey]) ? trim((string) $dataRow[$colKey]) : null;
                }
                $rows[] = $mapped;
            }

            return $rows;
        } catch (\Exception $e) {
            \Log::error('PhpSpreadsheet parse error: '.$e->getMessage(), [
                'path' => $path,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Parse XLSX/XLS file using Spout library (memory-efficient streaming).
     */
    private function parseXlsxWithSpout(string $path): array
    {
        try {
            $reader = \Box\Spout\Reader\Common\Creator\ReaderEntityFactory::createReaderFromFile($path);
            $reader->open($path);

            $rows = [];
            $header = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                if (! $sheet->isActive()) {
                    continue;
                }

                $rowIndex = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $rowData = [];
                    foreach ($cells as $cell) {
                        $value = $cell->getValue();
                        if ($value instanceof \DateTime) {
                            $value = $value->format('Y-m-d');
                        }
                        $rowData[] = $value;
                    }

                    if ($rowIndex === 0) {
                        // First row is header
                        $header = [];
                        foreach ($rowData as $colName) {
                            $normalized = strtolower(trim((string) $colName));
                            if (! empty($normalized)) {
                                $header[] = $normalized;
                            } else {
                                // Keep empty slot to preserve column mapping
                                $header[] = null;
                            }
                        }
                        $rowIndex++;

                        continue;
                    }

                    // Skip empty rows
                    $nonEmpty = array_filter($rowData, fn ($v) => ! empty(trim((string) $v)));
                    if (empty($nonEmpty)) {
                        $rowIndex++;

                        continue;
                    }

                    // Map to header keys
                    $mapped = [];
                    foreach ($header as $colIndex => $colName) {
                        if ($colName !== null && isset($rowData[$colIndex])) {
                            $mapped[$colName] = trim((string) $rowData[$colIndex]);
                        }
                    }
                    $rows[] = $mapped;
                    $rowIndex++;
                }

                break;
            }

            $reader->close();

            return $rows;
        } catch (\Exception $e) {
            \Log::error('Spout parse error: '.$e->getMessage(), [
                'path' => $path,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Parse CSV from absolute path.
     */
    private function parseCsvFromPath(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [];
        }

        // Normalize header
        $header = array_map(function ($h) {
            $h = trim($h);
            if (str_starts_with($h, "\xEF\xBB\xBF")) {
                $h = substr($h, 3);
            }

            return strtolower($h);
        }, $header);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (! trim(implode('', $row))) {
                continue;
            }

            $mapped = [];
            foreach ($header as $idx => $key) {
                $mapped[$key] = $row[$idx] ?? null;
            }
            $rows[] = $mapped;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Upsert a member from row data.
     * Returns 'created', 'updated', or 'error'.
     *
     * SECURITY: Always scope existing member lookup by unit to prevent cross-unit IDOR.
     */
    public function upsertMember(array $row, ?int $unitId): array
    {
        $nra = isset($row['nra']) ? strtoupper(trim($row['nra'])) : null;
        $email = isset($row['email']) ? strtolower(trim($row['email'])) : null;
        $nip = isset($row['nip']) ? strtoupper(trim($row['nip'])) : null;
        $fullName = isset($row['full_name']) ? trim($row['full_name']) : '';
        $status = isset($row['status']) ? strtolower(trim($row['status'])) : 'aktif';
        $phone = isset($row['phone']) ? trim($row['phone']) : null;
        $ktaNumber = isset($row['kta_number']) ? strtoupper(trim($row['kta_number'])) : null;
        $nipRaw = isset($row['nip']) ? trim($row['nip']) : null;
        $joinDate = isset($row['join_date']) ? $this->parseDate(trim($row['join_date'])) : null;
        if (! $joinDate) {
            $joinDate = now()->toDateString();
        }
        $birthPlace = isset($row['birth_place']) ? trim((string) $row['birth_place']) : null;
        $birthDate = isset($row['birth_date']) ? $this->parseDate(trim((string) $row['birth_date'])) : null;
        $gender = isset($row['gender']) ? strtoupper(trim((string) $row['gender'])) : null;
        $address = isset($row['address']) ? trim((string) $row['address']) : null;
        $employmentType = isset($row['employment_type']) ? strtolower(trim((string) $row['employment_type'])) : null;
        if (! $employmentType || ! in_array($employmentType, ['organik', 'tkwt'], true)) {
            $employmentType = 'organik';
        }
        $companyJoinDate = isset($row['company_join_date']) ? $this->parseDate(trim((string) $row['company_join_date'])) : null;
        $jobTitle = isset($row['job_title']) ? trim((string) $row['job_title']) : null;
        $notes = isset($row['notes']) ? trim((string) $row['notes']) : null;
        $unionPosCode = isset($row['union_position_code']) ? trim((string) $row['union_position_code']) : null;
        $unionPositionId = null;
        if ($unionPosCode) {
            $pos = \App\Models\UnionPosition::where('code', $unionPosCode)->orWhere('name', $unionPosCode)->first();
            $unionPositionId = $pos?->id;
        }

        // For global batches, determine unit from row
        $effectiveUnitId = $unitId;
        if ($effectiveUnitId === null) {
            $rowUnitId = isset($row['organization_unit_id']) ? (int) trim($row['organization_unit_id']) : null;
            if (! $rowUnitId) {
                return ['action' => 'error', 'member' => null]; // Global batch requires unit per row
            }
            $effectiveUnitId = $rowUnitId;
        }

        // Ensure status is within allowed enum (fallback to aktif)
        if (! in_array($status, self::VALID_STATUSES, true)) {
            $status = 'aktif';
        }

        $joinYear = (int) substr((string) $joinDate, 0, 4);

        // SECURITY: Find existing member by nra (primary) or email (fallback)
        // ALWAYS scope by unit to prevent cross-unit updates (IDOR prevention)
        $existing = null;
        if ($nip) {
            $existing = Member::where('organization_unit_id', $effectiveUnitId)
                ->where('nip', $nip)
                ->first();
        }
        if (! $existing && $nra) {
            $existing = Member::where('organization_unit_id', $effectiveUnitId)
                ->where('nra', $nra)
                ->first();
        }
        if (! $existing && $email) {
            $existing = Member::where('organization_unit_id', $effectiveUnitId)
                ->where('email', $email)
                ->first();
        }

        try {
            if ($existing) {
                // Update whitelisted fields only
                $existing->full_name = $fullName;
                $existing->status = $status;
                if ($phone) {
                    $existing->phone = $phone;
                }
                if ($ktaNumber) {
                    $existing->kta_number = $ktaNumber;
                }
                if ($nipRaw) {
                    $existing->nip = $nipRaw;
                }
                if ($joinDate) {
                    $existing->join_date = $joinDate;
                }
                if ($birthPlace) {
                    $existing->birth_place = $birthPlace;
                }
                if ($birthDate) {
                    $existing->birth_date = $birthDate;
                }
                if ($gender && in_array($gender, ['L', 'P'], true)) {
                    $existing->gender = $gender;
                }
                if ($address) {
                    $existing->address = $address;
                }
                if ($employmentType) {
                    $existing->employment_type = $employmentType;
                }
                if ($companyJoinDate) {
                    $existing->company_join_date = $companyJoinDate;
                }
                if ($jobTitle) {
                    $existing->job_title = $jobTitle;
                }
                if ($notes) {
                    $existing->notes = $notes;
                }
                if ($unionPositionId) {
                    $existing->union_position_id = $unionPositionId;
                }
                if ($email && ! $existing->email) {
                    $existing->email = $email;
                }
                $existing->save();

                return ['action' => 'updated', 'member' => $existing];
            } else {
                // If NRA is missing, generate it (and a sequence number) from the unit and join year.
                $sequenceNumber = null;
                if (! $nra) {
                    $gen = \App\Services\NraGenerator::generate($effectiveUnitId, $joinYear);
                    $nra = $gen['nra'];
                    $sequenceNumber = $gen['sequence'];
                }
                if ($sequenceNumber === null) {
                    $sequenceNumber = (int) (Member::where('organization_unit_id', $effectiveUnitId)
                        ->where('join_year', $joinYear)
                        ->max('sequence_number') ?? 0) + 1;
                }

                // FIX: Generate KTA number if missing (KTA format: 010-SPPIPS-24001 vs NRA: 010-24-001)
                // This ensures consistency with manual/google login creation
                if (! $ktaNumber) {
                    $ktaGen = \App\Services\KtaGenerator::generate($effectiveUnitId, $joinYear);
                    $ktaNumber = $ktaGen['kta'];
                    // Note: KtaGenerator also generates a sequence, but we already have one from NraGenerator or fallback.
                    // Ideally both should use the same sequence, but for now we prioritize having a KTA valid format.
                    // If sequence is critical for KTA to match NRA exactly, we should use the same sequence.
                    // KtaGenerator uses lockForUpdate, so it's safe.
                    // Let's use the KTA's sequence for consistency if we just generated it.
                    if (isset($ktaGen['sequence'])) {
                        $sequenceNumber = $ktaGen['sequence'];
                        // Re-generate NRA to match KTA sequence if needed, OR just update the sequence used.
                        // Ideally NRA and KTA share the same sequence number.
                        // Let's preserve the NRA prefix but update sequence to match KTA if possible.
                        // However, NRA format is simpler. Let's just trust KTA generator's sequence
                        // and implicitely update NRA if we hadn't already fixed it?
                        // Actually, let's keep it simple: Use generated KTA.
                        // If we didn't have NRA, regenerate NRA with new sequence?
                        if (! $nra) {
                            $yearTwoDigit = (int) substr((string) $joinYear, -2);
                            $nra = sprintf('%03d-%02d-%03d', $effectiveUnitId, $yearTwoDigit, $sequenceNumber);
                        }
                    }
                }

                // Some environments enforce NOT NULL for email; generate a placeholder if missing.
                $finalEmail = $email;
                if (! $finalEmail) {
                    $finalEmail = 'import+'.Str::uuid().'@invalid.local';
                }

                // Create new member with unit scope
                $created = Member::create([
                    'full_name' => $fullName,
                    'nra' => $nra,
                    'email' => $finalEmail,
                    'phone' => $phone,
                    'kta_number' => $ktaNumber,
                    'nip' => $nipRaw,
                    'status' => $status,
                    'join_date' => $joinDate,
                    'join_year' => $joinYear,
                    'sequence_number' => $sequenceNumber,
                    'birth_place' => $birthPlace,
                    'birth_date' => $birthDate,
                    'gender' => ($gender && in_array($gender, ['L', 'P'], true)) ? $gender : null,
                    'address' => $address,
                    'employment_type' => $employmentType,
                    'company_join_date' => $companyJoinDate,
                    'job_title' => $jobTitle,
                    'notes' => $notes,
                    'union_position_id' => $unionPositionId,
                    'organization_unit_id' => $effectiveUnitId,
                ]);

                return ['action' => 'created', 'member' => $created];
            }
        } catch (\Exception $e) {
            return ['action' => 'error', 'member' => null];
        }
    }

    /**
     * Create/link a User for the imported member when email fields are present.
     *
     * If a user exists with a different unit (and set), do not override unit linkage.
     */
    private function syncUserForMember(Member $member, array $row, ?int $batchUnitId): void
    {
        $effectiveUnitId = $batchUnitId;
        if ($effectiveUnitId === null) {
            $rowUnitId = isset($row['organization_unit_id']) ? (int) trim((string) $row['organization_unit_id']) : null;
            if ($rowUnitId) {
                $effectiveUnitId = $rowUnitId;
            }
        }

        $companyEmail = isset($row['company_email']) ? strtolower(trim((string) $row['company_email'])) : '';
        $personalEmail = isset($row['personal_email']) ? strtolower(trim((string) $row['personal_email'])) : '';
        $email = isset($row['email']) ? strtolower(trim((string) $row['email'])) : '';

        $targetEmail = $companyEmail ?: ($personalEmail ?: $email);
        if (! $targetEmail) {
            return;
        }

        $roleId = \App\Models\Role::where('name', 'anggota')->value('id');
        if (! $roleId) {
            return;
        }

        $user = User::where('email', $targetEmail)->first();
        if (! $user) {
            $password = Str::random(16);
            $hashedPassword = \Illuminate\Support\Facades\Hash::make($password);

            $user = User::create([
                'name' => $member->full_name,
                'email' => $targetEmail,
                'company_email' => $companyEmail ?: null,
                'password' => $hashedPassword,
                'role_id' => $roleId,
                'member_id' => $member->id,
                'organization_unit_id' => $effectiveUnitId,
            ]);
        } else {
            // Prevent cross-unit hijack: don't override a different existing unit.
            if ($effectiveUnitId && $user->organization_unit_id && (int) $user->organization_unit_id !== (int) $effectiveUnitId) {
                return;
            }
 
            // FIX: Selalu update member_id
            // Mencegah user yang di-import tidak punya member_id
            $user->member_id = $member->id;
            
            if ($effectiveUnitId && ! $user->organization_unit_id) {
                $user->organization_unit_id = $effectiveUnitId;
            }
            if ($companyEmail && $user->company_email !== $companyEmail) {
                $user->company_email = $companyEmail;
            }
            $user->save();
        }

        if ($user && $member->user_id !== $user->id) {
            $member->user_id = $user->id;
            $member->save();
        }
    }
}
