<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MemberResource;
use App\Models\MemberDocument;
use App\Models\MemberUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    use ResolvesMobileMember;

    public function show(Request $request): JsonResponse
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json([
                'member' => null,
                'update_requests' => [],
            ]);
        }

        $member->loadMissing(['unit', 'documents', 'statusLogs', 'unionPosition']);

        return response()->json([
            'member' => new MemberResource($member),
            'update_requests' => MemberUpdateRequest::where('member_id', $member->id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($update) => [
                    'id' => $update->id,
                    'status' => $update->status,
                    'old_data' => $update->old_data,
                    'new_data' => $update->new_data,
                    'notes' => $update->notes,
                    'created_at' => $update->created_at?->toISOString(),
                    'updated_at' => $update->updated_at?->toISOString(),
                ]),
        ]);
    }

    public function requestUpdate(Request $request): JsonResponse
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'regex:/^(\+62|62)8\d{7,11}$|^0[8-9]\d{7,11}$/'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'company_join_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currentData = [
            'address' => $member->address ?? '',
            'phone' => $member->phone ?? '',
            'emergency_contact' => $member->emergency_contact ?? '',
            'company_join_date' => $member->company_join_date?->format('Y-m-d') ?? '',
        ];

        $newData = [
            'address' => $validated['address'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'emergency_contact' => $validated['emergency_contact'] ?? '',
            'company_join_date' => $validated['company_join_date'] ?? '',
        ];

        $hasChanges = collect(['address', 'phone', 'emergency_contact', 'company_join_date'])
            ->contains(fn ($field) => trim((string) $newData[$field]) !== trim((string) $currentData[$field]));

        if (! $hasChanges) {
            return response()->json(['message' => 'Tidak ada perubahan data yang terdeteksi.'], 422);
        }

        $updateRequest = MemberUpdateRequest::where('member_id', $member->id)
            ->where('status', 'pending')
            ->first();

        if ($updateRequest) {
            $updateRequest->update([
                'new_data' => $newData,
                'notes' => $validated['notes'] ?? null,
            ]);
        } else {
            $updateRequest = MemberUpdateRequest::create([
                'member_id' => $member->id,
                'old_data' => $currentData,
                'new_data' => $newData,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'update_request' => $updateRequest,
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $manager = new ImageManager(new Driver);
        $image = $manager->read($request->file('photo'));

        if ($image->width() > 1200 || $image->height() > 1200) {
            $image->scale(1200, 1200);
        }

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $path = 'members/photos/member_'.$member->id.'_'.time().'.jpg';
        Storage::disk('public')->put($path, $image->toJpeg(75));

        $member->forceFill(['photo_path' => $path])->save();

        return response()->json([
            'status' => 'ok',
            'member' => new MemberResource($member->fresh(['unit', 'documents', 'unionPosition'])),
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'type' => ['required', 'string', 'in:surat_pernyataan,ktp'],
        ]);

        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $path = $request->file('file')->store('member_documents', 'public');

        $document = MemberDocument::updateOrCreate(
            ['member_id' => $member->id, 'type' => $request->input('type')],
            [
                'path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
            ]
        );

        return response()->json([
            'status' => 'ok',
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'original_name' => $document->original_name,
                'size' => $document->size,
                'updated_at' => $document->updated_at?->toISOString(),
            ],
        ]);
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
            $member->forceFill(['photo_path' => null])->save();
        }

        return response()->json([
            'status' => 'ok',
            'member' => new MemberResource($member->fresh(['unit', 'documents', 'unionPosition'])),
        ]);
    }
}
