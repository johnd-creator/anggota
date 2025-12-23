<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PendingMember;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);

        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'email';

        // Custom attempt to support company_email
        $user = User::where('email', $credentials['email'])
            ->orWhere('company_email', $credentials['email'])
            ->first();

        if ($user && \Hash::check($credentials['password'], $user->password)) {
            Auth::login($user, $remember);
            $request->session()->regenerate();

            $user = Auth::user();

            $this->auditService->logAuth('login_success', [
                'email' => $credentials['email'],
                'provider' => 'password',
            ]);

            if ($user->role && $user->role->name === 'reguler') {
                $pending = PendingMember::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                        'status' => 'pending',
                    ]
                );
                ActivityLog::create([
                    'actor_id' => $user->id,
                    'action' => 'onboarding_pending_created',
                    'subject_type' => PendingMember::class,
                    'subject_id' => $pending->id,
                    'payload' => ['email' => $pending->email, 'name' => $pending->name],
                ]);
                return redirect()->route('itworks');
            }

            return redirect()->route('dashboard');
        }

        $this->auditService->logAuth('login_failed', [
            'email' => $credentials['email'],
            'provider' => 'password',
        ], null);

        return back()->withErrors(['email' => 'Email atau password salah']);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
            $driver = Socialite::driver('google');
            if (app()->environment('local')) {
                /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
                $driver->stateless();
            }
            $provider = $driver;
            $googleUser = $provider->user();
        } catch (InvalidStateException $e) {
            \Log::error('Google login failed (invalid state)', [
                'request_id' => $request->headers->get('X-Request-Id'),
                'error' => $e->getMessage(),
            ]);
            return redirect('/login')->withErrors([
                'email' => 'Sesi login Google kadaluarsa, silakan coba lagi.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Google login failed', [
                'request_id' => $request->headers->get('X-Request-Id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect('/login')->withErrors([
                'email' => 'Google Login Failed',
            ]);
        }

        $email = $googleUser->getEmail();
        $domain = Str::after($email, '@');

        // Role Mapping Logic
        $roleName = 'reguler'; // Default

        // Example Whitelist Logic (Adjust as needed or move to config)
        $whitelist = [
            'superadmin.com' => 'super_admin', // Example domain
            'adminunit.com' => 'admin_unit',   // Example domain
        ];

        if (array_key_exists($domain, $whitelist)) {
            $roleName = $whitelist[$domain];
        }

        // Find or Create User
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password' => bcrypt(Str::random(16)), // Random password
            ]
        );

        // Assign Role if not already assigned (or update? PRD says "fallback auto-assign Reguler for user baru")
        // If user exists, we might want to keep their role, or update it based on domain?
        // "mapping role berdasarkan domain whitelist (untuk Super Admin/Admin), fallback auto-assign Reguler untuk user baru"
        // Implies we set it on creation. If we want to enforce it on login, we can do that too.
        // Let's set it only if role_id is null to avoid demoting manually promoted users.
        if (!$user->role_id) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->role_id = $role->id;
                $user->save();
            }
        }

        Auth::login($user);

        // Audit Log
        $this->auditService->logAuth('login_success', [
            'email' => $email,
            'provider' => 'google',
            'google_id' => $googleUser->getId(),
        ]);

        // Redirect based on Role
        if ($user->role && $user->role->name === 'reguler') {
            if (Schema::hasTable('pending_members')) {
                $pending = PendingMember::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                        'status' => 'pending',
                    ]
                );
                ActivityLog::create([
                    'actor_id' => $user->id,
                    'action' => 'onboarding_pending_created',
                    'subject_type' => PendingMember::class,
                    'subject_id' => $pending->id,
                    'payload' => ['email' => $pending->email, 'name' => $pending->name],
                ]);
            }
            return redirect()->route('itworks');
        }

        return redirect()->route('dashboard');
    }

    public function redirectToMicrosoft()
    {
        $driver = Socialite::driver('microsoft');
        if (app()->environment('local')) {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver->stateless();
        }
        return $driver->redirect();
    }

    public function handleMicrosoftCallback(Request $request)
    {
        \Log::info('Microsoft Callback Request:', $request->all());
        try {
            $driver = Socialite::driver('microsoft');
            if (app()->environment('local')) {
                /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
                $driver->stateless();
            }
            $msUser = $driver->user();
        } catch (\Throwable $e) {
            \Log::error('Microsoft login failed', ['error' => $e->getMessage()]);
            return redirect('/login')->withErrors(['email' => 'Microsoft Login Failed']);
        }

        $email = $msUser->getEmail();
        $domain = Str::after($email, '@');

        // Enforce Domain Restriction
        if ($domain !== 'plnipservices.co.id') {
            return redirect('/login')->withErrors(['email' => 'Hanya email @plnipservices.co.id yang diizinkan untuk login Microsoft.']);
        }

        // Find User by Microsoft ID OR Company Email OR Standard Email
        $user = User::where('microsoft_id', $msUser->getId())
            ->orWhere('company_email', $email)
            ->first();

        if (!$user) {
            // Check if standard email exists to link
            $user = User::where('email', $email)->first();
        }

        // Auto-Create if not found (Registration)
        if (!$user) {
            $user = User::create([
                'name' => $msUser->getName(),
                'email' => $email,
                'company_email' => $email,
                'microsoft_id' => $msUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'role_id' => Role::where('name', 'reguler')->value('id'), // Default to reguler/onboarding
            ]);

            // Create pending member entry for onboarding flow
            if (Schema::hasTable('pending_members')) {
                $pending = PendingMember::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                        'status' => 'pending',
                    ]
                );
                ActivityLog::create([
                    'actor_id' => $user->id,
                    'action' => 'onboarding_pending_created',
                    'subject_type' => PendingMember::class,
                    'subject_id' => $pending->id,
                    'payload' => ['email' => $pending->email, 'name' => $pending->name],
                ]);
            }
        } else {
            // Update/Link Existing User
            $user->forceFill([
                'microsoft_id' => $msUser->getId(),
                'company_email' => $email, // Ensure company email is set
            ])->save();
        }

        Auth::login($user);

        $this->auditService->logAuth('login_success', [
            'email' => $email,
            'provider' => 'microsoft',
            'microsoft_id' => $msUser->getId(),
        ]);

        // Redirect based on role
        if ($user->role && $user->role->name === 'reguler') {
            return redirect()->route('itworks');
        }

        return redirect()->route('dashboard');
    }
}
