<?php

namespace App\Http\Controllers\Letter;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Store attachment(s) for a letter.
     */
    public function store(Request $request, Letter $letter)
    {
        // Only creator can add attachments, and only for draft/revision
        if ($letter->creator_user_id !== $request->user()->id) {
            abort(403, 'Hanya pembuat surat yang dapat menambah lampiran.');
        }

        if (!in_array($letter->status, ['draft', 'revision'])) {
            abort(403, 'Lampiran hanya dapat ditambahkan ke surat draft atau revisi.');
        }

        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|mimes:pdf|max:5120', // 5MB
        ]);

        $uploaded = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store("letters/{$letter->id}", 'local');
            $uploaded[] = LetterAttachment::create([
                'letter_id' => $letter->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', count($uploaded) . ' lampiran berhasil diunggah.');
    }

    /**
     * Download an attachment.
     */
    public function download(Letter $letter, LetterAttachment $attachment)
    {
        $this->authorize('view', $letter);

        // Verify attachment belongs to letter
        if ($attachment->letter_id !== $letter->id) {
            abort(404);
        }

        // Mark as read when downloading attachment
        $this->markAsReadIfRecipient($letter, request()->user());

        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'File tidak ditemukan.');

        return response()->download(storage_path('app/' . $attachment->path), $attachment->original_name);
    }

    /**
     * Mark letter as read if user is a recipient.
     */
    protected function markAsReadIfRecipient(Letter $letter, $user): void
    {
        if (!$user)
            return;

        $isRecipient = false;
        if ($letter->to_type === 'member' && $letter->to_member_id) {
            $isRecipient = $user->member_id === $letter->to_member_id;
        } elseif ($letter->to_type === 'unit' && $letter->to_unit_id) {
            $isRecipient = $user->organization_unit_id === $letter->to_unit_id;
        } elseif ($letter->to_type === 'admin_pusat') {
            $isRecipient = in_array($user->role?->name, ['admin_pusat', 'super_admin']);
        }

        if ($isRecipient) {
            \App\Models\LetterRead::firstOrCreate([
                'letter_id' => $letter->id,
                'user_id' => $user->id,
            ]);
        }
    }
}
