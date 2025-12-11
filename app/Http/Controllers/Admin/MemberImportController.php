<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class MemberImportController extends Controller
{
    public function template(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\MembersTemplateExport, 'members_import_template.xlsx');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Member::class);
        $user = $request->user();
        if (!$user || !$user->role || $user->role->name !== 'admin_unit') {
            abort(403);
        }
        if (!$user->organization_unit_id) {
            return back()->with('error', 'Akun admin unit belum memiliki unit organisasi');
        }
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');

        try {
            $array = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\SimpleArrayImport, $file);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }

        if (count($array) === 0) {
            return back()->with('error', 'File kosong.');
        }

        $sheet = $array[0]; // First sheet
        if (count($sheet) < 2) {
            return back()->with('error', 'File tidak memiliki data (hanya header atau kosong).');
        }

        $header = array_map('trim', $sheet[0]);
        // Remove BOM if exists in first header
        if (isset($header[0]) && str_starts_with($header[0], "\xEF\xBB\xBF")) {
            $header[0] = substr($header[0], 3);
        }

        $data = [];
        for ($i = 1; $i < count($sheet); $i++) {
            $row = $sheet[$i];
            // Pad row if needed
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), null);
            }
            // Skip empty rows (join all cols)
            if (!trim(implode('', $row)))
                continue;

            $mapped = [];
            foreach ($header as $idx => $key) {
                // Ensure key exists in header
                if ($key) {
                    $mapped[$key] = $row[$idx];
                }
            }
            $data[] = $mapped;
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($data as $index => $item) {
            $this->importRow($item, $user, $success, $failed, $errors, $index + 2);
        }

        if ($failed > 0) {
            $msg = "Import selesai. Sukses: {$success}, Gagal: {$failed}.";
            if (count($errors) > 0) {
                $msg .= " Error pertama: " . $errors[0]['message'] . " (Baris " . $errors[0]['row'] . ")";
            }
            return back()->with('warning', $msg);
        }

        return back()->with('success', "Berhasil mengimpor {$success} anggota.");
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
            $unitId = (int) $user->organization_unit_id; // Forced to admin's unit

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
