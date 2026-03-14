<?php

namespace Database\Seeders;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\LetterRead;
use App\Models\LetterRevision;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LetterSeeder extends Seeder
{
    private const TOTAL_LETTERS = 100;

    private array $categories = [];
    private array $units = [];
    private array $creators = [];
    private array $members = [];
    private array $admins = [];

    private array $statusDistribution = [
        'draft' => 20,
        'submitted' => 25,
        'revision' => 10,
        'approved' => 12,
        'sent' => 18,
        'archived' => 10,
        'rejected' => 5,
    ];

    private array $urgencyOptions = ['biasa' => 72, 'segera' => 24, 'kilat' => 4];
    private array $confidentialityOptions = ['biasa', 'terbatas', 'rahasia'];
    private array $toTypeOptions = ['unit', 'member', 'admin_pusat', 'eksternal'];
    private array $signerTypeOptions = ['ketua', 'sekretaris'];

    public function run(): void
    {
        $this->command->info('✉️  Starting LetterSeeder...');
        $this->command->newLine();

        $this->loadReferenceData();
        $generatedNumbers = [];

        // Generate letters by status
        foreach ($this->statusDistribution as $status => $count) {
            $this->command->info("   Creating {$count} letters with status '{$status}'...");

            foreach (range(1, $count) as $i) {
                $letter = $this->createLetter($status, $generatedNumbers);
                $this->createRelatedData($letter, $status);

                if ($i % 5 === 0) {
                    $this->command->info("      ✓ Created {$i}/{$count}");
                }
            }
        }

        $this->displaySummary();
    }

    private function loadReferenceData(): void
    {
        $this->command->info('📋 Loading reference data...');

        $this->categories = LetterCategory::all()->keyBy('id');
        $this->units = OrganizationUnit::all()->keyBy('id');
        $this->creators = User::whereHas('role', fn($q) => $q->whereIn('name',
            ['super_admin', 'admin_pusat', 'admin_unit', 'pengurus']))->get();
        $this->members = User::whereHas('role', fn($q) => $q->where('name', 'anggota'))->get();
        $this->admins = User::whereHas('role', fn($q) => $q->whereIn('name',
            ['super_admin', 'admin_pusat']))->get();

        if ($this->categories->isEmpty()) {
            $this->command->error('❌ No letter categories found. Run LetterCategorySeeder first.');
            exit(1);
        }

        if ($this->units->isEmpty()) {
            $this->command->error('❌ No organization units found. Run OrganizationUnitSeeder first.');
            exit(1);
        }

        if ($this->creators->isEmpty()) {
            $this->command->warn('⚠️  No eligible creators found. Letters may have invalid creator_user_id.');
        }

        if ($this->admins->isEmpty()) {
            $this->command->warn('⚠️  No admin users found. Approval workflows may not work correctly.');
        }

        $this->command->info('   ✓ Reference data loaded');
        $this->command->info('   - ' . $this->categories->count() . ' letter categories');
        $this->command->info('   - ' . $this->units->count() . ' organization units');
        $this->command->info('   - ' . $this->creators->count() . ' eligible creators');
        $this->command->info('   - ' . $this->members->count() . ' members');
        $this->command->info('   - ' . $this->admins->count() . ' admins');
        $this->command->newLine();
    }

    private function createLetter(string $status, array &$generatedNumbers): Letter
    {
        return DB::transaction(function() use ($status, &$generatedNumbers) {
            $data = $this->getLetterDataForStatus($status);
            $letter = Letter::create($data);

            // Assign letter number if not draft
            if ($status !== 'draft') {
                $letter->letter_number = $this->generateLetterNumber($letter, $generatedNumbers);
                $letter->verification_token = Str::random(32);
                $letter->save();
            }

            return $letter->fresh();
        });
    }

    private function getLetterDataForStatus(string $status): array
    {
        $creator = $this->creators->random();
        $fromUnit = $this->units->random();
        $category = $this->categories->random();
        $urgency = array_rand($this->urgencyOptions);
        $confidentiality = $this->confidentialityOptions[array_rand($this->confidentialityOptions)];
        $toType = $this->toTypeOptions[array_rand($this->toTypeOptions)];
        $signerType = $this->signerTypeOptions[array_rand($this->signerTypeOptions)];

        $createdAt = now()->subDays(rand(1, 30));
        $slaHours = $this->urgencyOptions[$urgency];

        $data = [
            'creator_user_id' => $creator->id,
            'from_unit_id' => $fromUnit->id,
            'letter_category_id' => $category->id,
            'signer_type' => $signerType,
            'signer_type_secondary' => null,
            'to_type' => $toType,
            'to_unit_id' => null,
            'to_member_id' => null,
            'to_external_name' => null,
            'to_external_org' => null,
            'to_external_address' => null,
            'subject' => $this->generateSubject($category->code),
            'body' => $this->generateBody($category->code),
            'cc_text' => rand(1, 100) <= 35 ? $this->generateCcText() : null,
            'confidentiality' => $confidentiality,
            'urgency' => $urgency,
            'status' => $status,
            'month' => (int) $createdAt->format('n'),
            'year' => (int) $createdAt->format('y'),
            'sequence' => rand(1, 999),
            'created_at' => $createdAt,
        ];

        // Set destination based on to_type
        if ($toType === 'unit') {
            $eligibleUnits = $this->units->where('id', '!=', $fromUnit->id);
            if ($eligibleUnits->isNotEmpty()) {
                $data['to_unit_id'] = $eligibleUnits->random()->id;
            } else {
                // Fallback to same unit if no other units available
                $data['to_unit_id'] = $fromUnit->id;
            }
        } elseif ($toType === 'member' && !$this->members->isEmpty()) {
            $member = $this->members->random();
            $data['to_member_id'] = $member->member_id;
        } elseif ($toType === 'eksternal') {
            $external = $this->generateExternalRecipient();
            $data['to_external_name'] = $external['name'];
            $data['to_external_org'] = $external['org'];
            $data['to_external_address'] = $external['address'];
        }

        // Status-specific fields
        return $this->setStatusSpecificFields($data, $status, $slaHours);
    }

    private function setStatusSpecificFields(array $data, string $status, int $slaHours): array
    {
        $createdAt = $data['created_at'];

        switch ($status) {
            case 'draft':
                // No additional fields
                break;

            case 'submitted':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);

                // 30% SLA breach
                if (rand(1, 100) <= 30) {
                    $data['sla_status'] = 'breach';
                    $data['sla_marked_at'] = $data['sla_due_at']->copy()->addMinutes(rand(5, 60));
                } else {
                    $data['sla_status'] = 'ok';
                }

                // 20% dual approval setup
                if (rand(1, 100) <= 20) {
                    $data['signer_type_secondary'] = 'bendahara';
                }
                break;

            case 'revision':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);
                break;

            case 'approved':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);
                $approvalTime = $data['submitted_at']->copy()->addHours(rand(1, min($slaHours, 24)));
                $data['approved_by_user_id'] = $this->admins->random()->id;
                $data['approved_primary_at'] = $approvalTime;

                // 40% dual approval completed
                if (rand(1, 100) <= 40) {
                    $data['signer_type_secondary'] = 'bendahara';
                    $otherAdmins = $this->admins->where('id', '!=', $data['approved_by_user_id']);
                    if ($otherAdmins->isNotEmpty()) {
                        $data['approved_secondary_by_user_id'] = $otherAdmins->random()->id;
                        $data['approved_secondary_at'] = $approvalTime->copy()->addHours(rand(1, 8));
                    }
                }

                $data['approved_at'] = $data['signer_type_secondary'] && isset($data['approved_secondary_at'])
                    ? $data['approved_secondary_at']
                    : $data['approved_primary_at'];
                break;

            case 'sent':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);
                $approvalTime = $data['submitted_at']->copy()->addHours(rand(1, min($slaHours, 24)));
                $data['approved_by_user_id'] = $this->admins->random()->id;
                $data['approved_primary_at'] = $approvalTime;

                if (rand(1, 100) <= 40) {
                    $data['signer_type_secondary'] = 'bendahara';
                    $otherAdmins = $this->admins->where('id', '!=', $data['approved_by_user_id']);
                    if ($otherAdmins->isNotEmpty()) {
                        $data['approved_secondary_by_user_id'] = $otherAdmins->random()->id;
                        $data['approved_secondary_at'] = $approvalTime->copy()->addHours(rand(1, 8));
                        $data['approved_at'] = $data['approved_secondary_at'];
                    } else {
                        $data['approved_at'] = $data['approved_primary_at'];
                    }
                } else {
                    $data['approved_at'] = $data['approved_primary_at'];
                }

                $data['sent_at'] = $data['approved_at']->copy()->addDays(rand(0, 2));
                break;

            case 'archived':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);
                $approvalTime = $data['submitted_at']->copy()->addHours(rand(1, min($slaHours, 24)));
                $data['approved_by_user_id'] = $this->admins->random()->id;
                $data['approved_primary_at'] = $approvalTime;

                if (rand(1, 100) <= 40) {
                    $data['signer_type_secondary'] = 'bendahara';
                    $otherAdmins = $this->admins->where('id', '!=', $data['approved_by_user_id']);
                    if ($otherAdmins->isNotEmpty()) {
                        $data['approved_secondary_by_user_id'] = $otherAdmins->random()->id;
                        $data['approved_secondary_at'] = $approvalTime->copy()->addHours(rand(1, 8));
                        $data['approved_at'] = $data['approved_secondary_at'];
                    } else {
                        $data['approved_at'] = $data['approved_primary_at'];
                    }
                } else {
                    $data['approved_at'] = $data['approved_primary_at'];
                }

                $data['sent_at'] = $data['approved_at']->copy()->addDays(rand(0, 2));
                $data['archived_at'] = $data['sent_at']->copy()->addDays(rand(7, 30));
                break;

            case 'rejected':
                $data['submitted_at'] = $createdAt->copy()->addHours(rand(1, 4));
                $data['sla_due_at'] = $data['submitted_at']->copy()->addHours($slaHours);
                $data['rejected_by_user_id'] = $this->admins->random()->id;
                $data['rejected_at'] = $data['submitted_at']->copy()->addHours(rand(1, min($slaHours, 24)));
                $data['revision_note'] = $this->generateRevisionNote();
                break;
        }

        return $data;
    }

    private function createRelatedData(Letter $letter, string $status): void
    {
        // Create revisions for 'revision' status
        if ($status === 'revision') {
            $this->createRevisions($letter);
        }

        // Create reads for 'sent' and 'archived' status
        if (in_array($status, ['sent', 'archived']) && $letter->to_type !== 'eksternal') {
            $this->createReads($letter);
        }

        // Randomly create attachments (15-20%)
        if (rand(1, 100) <= 18) {
            $this->createAttachments($letter);
        }
    }

    private function createRevisions(Letter $letter): void
    {
        $count = rand(1, 3);
        $actor = $this->admins->isNotEmpty() ? $this->admins->random() : null;

        foreach (range(1, $count) as $i) {
            LetterRevision::create([
                'letter_id' => $letter->id,
                'actor_user_id' => $actor?->id,
                'actor_name' => $actor?->name ?? 'System',
                'revision_note' => $this->generateRevisionNote(),
                'created_at' => $letter->submitted_at?->copy()->addHours(rand(1, 72)) ?? now(),
            ]);
        }
    }

    private function createReads(Letter $letter): void
    {
        $readers = match($letter->to_type) {
            'unit' => User::where('organization_unit_id', $letter->to_unit_id)->get(),
            'member' => User::where('member_id', $letter->to_member_id)->get(),
            'admin_pusat' => $this->admins,
            default => collect(),
        };

        if ($readers->isEmpty()) {
            return;
        }

        $count = $letter->to_type === 'member' ? rand(1, 2) : rand(3, 10);
        $availableReaders = $readers;

        foreach (range(1, min($count, $readers->count())) as $i) {
            if ($availableReaders->isEmpty()) {
                break;
            }

            $reader = $availableReaders->random();
            $availableReaders = $availableReaders->filter(fn($u) => $u->id !== $reader->id);

            LetterRead::create([
                'letter_id' => $letter->id,
                'user_id' => $reader->id,
                'read_at' => $letter->sent_at
                    ? $letter->sent_at->copy()->addHours(rand(1, 24))
                    : now()->subHours(rand(1, 24)),
            ]);
        }
    }

    private function createAttachments(Letter $letter): void
    {
        $count = rand(1, 3);

        foreach (range(1, $count) as $i) {
            $isPdf = rand(1, 100) % 2 === 0;
            $extension = $isPdf ? 'pdf' : (rand(1, 100) % 2 === 0 ? 'jpg' : 'xlsx');
            $mimeType = match($extension) {
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/octet-stream',
            };

            LetterAttachment::create([
                'letter_id' => $letter->id,
                'filename' => "letter_{$letter->id}_att_{$i}.{$extension}",
                'filepath' => "storage/app/letters/letter_{$letter->id}_att_{$i}.{$extension}",
                'filesize' => rand(50000, 5000000),
                'mime_type' => $mimeType,
                'uploaded_by' => $letter->creator_user_id,
                'created_at' => $letter->created_at->copy()->addMinutes(rand(1, 60)),
            ]);
        }
    }

    private function generateLetterNumber(Letter $letter, array &$generatedNumbers): string
    {
        $category = $this->categories->get($letter->letter_category_id);
        $unit = $this->units->get($letter->from_unit_id);
        $retryCount = 0;

        while ($retryCount < 10) {
            $sequence = str_pad($letter->sequence + $retryCount, 3, '0', STR_PAD_LEFT);
            $letterNumber = "{$sequence}/{$category->code}/{$unit->code}/SP-PIPS/"
                . str_pad($letter->month, 2, '0', STR_PAD_LEFT) . "/{$letter->year}";

            if (!in_array($letterNumber, $generatedNumbers)) {
                $generatedNumbers[] = $letterNumber;
                return $letterNumber;
            }

            $retryCount++;
        }

        // Fallback: use timestamp
        return time() . "/{$category->code}/{$unit->code}/SP-PIPS/"
            . str_pad($letter->month, 2, '0', STR_PAD_LEFT) . "/{$letter->year}";
    }

    private function generateSubject(string $categoryCode): string
    {
        $subjects = [
            'UND' => ['Undangan Rapat Anggota Tahunan', 'Undangan Rapat Koordinasi',
                'Undangan Sosialisasi Program Baru', 'Undangan Pertemuan Pengurus',
                'Undangan Musyawarah Besar', 'Undangan Pelatihan Anggota'],
            'PEM' => ['Pemberitahuan Libur Nasional', 'Pemberitahuan Jadwal Baru',
                'Pemberitahuan Perubahan Kebijakan', 'Informasi Penting',
                'Pemberitahuan Kegiatan Rutin', 'Edaran Penting'],
            'ORG' => ['Reorganisasi Struktur Organisasi', 'Pembentukan Panitia Kerja',
                'Perubahan Susunan Pengurus', 'Pengangkatan Pejabat Sementara',
                'Pembentukan Unit Baru', 'Penataan Organisasi'],
            'AGT' => ['Verifikasi Data Keanggotaan', 'Pemberhentian Anggota',
                'Penerimaan Anggota Baru', 'Mutasi Keanggotaan',
                'Update Data Anggota', 'Konfirmasi Keanggotaan'],
            'HI' => ['Negosiasi PKB dengan Perusahaan', 'Mediasi Kasus Anggota',
                'Konsultasi Hubungan Industrial', 'Penyelesaian Sengketa',
                'Perundingan Kolektif', 'Klarifikasi HK'],
            'ADV' => ['Bantuan Hukum untuk Anggota', 'Konsultasi Advokasi',
                'Pendampingan Kasus', 'Rekomendasi Tindakan Hukum',
                'Kuasa Hukum Anggota', 'Nota Dinas Advokasi'],
            'EKS' => ['Surat Ke Dinas Pemerintahan', 'Korespondensi dengan Serikat Pekerja Lain',
                'Undangan Eksternal', 'Surat Permohonan Kerjasama',
                'Surat Resmi Mitra', 'Korespondensi Eksternal'],
            'SK' => ['Surat Keputusan Pengurus', 'SK Penetapan Kebijakan',
                'SK Pembentukan Unit Kerja', 'SK Pemberhentian',
                'SK Pengangkatan Pejabat', 'SK Kebijakan Baru'],
            'REK' => ['Rekomendasi Promosi', 'Rekomendasi Mutasi',
                'Saran Pengembangan Karir', 'Rekomendasi Training',
                'Rekomendasi Kenaikan Jenjang', 'Saran Karir'],
        ];

        $options = $subjects[$categoryCode] ?? ['Surat Umum', 'Surat Edaran', 'Surat Resmi'];
        return $options[array_rand($options)];
    }

    private function generateBody(string $categoryCode): string
    {
        $bodies = [
            'UND' => "Dengan hormat,<br><br>Sehubungan dengan adanya kegiatan penting yang akan dilaksanakan, dengan ini kami mengundang Bapak/Ibu/Saudara/i untuk hadir dalam:<br><br>Kegiatan: [Nama Kegiatan]<br>Hari/Tanggal: [Detail]<br>Waktu: [Detail]<br>Tempat: [Detail]<br><br>Dimohon kehadirannya tepat pada waktunya.<br><br>Hormat kami,<br>Pengurus",
            'PEM' => "Diberitahukan kepada seluruh anggota bahwa:<br><br>[Isi Pemberitahuan]<br><br>Hal ini agar diperhatikan dan dilaksanakan sebagaimana mestinya.<br><br>Terima kasih.",
            'ORG' => "Dalam rangka meningkatkan efektivitas organisasi, kami informasikan perubahan berikut:<br><br>[Detail Perubahan]<br><br>Demikian untuk diketahui dan dilaksanakan.<br><br>Hormat kami,<br>Pengurus",
            'AGT' => "Sehubungan dengan data keanggotaan, kami mohon konfirmasi mengenai:<br><br>[Detail Keanggotaan]<br><br>Mohon segera melengkapi data yang diperlukan.<br><br>Terima kasih.",
            'HI' => "Menindaklanjuti permasalahan hubungan industrial, kami sampaikan bahwa:<br><br>[Detail Kasus dan Tindak Lanjut]<br><br>Demikian untuk menjadi perhatian bersama.<br><br>Hormat kami,<br>Pengurus",
            'ADV' => "Berkaitan dengan permohonan bantuan hukum, kami informasikan:<br><br>[Status dan Tindak Lanjut]<br><br>Mohon koordinasi lebih lanjut dengan divisi hukum.<br><br>Terima kasih.",
            'EKS' => "Dengan hormat,<br><br>Sehubungan dengan [Perihal], kami dari [Organisasi] mengharapkan:<br><br>[Permintaan/Informasi]<br><br>Demikian surat ini kami sampaikan.<br><br>Hormat kami,<br>Pengurus",
            'SK' => "Berdasarkan rapat pengurus tanggal [Tanggal], ditetapkan bahwa:<br><br>[Keputusan]<br><br>Keputusan ini berlaku sejak tanggal ditetapkan.<br><br>Ditetapkan oleh:<br>Pengurus",
            'REK' => "Setelah melakukan evaluasi, kami merekomendasikan:<br><br>[Isi Rekomendasi]<br><br>Harap dipertimbangkan untuk keputusan selanjutnya.<br><br>Terima kasih.",
            'default' => "Dengan hormat,<br><br>Sehubungan dengan [Perihal], kami sampaikan hal-hal berikut:<br><br>[Isi Surat]<br><br>Demikian surat ini kami sampaikan. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.<br><br>Hormat kami,<br>Pengurus",
        ];

        return $bodies[$categoryCode] ?? $bodies['default'];
    }

    private function generateCcText(): ?string
    {
        $options = [
            'Divisi Hukum, Divisi Keuangan',
            'Bapak Ketua, Ibu Bendahara',
            'Dewan Pengurus, Divisi Organisasi',
            'Sekretariat, Divisi Advokasi',
            'Divisi Keanggotaan, Divisi Pendidikan',
            'Semua Ketua Unit, Bendahara Pusat',
        ];

        return $options[array_rand($options)];
    }

    private function generateExternalRecipient(): array
    {
        $orgs = [
            ['org' => 'PT. Maju Jaya Abadi', 'name' => 'Bpk. Ahmad Hidayat', 'address' => 'Jl. Sudirman No. 123, Jakarta'],
            ['org' => 'Dinas Tenaga Kerja', 'name' => 'Ibu Sri Wahyuni', 'address' => 'Jl. Gatot Subroto No. 45, Jakarta'],
            ['org' => 'Serikat Pekerja Nasional', 'name' => 'Bpk. Budi Santoso', 'address' => 'Jl. Thamrin No. 78, Jakarta'],
            ['org' => 'PT. Sejahtera Bersama', 'name' => 'Ibu Ratna Sari', 'address' => 'Jl. Rasuna Said No. 56, Jakarta'],
            ['org' => 'Kementerian Ketenagakerjaan', 'name' => 'Bpk. Dedi Kurniawan', 'address' => 'Jl. MT. Haryono No. 89, Jakarta'],
            ['org' => 'Konfederasi Serikat Pekerja', 'name' => 'Ibu Dewi Lestari', 'address' => 'Jl. Diponegoro No. 23, Jakarta'],
        ];

        return $orgs[array_rand($orgs)];
    }

    private function generateRevisionNote(): string
    {
        $notes = [
            'Mohon diperbaiki paragraf ke-2 mengenai data keanggotaan.',
            'Harap tambahkan data pendukung pada lampiran.',
            'Perlu klarifikasi lebih lanjut mengenai poin ke-3.',
            'Tolong sesuaikan dengan format standar surat keluar.',
            'Mohon direvisi bagian kesimpulan dan saran.',
            'Perubahan diperlukan pada data referensi.',
            'Mohon lengkapi informasi mengenai pihak terkait.',
            'Harap ditinjau kembali substansi surat.',
            'Perlu penambahan dasar hukum yang relevan.',
            'Mohon disesuaikan dengan ketentuan organisasi.',
        ];

        return $notes[array_rand($notes)];
    }

    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('✅ LetterSeeder completed successfully!');
        $this->command->newLine();

        // Overall statistics
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Letters', Letter::count()],
                ['Letter Categories Used', Letter::distinct('letter_category_id')->count()],
                ['Urgency Levels', Letter::distinct('urgency')->count()],
                ['Confidentiality Levels', Letter::distinct('confidentiality')->count()],
                ['Recipient Types', Letter::distinct('to_type')->count()],
            ]
        );

        $this->command->newLine();

        // Status distribution
        $statusCounts = Letter::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusRows = array_map(function($status) use ($statusCounts) {
            return [
                $status,
                $statusCounts[$status] ?? 0,
                $this->statusDistribution[$status] ?? 0
            ];
        }, array_keys($this->statusDistribution));

        $this->command->table(
            ['Status', 'Created', 'Expected'],
            $statusRows
        );

        $this->command->newLine();

        // Related data
        $this->command->table(
            ['Related Data', 'Count'],
            [
                ['Letter Revisions', LetterRevision::count()],
                ['Letter Reads', LetterRead::count()],
                ['Letter Attachments', LetterAttachment::count()],
            ]
        );

        $this->command->newLine();

        // Special scenarios
        $slaBreachCount = Letter::where('sla_status', 'breach')->count();
        $dualApprovalCount = Letter::whereNotNull('signer_type_secondary')->count();
        $externalCount = Letter::where('to_type', 'eksternal')->count();
        $duplicateNumbersCount = Letter::selectRaw('letter_number, COUNT(*) as count')
            ->whereNotNull('letter_number')
            ->groupBy('letter_number')
            ->having('count', '>', 1)
            ->count();

        $this->command->table(
            ['Special Scenarios', 'Count', 'Status'],
            [
                ['SLA Breach Cases', $slaBreachCount, $slaBreachCount >= 7 ? '✓' : '⚠'],
                ['Dual Approval Letters', $dualApprovalCount, $dualApprovalCount >= 18 ? '✓' : '⚠'],
                ['External Recipients', $externalCount, $externalCount >= 18 ? '✓' : '⚠'],
                ['Duplicate Letter Numbers', $duplicateNumbersCount, $duplicateNumbersCount === 0 ? '✓' : '✗'],
            ]
        );

        $this->command->newLine();

        // Verification checklist
        $this->command->info('📊 Verification Checklist:');
        $checks = [
            'Total 100 letters created' => Letter::count() === 100,
            'All 7 statuses populated' => count($statusCounts) === 7,
            'SLA breach cases exist' => $slaBreachCount >= 7,
            'Dual approval exists' => $dualApprovalCount >= 18,
            'External recipients exist' => $externalCount >= 18,
            'No duplicate letter numbers' => $duplicateNumbersCount === 0,
            'All non-draft have verification token' => Letter::where('status', '!=', 'draft')
                ->whereNull('verification_token')->count() === 0,
            'Revisions created for revision status' => Letter::where('status', 'revision')
                ->whereHas('revisions')->count() > 0,
        ];

        foreach ($checks as $check => $passed) {
            $this->command->line('   ' . ($passed ? '✓' : '✗') . ' ' . $check);
        }

        $this->command->newLine();
        $this->command->info('✨ Seeding complete! You can now test the Letters module.');
    }
}
