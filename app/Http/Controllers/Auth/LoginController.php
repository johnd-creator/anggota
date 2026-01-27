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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class LoginController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);

        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'email';

        // Login hanya dengan email utama (Gmail), bukan dengan company_email (PLN)
        $user = User::where('email', $credentials['email'])->first();

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

        // Domain Whitelist for Role Assignment (Adjust as needed)
        $whitelist = [
            'waspro.com' => 'super_admin',    // Production domain
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
        if (! $user->role_id) {
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
            // Check if user has rejected pending member, delete it FIRST
            // This must happen before checking existing member to allow retry
            if (Schema::hasTable('pending_members')) {
                $rejectedPending = PendingMember::where('user_id', $user->id)
                    ->where('status', 'rejected')
                    ->first();

                if ($rejectedPending) {
                    // Log deletion
                    ActivityLog::create([
                        'actor_id' => $user->id,
                        'action' => 'auto_delete_rejected_pending_member',
                        'subject_type' => PendingMember::class,
                        'subject_id' => $rejectedPending->id,
                        'payload' => [
                            'email' => $user->email,
                            'previous_status' => 'rejected',
                            'reason' => $rejectedPending->notes,
                            'provider' => 'google',
                        ],
                    ]);

                    // Delete rejected pending member
                    $rejectedPending->delete();
                }
            }

            // Check if member already exists with this email
            $existingMember = \App\Models\Member::where('email', $user->email)->first();

            // If not found and user has company_email, try searching with company_email
            if (! $existingMember && $user->company_email) {
                $existingMember = \App\Models\Member::where('email', $user->company_email)->first();

                if ($existingMember) {
                    // Update member.email to user.email (personal_email)
                    $oldEmail = $existingMember->email;
                    $existingMember->email = $user->email;
                    $existingMember->save();

                    ActivityLog::create([
                        'actor_id' => $user->id,
                        'action' => 'member_email_updated_to_personal_email',
                        'subject_type' => \App\Models\Member::class,
                        'subject_id' => $existingMember->id,
                        'payload' => [
                            'old_email' => $oldEmail,
                            'new_email' => $user->email,
                            'provider' => 'google',
                        ],
                    ]);
                }
            }

            if ($existingMember) {
                // Link user to existing member
                $user->assignMember($existingMember);

                // Log linkage
                ActivityLog::create([
                    'actor_id' => $user->id,
                    'action' => 'user_linked_to_existing_member',
                    'subject_type' => \App\Models\Member::class,
                    'subject_id' => $existingMember->id,
                    'payload' => [
                        'email' => $user->email,
                        'member_id' => $existingMember->id,
                        'nra' => $existingMember->nra,
                        'provider' => 'google',
                    ],
                ]);

                return redirect()->route('dashboard');
            }

            // No member found, create onboarding entry
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

        if (! $user) {
            // Check if standard email exists to link
            $user = User::where('email', $email)->first();
        }

        // Auto-Create if not found (Registration)
        if (! $user) {
            $user = User::create([
                'name' => $msUser->getName(),
                'email' => $email,
                'company_email' => $email,
                'microsoft_id' => $msUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'role_id' => Role::where('name', 'reguler')->value('id'), // Default to reguler/onboarding
            ]);
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
            // Check if user has rejected pending member, delete it FIRST
            // This must happen before checking existing member to allow retry
            if (Schema::hasTable('pending_members')) {
                $rejectedPending = PendingMember::where('user_id', $user->id)
                    ->where('status', 'rejected')
                    ->first();

                if ($rejectedPending) {
                    // Log deletion
                    ActivityLog::create([
                        'actor_id' => $user->id,
                        'action' => 'auto_delete_rejected_pending_member',
                        'subject_type' => PendingMember::class,
                        'subject_id' => $rejectedPending->id,
                        'payload' => [
                            'email' => $user->email,
                            'previous_status' => 'rejected',
                            'reason' => $rejectedPending->notes,
                            'provider' => 'microsoft',
                        ],
                    ]);

                    // Delete rejected pending member
                    $rejectedPending->delete();
                }
            }

            // Check if member already exists with this email
            $existingMember = \App\Models\Member::where('email', $user->email)->first();

            if ($existingMember) {
                // Link user to existing member
                $user->assignMember($existingMember);

                // Log linkage
                ActivityLog::create([
                    'actor_id' => $user->id,
                    'action' => 'user_linked_to_existing_member',
                    'subject_type' => \App\Models\Member::class,
                    'subject_id' => $existingMember->id,
                    'payload' => [
                        'email' => $user->email,
                        'member_id' => $existingMember->id,
                        'nra' => $existingMember->nra,
                        'provider' => 'microsoft',
                    ],
                ]);

                return redirect()->route('dashboard');
            }

            // No member found, create onboarding entry
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
