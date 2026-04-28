<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ResolvesMobileMember;

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::with(['role', 'linkedMember.unit', 'linkedMember.documents', 'linkedMember.unionPosition'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->auditService->logAuth('login_failed', [
                'email' => $credentials['email'],
                'provider' => 'mobile_password',
            ], null);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        $member = $this->mobileMember($user);
        $user->setRelation('linkedMember', $member);

        $token = $user->createToken($credentials['device_name'] ?? 'flutter-mobile')->plainTextToken;

        $this->auditService->logAuth('login_success', [
            'email' => $user->email,
            'provider' => 'mobile_password',
        ], $user->id);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'linkedMember.unit', 'linkedMember.documents', 'linkedMember.unionPosition']);
        $user->setRelation('linkedMember', $this->mobileMember($user));

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
