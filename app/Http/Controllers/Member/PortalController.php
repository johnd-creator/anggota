<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PortalController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $member = Member::with(['unit', 'documents', 'statusLogs'])->where('user_id', $user->id)->first();

        return Inertia::render('Member/Portal', [
            'member' => $member,
            'updateRequests' => $member ? MemberUpdateRequest::where('member_id', $member->id)->latest()->limit(10)->get() : [],
            'notifications' => $user ? \App\Models\Notification::where('notifiable_type', \App\Models\User::class)->where('notifiable_id', $user->id)->latest()->limit(10)->get() : [],
        ]);
    }

    public function requestUpdate(Request $request)
    {
        $user = Auth::user();
        $member = Member::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'address' => 'nullable|string',
            'phone' => ['nullable', 'regex:/^(\+62|62)8\d{7,11}$|^0[8-9]\d{7,11}$/'],
            'emergency_contact' => 'nullable|string|max:100',
            'company_join_date' => 'nullable|date',
        ]);

        // Check if there are actual changes from current member data
        $currentData = [
            'address' => $member->address ?? '',
            'phone' => $member->phone ?? '',
            'emergency_contact' => $member->emergency_contact ?? '',
            'company_join_date' => $member->company_join_date ? $member->company_join_date->format('Y-m-d') : '',
        ];

        $newData = [
            'address' => $validated['address'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'emergency_contact' => $validated['emergency_contact'] ?? '',
            'company_join_date' => $validated['company_join_date'] ?? '',
        ];

        $hasChanges = false;
        foreach (['address', 'phone', 'emergency_contact', 'company_join_date'] as $field) {
            if (trim($newData[$field]) !== trim($currentData[$field])) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return redirect()->back()->with('error', 'Tidak ada perubahan data yang terdeteksi');
        }

        // Check for existing pending request
        $existingRequest = MemberUpdateRequest::where('member_id', $member->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            // Update existing pending request
            $existingRequest->update([
                'new_data' => $validated,
                'notes' => $request->input('notes'),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Permintaan perubahan berhasil diperbarui');
        }

        // Create new request if no pending exists
        MemberUpdateRequest::create([
            'member_id' => $member->id,
            'old_data' => [
                'address' => $member->address,
                'phone' => $member->phone,
                'emergency_contact' => $member->emergency_contact,
                'company_join_date' => $member->company_join_date ? $member->company_join_date->format('Y-m-d') : null,
            ],
            'new_data' => $validated,
            'status' => 'pending',
            'notes' => $request->input('notes'),
        ]);

        return redirect()->back()->with('success', 'Permintaan perubahan dikirim');
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:2048',
            'type' => 'required|string|in:surat_pernyataan,ktp',
        ]);

        $user = Auth::user();
        $member = Member::where('user_id', $user->id)->firstOrFail();

        $path = $request->file('file')->store('member_documents', 'public');

        MemberDocument::updateOrCreate(
            ['member_id' => $member->id, 'type' => $request->type],
            [
                'path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
            ]
        );

        return redirect()->back()->with('success', 'Dokumen berhasil diupload');
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = Auth::user();
        $member = Member::where('user_id', $user->id)->firstOrFail();

        $manager = new ImageManager(new Driver);
        $image = $manager->read($request->file('photo'));

        if ($image->width() > 1200 || $image->height() > 1200) {
            $image->scale(1200, 1200);
        }

        $filename = 'member_'.$member->id.'_'.time().'.jpg';

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $path = 'members/photos/'.$filename;
        Storage::disk('public')->put($path, $image->toJpeg(75));

        $member->photo_path = $path;
        $member->save();

        return redirect()->back()->with('success', 'Foto berhasil disimpan');
    }

    public function deletePhoto(Request $request)
    {
        $user = Auth::user();
        $member = Member::where('user_id', $user->id)->firstOrFail();

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
            $member->photo_path = null;
            $member->save();
        }

        return redirect()->back()->with('success', 'Foto berhasil dihapus');
    }
}
