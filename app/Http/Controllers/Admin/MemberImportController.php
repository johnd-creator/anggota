<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\MemberImportService;
use App\Services\NraGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            if (! $unitId) {
                return response()->json([
                    'message' => 'Akun admin unit belum memiliki unit organisasi.',
                ], 422);
            }
        } elseif ($user->hasRole('super_admin') || $user->hasRole('admin_pusat')) {
            // Global admins can optionally specify unit
            $unitId = $request->input('organization_unit_id') ?: null;
        } else {
            abort(403);
        }

        try {
            $file = $this->validateImportFile($request);
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
                ->map(fn ($e) => [
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
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first('file') ?: 'Validasi file gagal.',
            ], 422);
        } catch (QueryException $e) {
            $message = 'Preview gagal karena masalah database.';
            $error = strtolower($e->getMessage());
            if (str_contains($error, 'import_batches')) {
                $message = 'Tabel import belum tersedia. Jalankan migrasi database terlebih dahulu.';
            } elseif (str_contains($error, 'readonly')) {
                $message = 'Database bersifat read-only. Periksa permission file database.';
            }

            return response()->json([
                'message' => $message,
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Import preview error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = 'Preview gagal: '.$e->getMessage();
            if (str_contains(strtolower($e->getMessage()), 'memory')) {
                $message = 'Preview gagal: File terlalu besar atau format kompleks. Coba kurangi jumlah baris atau gunakan file CSV.';
            } elseif (str_contains(strtolower($e->getMessage()), 'allowed memory size')) {
                $message = 'Preview gagal: File terlalu besar. Silakan kurangi jumlah baris (max 1000 baris disarankan) atau gunakan format CSV.';
            }

            return response()->json([
                'message' => $message,
            ], 422);
        }
    }

    /**
     * Commit/execute an import batch.
     */
    public function commit(Request $request, \App\Models\ImportBatch $batch)
    {
        $user = $request->user();

        set_time_limit(300);

        // Authorization: only actor or super_admin can commit
        if ($batch->actor_user_id !== $user->id && ! $user->hasRole('super_admin')) {
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
                'error' => 'Import gagal: '.$e->getMessage(),
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
        if ($batch->actor_user_id !== $user->id && ! $user->hasRole('super_admin')) {
            abort(403);
        }

        // Recompute errors from stored file to ensure the report is complete (not limited to preview sample).
        // If the stored file is not available (e.g. synthetic test batches), fall back to stored preview errors.
        $rows = $this->importService->parseStoredFile($batch->stored_path);
        $errors = collect();
        if (! empty($rows)) {
            $errors = collect($this->importService->collectValidationErrors($rows, $batch->organization_unit_id))
                ->sortBy('row_number')
                ->values();
        } elseif ($batch->errors()->exists()) {
            $errors = $batch->errors()
                ->orderBy('row_number')
                ->get()
                ->map(fn ($e) => ['row_number' => $e->row_number, 'errors' => $e->errors_json])
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

                if (! is_array($errorList)) {
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

        if (! $user || ! $user->role || $user->role->name !== 'admin_unit') {
            abort(403);
        }

        $userUnitId = $user->currentUnitId();
        if (! $userUnitId) {
            return redirect()->route('admin.members.index')->with('error', 'Akun admin unit belum memiliki unit organisasi');
        }

        $file = $this->validateImportFile($request);

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
                $msg = 'Import gagal. Tidak ada data valid.';
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

        } catch (\Throwable $e) {
            return redirect()->route('admin.members.index')
                ->with('error', 'Import gagal: '.$e->getMessage());
        }
    }

    private function validateImportFile(Request $request): UploadedFile
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'Upload file gagal. Coba ulangi.',
            ]);
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $extension2 = $file->extension();

        Log::info('File validation details', [
            'original_name' => $originalName,
            'extension' => $extension,
            'extension2' => $extension2,
            'mime_type' => $mimeType,
            'is_valid' => $file->isValid(),
            'client_extension' => $file->getClientOriginalExtension(),
            'guessed_extension' => $file->guessExtension(),
        ]);

        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            Log::warning('File extension not allowed', [
                'extension' => $extension,
                'allowed' => ['csv', 'xlsx', 'xls'],
                'original_name' => $originalName,
            ]);
            throw ValidationException::withMessages([
                'file' => 'Format file harus CSV, XLSX, atau XLS.',
            ]);
        }

        return $file;
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
        if ($companyEmail && ! str_ends_with($companyEmail, '@plnipservices.co.id')) {
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
            if ($nip) {
                $member = Member::where('nip', $nip)->first();
            }
            if (! $member && $email) {
                $member = Member::where('email', $email)->first();
            }

            $isNew = ! $member;

            if ($isNew) {
                $gen = NraGenerator::generate($unitId, $joinYear);
                $nraVal = $gen['nra'];

                $member = new Member;
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

                if (! $targetUser) {
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
                    if (! $targetUser->member_id) {
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
