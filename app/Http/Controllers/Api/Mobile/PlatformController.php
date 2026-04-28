<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    use MobileApiHelpers;

    public function storeDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'device_token' => ['required', 'string', 'max:512'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = MobileDevice::updateOrCreate(
            ['device_token' => $data['device_token']],
            $data + ['user_id' => $request->user()->id, 'last_seen_at' => now()]
        );

        return response()->json(['status' => 'ok', 'device' => $device], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteDevice(Request $request, MobileDevice $device): JsonResponse
    {
        abort_unless((int) $device->user_id === (int) $request->user()->id, 404);
        $device->delete();

        return $this->ok();
    }

    public function googleToken(): JsonResponse
    {
        return response()->json([
            'message' => 'Mobile Google token exchange belum diaktifkan. Tambahkan verifier id_token server-side sebelum endpoint ini dipakai produksi.',
            'provider' => 'google',
        ], 501);
    }

    public function microsoftToken(): JsonResponse
    {
        return response()->json([
            'message' => 'Mobile Microsoft token exchange belum diaktifkan. Tambahkan verifier id_token server-side sebelum endpoint ini dipakai produksi.',
            'provider' => 'microsoft',
        ], 501);
    }
}
