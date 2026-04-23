<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

class FixMemberKtaPrefixes extends Command
{
    protected $signature = 'members:fix-kta-prefix
        {--apply : Persist the corrected KTA numbers}
        {--resequence : Recalculate sequence_number per organization before fixing KTA}
        {--member-id=* : Limit to one or more member IDs; not supported with --resequence}';

    protected $description = 'Audit and optionally fix member KTA numbers so they match unit code and organization-wide sequence';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $resequence = (bool) $this->option('resequence');
        $memberIds = collect($this->option('member-id'))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($resequence && $memberIds->isNotEmpty()) {
            $this->error('--resequence tidak dapat digabung dengan --member-id karena sequence harus dihitung per organisasi utuh.');

            return self::FAILURE;
        }

        $checked = 0;
        $fixed = 0;
        $skipped = 0;

        if (! $apply) {
            $this->warn('Dry-run mode. Re-run with --apply to persist changes.');
        }

        if ($resequence) {
            return $this->handleResequence($apply);
        }

        Member::query()
            ->with('unit:id,code,name')
            ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $memberIds))
            ->orderBy('id')
            ->chunkById(200, function ($members) use ($apply, &$checked, &$fixed, &$skipped) {
                foreach ($members as $member) {
                    $checked++;

                    $targetKta = $this->targetKta($member);
                    if (! $targetKta) {
                        $skipped++;
                        $this->line("SKIP member {$member->id}: unit/code/join data tidak valid untuk KTA.");
                        continue;
                    }

                    if ($member->kta_number === $targetKta) {
                        continue;
                    }

                    $conflict = Member::query()
                        ->where('kta_number', $targetKta)
                        ->whereKeyNot($member->id)
                        ->first(['id']);

                    if ($conflict) {
                        $skipped++;
                        $this->line("SKIP member {$member->id}: target {$targetKta} sudah dipakai member {$conflict->id}.");
                        continue;
                    }

                    $oldKta = $member->kta_number ?: '(empty)';
                    $this->line(($apply ? 'FIX' : 'WOULD FIX') . " member {$member->id}: {$oldKta} -> {$targetKta}");

                    if ($apply) {
                        $member->forceFill(['kta_number' => $targetKta])->save();
                    }

                    $fixed++;
                }
            });

        $this->info("Checked: {$checked}; " . ($apply ? 'Fixed' : 'Would fix') . ": {$fixed}; Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function handleResequence(bool $apply): int
    {
        $checked = 0;
        $fixed = 0;
        $skipped = 0;
        $targets = [];
        $targetKtas = [];

        $members = Member::query()
            ->with('unit:id,code,name')
            ->orderBy('organization_unit_id')
            ->orderBy('id')
            ->get();

        $sequenceByUnit = [];
        foreach ($members as $member) {
            $checked++;
            $unitId = (int) $member->organization_unit_id;
            $sequenceByUnit[$unitId] = ($sequenceByUnit[$unitId] ?? 0) + 1;
            $targetSequence = $sequenceByUnit[$unitId];
            $targetKta = $this->targetKta($member, $targetSequence);

            if (! $targetKta) {
                $skipped++;
                $this->line("SKIP member {$member->id}: unit/code/join data tidak valid untuk KTA.");
                continue;
            }

            if (isset($targetKtas[$targetKta])) {
                $skipped++;
                $this->line("SKIP member {$member->id}: target {$targetKta} duplikat dengan member {$targetKtas[$targetKta]} dalam rencana resequence.");
                continue;
            }

            $targetKtas[$targetKta] = $member->id;
            $targets[$member->id] = [
                'member' => $member,
                'sequence_number' => $targetSequence,
                'kta_number' => $targetKta,
            ];
        }

        foreach ($targets as $memberId => $target) {
            $member = $target['member'];
            $targetSequence = $target['sequence_number'];
            $targetKta = $target['kta_number'];

            $conflict = Member::query()
                ->where('kta_number', $targetKta)
                ->whereKeyNot($memberId)
                ->whereNotIn('id', array_keys($targets))
                ->first(['id']);

            if ($conflict) {
                $skipped++;
                $this->line("SKIP member {$memberId}: target {$targetKta} sudah dipakai member {$conflict->id}.");
                continue;
            }

            if ($member->kta_number === $targetKta && (int) $member->sequence_number === $targetSequence) {
                continue;
            }

            $oldKta = $member->kta_number ?: '(empty)';
            $this->line(($apply ? 'FIX' : 'WOULD FIX') . " member {$memberId}: {$oldKta} / seq {$member->sequence_number} -> {$targetKta} / seq {$targetSequence}");

            if ($apply) {
                $member->forceFill([
                    'kta_number' => $targetKta,
                    'sequence_number' => $targetSequence,
                ])->save();
            }

            $fixed++;
        }

        $this->info("Checked: {$checked}; " . ($apply ? 'Fixed' : 'Would fix') . ": {$fixed}; Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function targetKta(Member $member, ?int $sequenceNumber = null): ?string
    {
        $unitCode = strtoupper(trim((string) $member->unit?->code));

        if (! preg_match('/^\d{3}$/', $unitCode)) {
            return null;
        }

        $sequenceNumber ??= (int) $member->sequence_number;

        if (! $member->join_year || ! $sequenceNumber) {
            return null;
        }

        $yearTwoDigit = (int) substr((string) $member->join_year, -2);

        return sprintf('%s-SPPIPS-%02d%03d', $unitCode, $yearTwoDigit, $sequenceNumber);
    }
}
