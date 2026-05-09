<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        $user = $this->loadMobileAuthUserByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->auditService->logAuth('login_failed', [
                'email' => $credentials['email'],
                'provider' => 'mobile_password',
            ], null);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        return $this->issueMobileLoginResponse($user, $credentials['device_name'] ?? 'flutter-mobile', [
            'email' => $user->email,
            'provider' => 'mobile_password',
        ]);
    }

    public function googleToken(Request $request, GoogleIdTokenVerifier $googleIdTokenVerifier): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'server_auth_code' => ['nullable', 'string'],
        ]);

        try {
            $payload = $googleIdTokenVerifier->verify($data['id_token']);
        } catch (ValidationException $e) {
            $this->auditService->logAuth('login_failed', [
                'provider' => 'mobile_google',
                'reason' => 'invalid_id_token',
            ]);

            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Terjadi kesalahan internal saat memverifikasi login Google.',
            ], 500);
        }

        $user = $this->loadMobileAuthUserByEmail((string) $payload['email']);

        if (! $user) {
            $this->auditService->logAuth('login_failed', [
                'email' => $payload['email'],
                'provider' => 'mobile_google',
                'reason' => 'user_not_registered',
            ]);

            return response()->json([
                'message' => 'Akun Google ini belum terdaftar di sistem.',
            ], 422);
        }

        $user = $this->hydrateMobileAuthUser($user);

        if (! $user->role || $user->role->name === 'reguler') {
            $this->auditService->logAuth('login_failed', [
                'email' => $user->email,
                'provider' => 'mobile_google',
                'reason' => 'mobile_access_denied',
            ], $user->id);

            return response()->json([
                'message' => 'Login Google tidak dapat digunakan untuk akun ini.',
            ], 403);
        }

        if ($user->linkedMember && $user->linkedMember->status !== 'aktif') {
            $this->auditService->logAuth('login_failed', [
                'email' => $user->email,
                'provider' => 'mobile_google',
                'reason' => 'member_not_active',
            ], $user->id);

            return response()->json([
                'message' => 'Login Google tidak dapat digunakan untuk akun ini.',
            ], 403);
        }

        return $this->issueMobileLoginResponse($user, $data['device_name'], [
            'email' => $user->email,
            'provider' => 'mobile_google',
            'role_name' => $user->role?->name,
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
        $user = $this->hydrateMobileAuthUser($request->user());

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    protected function issueMobileLoginResponse(User $user, string $deviceName, array $auditPayload): JsonResponse
    {
        $user = $this->hydrateMobileAuthUser($user);
        $token = $user->createToken($deviceName)->plainTextToken;

        $this->auditService->logAuth('login_success', $auditPayload, $user->id);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    protected function hydrateMobileAuthUser(User $user): User
    {
        $user->loadMissing(['role', 'linkedMember.unit', 'linkedMember.documents', 'linkedMember.unionPosition']);
        $user->setRelation('linkedMember', $this->mobileMember($user));

        return $user;
    }

    protected function loadMobileAuthUserByEmail(string $email): ?User
    {
        return User::with(['role', 'linkedMember.unit', 'linkedMember.documents', 'linkedMember.unionPosition'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }
}
