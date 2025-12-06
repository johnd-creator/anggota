<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ActivityLog;
use App\Models\PendingMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ]);

        $remember = (bool)($credentials['remember'] ?? false);

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'login_success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => ['email' => $credentials['email']],
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

        AuditLog::create([
            'user_id' => null,
            'event' => 'login_failed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => ['email' => $credentials['email']],
        ]);

        return back()->withErrors(['email' => 'Email atau password salah']);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $provider = app()->environment('local')
                ? Socialite::driver('google')->stateless()
                : Socialite::driver('google');
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
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'login_success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => ['email' => $email, 'google_id' => $googleUser->getId()],
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
}
