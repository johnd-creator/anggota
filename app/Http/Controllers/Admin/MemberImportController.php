<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\MemberImportService;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class MemberImportController extends Controller
{
    protected MemberImportService $importService;

    public function __construct(MemberImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Show the import page.
     */
    public function index()
    {
        Gate::authorize('create', Member::class);
        return Inertia::render('Admin/Members/Import');
    }

    public function template(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\MembersTemplateExport, 'members_import_template.xlsx');
    }

    /**
     * Preview import without committing.
     */
    public function preview(Request $request)
    {
        Gate::authorize('create', Member::class);

        $user = $request->user();

        // Determine effective unit ID (admin_unit forced to own unit)
        $unitId = null;
        if ($user->hasRole('admin_unit')) {
            $unitId = $user->currentUnitId();
            if (!$unitId) {
                return back()->withErrors(['file' => 'Akun admin unit belum memiliki unit organisasi']);
            }
        } elseif ($user->hasRole('super_admin') || $user->hasRole('admin_pusat')) {
            // Global admins can optionally specify unit
            $unitId = $request->input('organization_unit_id') ?: null;
        } else {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
        ]);

        $file = $request->file('file');

        try {
            // Run preview
            $batch = $this->importService->preview($file, $unitId, $user);

            // Audit log (no PII)
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $unitId,
                'event' => 'import.members.preview',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'unit_id' => $unitId,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                ],
            ]);

            // Get first 20 errors for display
            $errorSample = $batch->errors()
                ->orderBy('row_number')
                ->limit(20)
                ->get()
                ->map(fn($e) => [
                    'row' => $e->row_number,
                    'errors' => $e->errors_json,
                ]);

            return response()->json([
                'batch' => [
                    'id' => $batch->id,
                    'status' => $batch->status,
                    'original_filename' => $batch->original_filename,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                ],
                'errors' => $errorSample,
            ]);
        } catch (\Exception $e) {
            \Log::error('Import preview error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Preview gagal: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Commit/execute an import batch.
     */
    public function commit(Request $request, \App\Models\ImportBatch $batch)
    {
        $user = $request->user();

        // Authorization: only actor or super_admin can commit
        if ($batch->actor_user_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, 'Anda tidak memiliki akses untuk commit batch ini.');
        }

        // Idempotency check
        if ($batch->committed_at) {
            return response()->json([
                'error' => 'Batch sudah di-commit sebelumnya.',
                'committed_at' => $batch->committed_at->toIso8601String(),
            ], 409);
        }

        // Must be in previewed status
        if ($batch->status !== 'previewed') {
            return response()->json([
                'error' => 'Batch harus dalam status previewed.',
                'current_status' => $batch->status,
            ], 422);
        }

        // Mark as processing
        $batch->markProcessing();

        // Audit log: commit start
        AuditLog::create([
            'user_id' => $user->id,
            'organization_unit_id' => $batch->organization_unit_id,
            'event' => 'import.members.commit',
            'event_category' => 'export',
            'subject_type' => 'import_batch',
            'subject_id' => $batch->id,
            'payload' => [
                'batch_id' => $batch->id,
                'total_rows' => $batch->total_rows,
            ],
        ]);

        try {
            // Execute import
            $result = $this->importService->commit($batch);

            // Update batch with results
            $batch->update([
                'status' => 'completed',
                'committed_at' => now(),
                'finished_at' => now(),
                'created_count' => $result['created_count'],
                'updated_count' => $result['updated_count'],
            ]);

            // Audit log: completed
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $batch->organization_unit_id,
                'event' => 'import.members.completed',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'created_count' => $result['created_count'],
                    'updated_count' => $result['updated_count'],
                    'error_count' => $result['error_count'],
                ],
            ]);

            return response()->json([
                'status' => 'completed',
                'batch_id' => $batch->id,
                'created_count' => $result['created_count'],
                'updated_count' => $result['updated_count'],
                'error_count' => $result['error_count'],
            ]);
        } catch (\Exception $e) {
            $batch->markFailed();

            // Audit log: failed
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $batch->organization_unit_id,
                'event' => 'import.members.failed',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ],
            ]);

            return response()->json([
                'status' => 'failed',
                'error' => 'Import gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download error report as CSV.
     */
    public function downloadErrors(Request $request, \App\Models\ImportBatch $batch)
    {
        $user = $request->user();

        // Authorization: only actor or super_admin
        if ($batch->actor_user_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403);
        }

        // Recompute errors from stored file to ensure the report is complete (not limited to preview sample).
        // If the stored file is not available (e.g. synthetic test batches), fall back to stored preview errors.
        $rows = $this->importService->parseStoredFile($batch->stored_path);
        $errors = collect();
        if (!empty($rows)) {
            $errors = collect($this->importService->collectValidationErrors($rows, $batch->organization_unit_id))
                ->sortBy('row_number')
                ->values();
        } elseif ($batch->errors()->exists()) {
            $errors = $batch->errors()
                ->orderBy('row_number')
                ->get()
                ->map(fn($e) => ['row_number' => $e->row_number, 'errors' => $e->errors_json])
                ->values();
        }

        $filename = "import_errors_batch_{$batch->id}.csv";

        return response()->streamDownload(function () use ($errors) {
            $out = fopen('php://output', 'w');

            // New header with 6 columns for structured errors
            fputcsv($out, ['row_number', 'severity', 'field', 'current_value', 'message', 'expected_format']);

            foreach ($errors as $error) {
                $rowNumber = $error['row_number'] ?? '';
                $errorList = $error['errors'] ?? [];

                if (!is_array($errorList)) {
                    continue;
                }

                foreach ($errorList as $fieldError) {
                    // Handle both new structured format and legacy string format
                    if (is_array($fieldError) && isset($fieldError['field'])) {
                        fputcsv($out, [
                            $rowNumber,
                            $fieldError['severity'] ?? 'warning',
                            $fieldError['field'] ?? '',
                            $fieldError['current_value'] ?? '',
                            $fieldError['message'] ?? '',
                            $fieldError['expected_format'] ?? '',
                        ]);
                    } else {
                        // Legacy string format - convert to basic structure
                        fputcsv($out, [
                            $rowNumber,
                            'warning',
                            '',
                            '',
                            is_string($fieldError) ? $fieldError : '',
                            '',
                        ]);
                    }
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Legacy import endpoint - now uses batch flow for audit and idempotency.
     * 
     * @deprecated Use preview() + commit() flow for new implementations.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Member::class);
        $user = $request->user();

        if (!$user || !$user->role || $user->role->name !== 'admin_unit') {
            abort(403);
        }

        $userUnitId = $user->currentUnitId();
        if (!$userUnitId) {
            return redirect()->route('admin.members.index')->with('error', 'Akun admin unit belum memiliki unit organisasi');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');

        try {
            // Use batch flow: preview first
            $batch = $this->importService->preview($file, $userUnitId, $user);

            // Audit log: preview (same as new flow)
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $userUnitId,
                'event' => 'import.members.preview',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'unit_id' => $userUnitId,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                    'source' => 'legacy_store',
                ],
            ]);

            // Check if there are valid rows to commit
            if ($batch->valid_rows === 0) {
                $msg = "Import gagal. Tidak ada data valid.";
                if ($batch->invalid_rows > 0) {
                    $msg .= " {$batch->invalid_rows} baris error.";
                }
                return redirect()->route('admin.members.index')->with('error', $msg);
            }

            // Auto-commit for legacy flow
            $batch->markProcessing();

            // Audit log: commit
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $userUnitId,
                'event' => 'import.members.commit',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'total_rows' => $batch->total_rows,
                    'source' => 'legacy_store',
                ],
            ]);

            // Execute import
            $result = $this->importService->commit($batch);

            // Update batch
            $batch->update([
                'status' => 'completed',
                'committed_at' => now(),
                'finished_at' => now(),
                'created_count' => $result['created_count'],
                'updated_count' => $result['updated_count'],
            ]);

            // Audit log: completed
            AuditLog::create([
                'user_id' => $user->id,
                'organization_unit_id' => $userUnitId,
                'event' => 'import.members.completed',
                'event_category' => 'export',
                'subject_type' => 'import_batch',
                'subject_id' => $batch->id,
                'payload' => [
                    'batch_id' => $batch->id,
                    'created_count' => $result['created_count'],
                    'updated_count' => $result['updated_count'],
                    'error_count' => $result['error_count'],
                    'source' => 'legacy_store',
                ],
            ]);

            $total = $result['created_count'] + $result['updated_count'];
            if ($result['error_count'] > 0) {
                return redirect()->route('admin.members.index')
                    ->with('warning', "Import selesai. Sukses: {$total}, Gagal: {$result['error_count']}.");
            }

            return redirect()->route('admin.members.index')
                ->with('success', "Berhasil mengimpor {$total} anggota.");

        } catch (\Exception $e) {
            return redirect()->route('admin.members.index')
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    /**
     * Read spreadsheet file to array (supports CSV, XLSX (inlineStr), and XLS SpreadsheetML XML).
     * Returns array of sheets, each sheet is array of rows (array of cell values).
     */
    private function readSpreadsheetToArray(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        // Handle SpreadsheetML 2003 saved with .xls extension (XML).
        if ($ext === 'xls') {
            $content = $file->get();
            $trimmed = ltrim((string) $content);
            if (str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<Workbook')) {
                return [$this->parseSpreadsheetMlXml((string) $content)];
            }
        }

        // Handle minimal XLSX (inlineStr) without relying on Laravel-Excel's detection.
        if ($ext === 'xlsx') {
            try {
                return [$this->parseXlsxInlineStrings($file->getRealPath())];
            } catch (\Throwable $e) {
                // Fallback to Laravel-Excel if our parser can't handle the file.
            }
        }

        return \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\SimpleArrayImport, $file);
    }

    private function parseSpreadsheetMlXml(string $xmlContent): array
    {
        $xml = @simplexml_load_string($xmlContent);
        if (!$xml) {
            throw new \RuntimeException('Format XLS (XML) tidak valid.');
        }

        $ns = 'urn:schemas-microsoft-com:office:spreadsheet';
        $xml->registerXPathNamespace('s', $ns);

        $rows = [];
        foreach ($xml->xpath('//s:Row') ?: [] as $rowNode) {
            $row = [];
            // NOTE: XPath namespaces are not inherited on SimpleXMLElement nodes,
            // so we use children($ns) instead of $rowNode->xpath('./s:Cell').
            foreach ($rowNode->children($ns)->Cell ?: [] as $cell) {
                $data = $cell->children($ns)->Data;
                $row[] = isset($data[0]) ? (string) $data[0] : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function parseXlsxInlineStrings(?string $path): array
    {
        if (!$path || !is_file($path)) {
            throw new \RuntimeException('File XLSX tidak ditemukan.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file XLSX.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            throw new \RuntimeException('Sheet XLSX tidak ditemukan.');
        }

        // Some test-generated XLSX XML strings contain literal "\n" sequences.
        $sheetXml = str_replace(['\\n', '\\r'], ["\n", "\r"], $sheetXml);

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml) {
            throw new \RuntimeException('Format XLSX tidak valid.');
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $xml->registerXPathNamespace('s', $ns);

        $rows = [];
        foreach ($xml->xpath('//s:sheetData/s:row') ?: [] as $rowNode) {
            $row = [];
            // NOTE: XPath namespaces are not inherited on SimpleXMLElement nodes,
            // so we use children($ns) instead of $rowNode->xpath('./s:c').
            foreach ($rowNode->children($ns)->c ?: [] as $cell) {
                $attrs = $cell->attributes() ?: [];
                $ref = isset($attrs['r']) ? (string) $attrs['r'] : '';
                $colLetters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
                $colIndex = $this->xlsxColumnIndex($colLetters);
                $value = '';

                $type = isset($attrs['t']) ? (string) $attrs['t'] : '';
                if ($type === 'inlineStr') {
                    $is = $cell->children($ns)->is;
                    $t = $is ? $is->children($ns)->t : null;
                    $value = $t ? (string) $t : '';
                } else {
                    $v = $cell->children($ns)->v;
                    $value = $v ? (string) $v : '';
                }

                if ($colIndex !== null) {
                    $row[$colIndex] = $value;
                } else {
                    $row[] = $value;
                }
            }

            if (!empty($row)) {
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    private function xlsxColumnIndex(?string $letters): ?int
    {
        if (!$letters) {
            return null;
        }
        $letters = strtoupper($letters);
        $idx = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $c = ord($letters[$i]);
            if ($c < 65 || $c > 90) {
                return null;
            }
            $idx = ($idx * 26) + ($c - 64);
        }
        return $idx - 1;
    }

    private function importRow(array $item, $user, int &$success, int &$failed, array &$errors, int $row)
    {
        // Personal Data
        $fullName = trim($item['personal_full_name'] ?? $item['full_name'] ?? '');
        $email = trim($item['personal_email'] ?? $item['email'] ?? '');
        $nip = trim($item['personal_nip'] ?? $item['nip'] ?? '');
        $birthPlace = trim($item['personal_birth_place'] ?? '');
        $birthDate = trim($item['personal_birth_date'] ?? '');
        $rawGender = strtolower(trim($item['personal_gender'] ?? $item['gender'] ?? ''));
        $gender = ($rawGender === 'laki-laki' || $rawGender === 'l') ? 'L' : (($rawGender === 'perempuan' || $rawGender === 'p') ? 'P' : null);
        $phone = trim($item['personal_phone'] ?? $item['phone'] ?? '');
        $address = trim($item['address'] ?? '');
        $companyEmail = trim($item['company_email'] ?? '');

        // Organization Data
        $unionPosCode = trim($item['union_position_code'] ?? '');
        $empType = strtolower(trim($item['employment_type'] ?? 'organik'));
        $joinDate = trim($item['join_date'] ?? '');
        $companyJoinDate = trim($item['company_join_date'] ?? '');
        $status = strtolower(trim($item['status'] ?? 'aktif'));
        $jobTitle = trim($item['job_title'] ?? '');
        $notes = trim($item['notes'] ?? '');

        if ($fullName === '') {
            $failed++;
            $errors[] = ['row' => $row, 'message' => 'Nama lengkap wajib'];
            return;
        }

        // Validation: Company Email Domain
        if ($companyEmail && !str_ends_with($companyEmail, '@plnipservices.co.id')) {
            $failed++;
            $errors[] = ['row' => $row, 'message' => 'Email perusahaan harus @plnipservices.co.id'];
            return;
        }

        try {
            DB::beginTransaction();

            $joinYear = $joinDate ? (int) date('Y', strtotime($joinDate)) : (int) now()->year;
            $unitId = (int) $user->currentUnitId(); // Forced to admin's unit via currentUnitId()

            // Resolve Union Position
            $unionPosId = null;
            if ($unionPosCode) {
                $pos = \App\Models\UnionPosition::where('code', $unionPosCode)->orWhere('name', $unionPosCode)->first();
                $unionPosId = $pos?->id;
            }

            // Generate NRA if missing
            $nraVal = null;

            // Find existing
            $member = null;
            if ($nip)
                $member = Member::where('nip', $nip)->first();
            if (!$member && $email)
                $member = Member::where('email', $email)->first();

            $isNew = !$member;

            if ($isNew) {
                $gen = NraGenerator::generate($unitId, $joinYear);
                $nraVal = $gen['nra'];

                $member = new Member();
                $member->nra = $nraVal;
                $member->join_year = $joinYear;
                $member->sequence_number = $gen['sequence'];
            }

            $member->full_name = $fullName;
            $member->email = $email ?: null;
            $member->nip = $nip ?: null;
            $member->phone = $phone ?: null;
            $member->birth_place = $birthPlace ?: null;
            $member->birth_date = $birthDate ?: null;
            $member->gender = $gender;
            $member->address = $address ?: null;

            $member->organization_unit_id = $unitId; // Force sync unit logic
            $member->union_position_id = $unionPosId ?: $member->union_position_id;
            $member->employment_type = $empType;
            $member->status = in_array($status, ['aktif', 'cuti', 'suspended', 'resign', 'pensiun']) ? $status : 'aktif';
            $member->join_date = $joinDate ?: now()->toDateString();
            $member->company_join_date = $companyJoinDate ?: null;
            $member->job_title = $jobTitle ?: null;
            $member->notes = $notes ?: null;

            $member->save();

            // Handle User Logic
            $targetEmail = $email ?: $companyEmail;

            if ($targetEmail) {
                // Ensure user exists
                $targetUser = \App\Models\User::where('email', $targetEmail)->first();

                if (!$targetUser) {
                    $targetUser = \App\Models\User::create([
                        'name' => $fullName,
                        'email' => $targetEmail,
                        'company_email' => $companyEmail ?: null,
                        'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                        'role_id' => \App\Models\Role::where('name', 'anggota')->value('id'),
                        'member_id' => $member->id,
                        'organization_unit_id' => $unitId,
                    ]);
                } else {
                    // User exists, link member
                    if (!$targetUser->member_id) {
                        $targetUser->member_id = $member->id;
                        $targetUser->organization_unit_id = $unitId;
                        $targetUser->save();
                    }
                    // Update company_email if provided
                    if ($companyEmail && $targetUser->company_email !== $companyEmail) {
                        $targetUser->company_email = $companyEmail;
                        $targetUser->save();
                    }
                }

                // Reverse Link member -> user
                if ($targetUser && $member->user_id !== $targetUser->id) {
                    $member->user_id = $targetUser->id;
                    $member->save();
                }
            }

            DB::commit();
            $success++;
        } catch (\Throwable $e) {
            DB::rollBack();
            $failed++;
            $errors[] = ['row' => $row, 'message' => $e->getMessage()];
        }
    }
}
