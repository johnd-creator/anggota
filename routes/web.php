<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Auth/Login');
});

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

Route::get('auth/google', [LoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->middleware('throttle:10,1');
Route::get('auth/microsoft', [LoginController::class, 'redirectToMicrosoft'])->name('auth.microsoft');
Route::get('auth/microsoft/callback', [LoginController::class, 'handleMicrosoftCallback'])->middleware('throttle:10,1');

// Public letter verification (QR code scan)
Route::get('letters/verify/{token}', [\App\Http\Controllers\LetterController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('letters.verify');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');


    Route::get('/itworks', function () {
        return Inertia::render('ItWorks');
    })->name('itworks');

    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->middleware('auth')->name('audit-logs');

    Route::get('/ui/components', function () {
        return Inertia::render('UI/ComponentsGallery');
    })->middleware('role:super_admin')->name('ui.components');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read_all');
    Route::post('/notifications/{id}/unread', [\App\Http\Controllers\NotificationController::class, 'markUnread'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.unread');
    Route::post('/notifications/read-batch', [\App\Http\Controllers\NotificationController::class, 'markReadBatch'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read_batch');
    Route::get('/notifications/recent', [\App\Http\Controllers\NotificationController::class, 'recent'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.recent');

    Route::controller(\App\Http\Controllers\SettingsController::class)->group(function () {
        Route::get('/settings', 'index')->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('settings.index');
        Route::patch('/settings/notifications', 'updateNotifications')->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('settings.notification_prefs');
        Route::patch('/settings/profile', 'updateProfile')->middleware('auth')->name('settings.profile.update');
        Route::patch('/settings/password', 'updatePassword')->middleware('auth')->name('settings.password.update');
        Route::get('/settings/sessions', 'getSessions')->middleware('auth')->name('settings.sessions');
    });

    Route::post('/settings/sessions/revoke-others', function (\Illuminate\Http\Request $request) {
        $userId = Auth::id();
        $currentId = $request->session()->getId();

        $count = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentId)
            ->delete();

        if ($count > 0) {
            \App\Models\ActivityLog::create([
                'actor_id' => $userId,
                'action' => 'revoke_other_sessions',
                'subject_type' => \App\Models\User::class,
                'subject_id' => $userId,
                'payload' => ['count' => $count],
                'event_category' => 'auth',
            ]);
        }

        return response()->json(['status' => 'ok', 'count' => $count]);
    })->middleware('auth')->name('settings.sessions.revoke_others');

    Route::get('/help', function () {
        return Inertia::render('Help/Index');
    })->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('help.index');

    // root path handled above (guest: login page, auth: dashboard)

    Route::prefix('reports')->middleware(['feature:reports', 'role:super_admin,admin_pusat,admin_unit,bendahara'])->group(function () {
        // UI pages (existing)
        Route::get('growth', [\App\Http\Controllers\ReportController::class, 'growth'])->name('reports.growth');
        Route::get('mutations', [\App\Http\Controllers\ReportController::class, 'mutations'])->name('reports.mutations');
        // New Reports UI v2
        Route::get('members', [\App\Http\Controllers\ReportController::class, 'members'])->name('reports.members');
        Route::get('aspirations', [\App\Http\Controllers\ReportController::class, 'aspirations'])->name('reports.aspirations');
        Route::get('dues', [\App\Http\Controllers\ReportController::class, 'dues'])->name('reports.dues');
        Route::get('finance', [\App\Http\Controllers\ReportController::class, 'finance'])->name('reports.finance');

        // Legacy per-type export (kept for backward compatibility)
        Route::controller(\App\Http\Controllers\ReportsExportController::class)->group(function () {
            // Legacy per-type export (kept for backward compatibility)
            Route::post('{type}/export', 'legacyExport')->name('reports.export');

            // New unified CSV export endpoint (R1)
            Route::get('/export', 'export')
                ->name('reports.export.unified') // Renaming to avoid name collision with legacy if names were same, though strict REST allows same name for different methods potentially? No, route names must be unique.
                ->middleware('throttle:10,1');
        });

        // Export Status
        Route::get('/export/status', \App\Http\Controllers\ReportsExportStatusController::class)
            ->name('reports.export.status');
    });

    // Backward-compatible redirect: Reports CSV docs moved to Help Center.
    Route::get('/docs/reports/csv', function () {
        return redirect('/docs/help/reports-csv');
    })->middleware(['auth', 'role:super_admin,admin_pusat,admin_unit,bendahara'])->name('docs.reports.csv');


    Route::get('/ops', function () {
        $latest = null;
        try {
            $disk = Storage::disk('local');
            $files = $disk->files('backups');
            $latest = collect($files)->map(function ($f) use ($disk) {
                return [
                    'path' => $f,
                    'modified' => $disk->lastModified($f),
                    'size' => $disk->size($f),
                ];
            })->sortByDesc('modified')->first();
        } catch (\Throwable $e) {
            $latest = null;
        }
        $lastBackup = $latest ? [
            'path' => $latest['path'],
            'modified_at' => date('c', $latest['modified']),
            'size' => $latest['size'],
        ] : null;

        return Inertia::render('Ops/Center', [
            'last_backup' => $lastBackup,
        ]);
    })->middleware('role:super_admin')->name('ops.center');

    Route::get('/docs/ops/backup-dr', function () {
        $content = '';
        try {
            $content = file_get_contents(base_path('docs/ops/backup-dr.md')) ?: '';
        } catch (\Throwable $e) {
            $content = 'Dokumen tidak ditemukan.';
        }
        return Inertia::render('Docs/Viewer', ['title' => 'Backup & DR Runbook', 'content' => $content]);
    })->middleware('role:super_admin')->name('docs.ops.backup');

    Route::get('/docs/release/launch-checklist', function () {
        $content = '';
        try {
            $content = file_get_contents(base_path('docs/release/launch-checklist.md')) ?: '';
        } catch (\Throwable $e) {
            $content = 'Dokumen tidak ditemukan.';
        }
        return Inertia::render('Docs/Viewer', ['title' => 'Launch Checklist', 'content' => $content]);
    })->middleware('role:super_admin')->name('docs.release.launch');

    Route::get('/docs/security/review', function () {
        $path = base_path('docs/security/security-review.md');
        $content = '';
        $updatedAt = null;
        try {
            $content = file_get_contents($path) ?: '';
            $updatedAt = filemtime($path) ? date('c', filemtime($path)) : null;
        } catch (\Throwable $e) {
            $content = 'Dokumen tidak ditemukan.';
        }
        return Inertia::render('Docs/Viewer', ['title' => 'Security Review', 'content' => $content, 'updated_at' => $updatedAt]);
    })->middleware('role:super_admin')->name('docs.security.review');

    Route::get('/docs/help/{slug}', function ($slug) {
        $path = base_path('docs/help/' . $slug . '.md');
        $content = is_file($path) ? file_get_contents($path) : 'Artikel tidak ditemukan.';
        return Inertia::render('Docs/Viewer', ['title' => 'Bantuan: ' . ucfirst($slug), 'content' => $content]);
    })->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('docs.help.show');

    // Admin Routes
    Route::prefix('admin')->name('admin.')->middleware('role:super_admin,admin_unit,admin_pusat')->group(function () {
        Route::resource('units', \App\Http\Controllers\Admin\OrganizationUnitController::class);

        Route::resource('members', \App\Http\Controllers\Admin\MemberController::class)
            ->whereNumber('member');
        Route::get('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])
            ->whereNumber('user')
            ->name('users.show');
        Route::resource('union-positions', \App\Http\Controllers\Admin\UnionPositionController::class)->middleware('role:super_admin')->names('union_positions');
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class)->middleware('role:super_admin');
        Route::post('roles/{role}/assign', [\App\Http\Controllers\Admin\RoleController::class, 'assign'])->middleware('role:super_admin')->name('roles.assign');
        Route::delete('roles/{role}/users/{user}', [\App\Http\Controllers\Admin\RoleController::class, 'removeUser'])->middleware('role:super_admin')->name('roles.remove_user');

        // Admin Aspirations (Categories & Main)
        Route::resource('aspiration-categories', \App\Http\Controllers\Admin\AspirationCategoryController::class)->middleware('role:super_admin');
        Route::resource('letter-categories', \App\Http\Controllers\Admin\LetterCategoryController::class)->middleware('role:super_admin');
        Route::resource('letter-approvers', \App\Http\Controllers\Admin\LetterApproverController::class)->middleware('role:super_admin,admin_pusat');
        Route::post('letter-approvers/{letter_approver}/toggle-active', [\App\Http\Controllers\Admin\LetterApproverController::class, 'toggleActive'])->middleware('role:super_admin,admin_pusat')->name('letter-approvers.toggle-active');
        Route::get('aspirations', [\App\Http\Controllers\Admin\AspirationController::class, 'index'])->name('aspirations.index');
        Route::get('aspirations/{aspiration}', [\App\Http\Controllers\Admin\AspirationController::class, 'show'])->name('aspirations.show');
        Route::patch('aspirations/{aspiration}/status', [\App\Http\Controllers\Admin\AspirationController::class, 'updateStatus'])->name('aspirations.update_status');
        Route::post('aspirations/{aspiration}/merge', [\App\Http\Controllers\Admin\AspirationController::class, 'merge'])->name('aspirations.merge');

        // Admin Sessions
        Route::get('sessions', [\App\Http\Controllers\Admin\UserSessionController::class, 'index'])->middleware('role:super_admin')->name('sessions.index');
        Route::delete('sessions/{session}', [\App\Http\Controllers\Admin\UserSessionController::class, 'destroy'])->middleware('role:super_admin')->name('sessions.destroy');
        Route::delete('sessions/user/{user}', [\App\Http\Controllers\Admin\UserSessionController::class, 'destroyUserSessions'])->middleware('role:super_admin')->name('sessions.destroy_user');

        Route::get('activity-logs', function () {
            $logs = \App\Models\ActivityLog::latest()->paginate(20)->withQueryString();
            return Inertia::render('Admin/ActivityLogs', ['logs' => $logs]);
        })->middleware('role:super_admin')->name('activity-logs.index');
        Route::get('onboarding', [\App\Http\Controllers\Admin\OnboardingController::class, 'index'])->name('onboarding.index');
        Route::post('onboarding/{pending}/approve', [\App\Http\Controllers\Admin\OnboardingController::class, 'approve'])->name('onboarding.approve');
        Route::post('onboarding/{pending}/reject', [\App\Http\Controllers\Admin\OnboardingController::class, 'reject'])->name('onboarding.reject');
        Route::get('updates', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'index'])->name('updates.index');
        Route::post('updates/{update_request}/approve', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'approve'])->name('updates.approve');
        Route::post('updates/{update_request}/reject', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'reject'])->name('updates.reject');
        Route::get('mutations', [\App\Http\Controllers\Admin\MutationController::class, 'index'])->name('mutations.index');
        Route::get('mutations/create', [\App\Http\Controllers\Admin\MutationController::class, 'create'])->name('mutations.create');
        Route::post('mutations', [\App\Http\Controllers\Admin\MutationController::class, 'store'])->name('mutations.store');
        Route::get('mutations/{mutation}', [\App\Http\Controllers\Admin\MutationController::class, 'show'])->name('mutations.show');
        Route::post('mutations/{mutation}/approve', [\App\Http\Controllers\Admin\MutationController::class, 'approve'])->middleware(['role:super_admin', 'throttle:10,1'])->name('mutations.approve');
        Route::post('mutations/{mutation}/reject', [\App\Http\Controllers\Admin\MutationController::class, 'reject'])->middleware(['role:super_admin', 'throttle:10,1'])->name('mutations.reject');
        Route::post('mutations/{mutation}/cancel', [\App\Http\Controllers\Admin\MutationController::class, 'cancel'])->name('mutations.cancel');

        Route::get('members-export', [\App\Http\Controllers\ReportsExportController::class, 'adminMembersExport'])->name('members.export');

        Route::get('members/import', [\App\Http\Controllers\Admin\MemberImportController::class, 'index'])->middleware('role:super_admin,admin_unit,admin_pusat')->name('members.import.index');
        Route::get('members/import/template', [\App\Http\Controllers\Admin\MemberImportController::class, 'template'])->middleware('role:super_admin,admin_unit,admin_pusat')->name('members.import.template');
        Route::post('members/import/preview', [\App\Http\Controllers\Admin\MemberImportController::class, 'preview'])->middleware('role:super_admin,admin_unit,admin_pusat')->name('members.import.preview');
        Route::post('members/import/{batch}/commit', [\App\Http\Controllers\Admin\MemberImportController::class, 'commit'])->middleware('role:super_admin,admin_unit,admin_pusat')->name('members.import.commit');
        Route::get('members/import/{batch}/errors', [\App\Http\Controllers\Admin\MemberImportController::class, 'downloadErrors'])->middleware('role:super_admin,admin_unit,admin_pusat')->name('members.import.errors');
        Route::post('members/import', [\App\Http\Controllers\Admin\MemberImportController::class, 'store'])->middleware('role:admin_unit')->name('members.import');

        Route::get('mutations/export', [\App\Http\Controllers\ReportsExportController::class, 'adminMutationsExport'])->name('admin.mutations.export');
    });

    Route::prefix('finance')->name('finance.')->middleware(['feature:finance', 'role:super_admin,admin_unit,bendahara'])->group(function () {
        Route::get('categories', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('categories/export', [\App\Http\Controllers\Finance\FinanceCategoryController::class, 'export'])->name('categories.export');

        Route::get('ledgers', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'index'])->name('ledgers.index');
        Route::get('ledgers/create', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'create'])->name('ledgers.create');
        Route::post('ledgers', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'store'])->name('ledgers.store');
        Route::get('ledgers/export', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'export'])->name('ledgers.export');
        Route::get('ledgers/{ledger}/edit', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'edit'])->name('ledgers.edit');
        Route::put('ledgers/{ledger}', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'update'])->name('ledgers.update');
        Route::delete('ledgers/{ledger}', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'destroy'])->name('ledgers.destroy');

        // Workflow approval routes - admin_unit only
        Route::post('ledgers/{ledger}/approve', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'approve'])->name('ledgers.approve');
        Route::post('ledgers/{ledger}/reject', [\App\Http\Controllers\Finance\FinanceLedgerController::class, 'reject'])->name('ledgers.reject');

        // Dues payment routes
        Route::get('dues', [\App\Http\Controllers\Finance\FinanceDuesController::class, 'index'])->name('dues.index');
        Route::post('dues/update', [\App\Http\Controllers\Finance\FinanceDuesController::class, 'update'])->name('dues.update');
        Route::post('dues/mass-update', [\App\Http\Controllers\Finance\FinanceDuesController::class, 'massUpdate'])->name('dues.mass_update');
    });

    // Letter Module Routes
    Route::prefix('letters')->name('letters.')->group(function () {
        // Inbox - all receiving roles
        Route::get('inbox', [\App\Http\Controllers\LetterController::class, 'inbox'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('inbox');

        // Approvals - for Ketua/Sekretaris
        Route::get('approvals', [\App\Http\Controllers\LetterController::class, 'approvals'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('approvals');

        // Outbox & Create/Edit - sender roles only
        Route::middleware('role:admin_unit,admin_pusat,super_admin')->group(function () {
            Route::get('outbox', [\App\Http\Controllers\LetterController::class, 'outbox'])->name('outbox');
            Route::get('create', [\App\Http\Controllers\LetterController::class, 'create'])->name('create');
            Route::get('template-render', [\App\Http\Controllers\LetterController::class, 'templateRender'])->name('template-render');
            Route::post('/', [\App\Http\Controllers\LetterController::class, 'store'])->name('store');
            Route::get('{letter}/edit', [\App\Http\Controllers\LetterController::class, 'edit'])->name('edit');
            Route::put('{letter}', [\App\Http\Controllers\LetterController::class, 'update'])->name('update');
            Route::delete('{letter}', [\App\Http\Controllers\LetterController::class, 'destroy'])->name('destroy');
            Route::post('{letter}/submit', [\App\Http\Controllers\Letter\ApprovalController::class, 'submit'])->name('submit');
            Route::post('{letter}/send', [\App\Http\Controllers\Letter\ApprovalController::class, 'send'])->name('send');
            Route::post('{letter}/archive', [\App\Http\Controllers\Letter\ApprovalController::class, 'archive'])->name('archive');
        });

        // Approval actions - for Ketua/Sekretaris (policy check inside)
        Route::post('{letter}/approve', [\App\Http\Controllers\Letter\ApprovalController::class, 'approve'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('approve');
        Route::post('{letter}/revise', [\App\Http\Controllers\Letter\ApprovalController::class, 'revise'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('revise');
        Route::post('{letter}/reject', [\App\Http\Controllers\Letter\ApprovalController::class, 'reject'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('reject');

        // Preview - accessible by viewer (policy check)
        Route::get('{letter}/preview', [\App\Http\Controllers\Letter\ViewController::class, 'preview'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('preview');

        // QR code image for preview
        Route::get('{letter}/qr.png', [\App\Http\Controllers\Letter\ViewController::class, 'qrCode'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('qr');

        // Attachments
        Route::post('{letter}/attachments', [\App\Http\Controllers\Letter\AttachmentController::class, 'store'])
            ->middleware('role:admin_unit,admin_pusat,super_admin')
            ->name('attachments.store');
        Route::get('{letter}/attachments/{attachment}', [\App\Http\Controllers\Letter\AttachmentController::class, 'download'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('attachments.download');

        // PDF export
        Route::get('{letter}/pdf', [\App\Http\Controllers\Letter\ViewController::class, 'pdf'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('pdf');

        // Show - accessible by recipient/creator (policy check)
        Route::get('{letter}', [\App\Http\Controllers\LetterController::class, 'show'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('show');
    });

    // Member search API for letter recipient autocomplete
    Route::get('api/members/search', [\App\Http\Controllers\LetterController::class, 'searchMembers'])
        ->middleware('role:admin_unit,admin_pusat,super_admin')
        ->name('api.members.search');

    Route::get('/member/profile', [\App\Http\Controllers\Member\SelfProfileController::class, 'show'])->middleware('role:anggota,super_admin,admin_unit,bendahara')->name('member.profile');
    Route::get('/member/portal', [\App\Http\Controllers\Member\PortalController::class, 'show'])->middleware('role:anggota,super_admin,admin_unit,bendahara')->name('member.portal');
    Route::post('/member/portal/request-update', [\App\Http\Controllers\Member\PortalController::class, 'requestUpdate'])->middleware(['role:anggota', 'throttle:3,1'])->name('member.request_update');
    Route::post('/member/document/upload', [\App\Http\Controllers\Member\PortalController::class, 'uploadDocument'])->middleware('role:anggota')->name('member.document.upload');
    Route::post('/member/data/export-request', function (\Illuminate\Http\Request $request) {
        \App\Models\ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'gdpr_export_request',
            'subject_type' => \App\Models\User::class,
            'subject_id' => $request->user()->id,
            'payload' => ['channel' => 'portal'],
        ]);
        try {
            $request->user()->notify(new class extends \Illuminate\Notifications\Notification {
                public function via($n)
                {
                    return ['database'];
                }
                public function toDatabase($n)
                {
                    return ['message' => 'Permintaan export data tercatat', 'category' => 'security', 'link' => '/member/portal'];
                }
            });
        } catch (\Throwable $e) {
        }
        return back()->with('success', 'Permintaan export data tercatat');
    })->middleware('role:anggota')->name('member.data.export_request');
    Route::post('/member/data/delete-request', function (\Illuminate\Http\Request $request) {
        \App\Models\ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'gdpr_delete_request',
            'subject_type' => \App\Models\User::class,
            'subject_id' => $request->user()->id,
            'payload' => ['channel' => 'portal'],
        ]);
        try {
            $request->user()->notify(new class extends \Illuminate\Notifications\Notification {
                public function via($n)
                {
                    return ['database'];
                }
                public function toDatabase($n)
                {
                    return ['message' => 'Permintaan penghapusan data tercatat', 'category' => 'security', 'link' => '/member/portal'];
                }
            });
        } catch (\Throwable $e) {
        }
        return back()->with('success', 'Permintaan penghapusan data tercatat');
    })->middleware('role:anggota')->name('member.data.delete_request');
    Route::get('/verify-card/{token}', [\App\Http\Controllers\Member\CardController::class, 'verify'])->name('member.card.verify');
    Route::get('/member/card/pdf', [\App\Http\Controllers\Member\CardPdfController::class, 'download'])->middleware('role:anggota,super_admin,admin_unit,bendahara')->name('member.card.pdf');
    Route::get('/member/card/qr.png', [\App\Http\Controllers\Member\CardController::class, 'qr'])->middleware('role:anggota,super_admin,admin_unit,bendahara')->name('member.card.qr');

    // Member Aspirations
    Route::get('/member/aspirations', [\App\Http\Controllers\Member\AspirationController::class, 'index'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.index');
    Route::get('/member/aspirations/create', [\App\Http\Controllers\Member\AspirationController::class, 'create'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.create');
    Route::post('/member/aspirations', [\App\Http\Controllers\Member\AspirationController::class, 'store'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.store');
    Route::get('/member/aspirations/{aspiration}', [\App\Http\Controllers\Member\AspirationController::class, 'show'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.show');
    Route::post('/member/aspirations/{aspiration}/support', [\App\Http\Controllers\Member\AspirationController::class, 'support'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.support');

    // Member Dues (Iuran Saya)
    Route::get('/member/dues', [\App\Http\Controllers\Member\MemberDuesController::class, 'index'])
        ->middleware(['feature:finance', 'auth'])
        ->name('member.dues');

    // Announcements
    Route::prefix('announcements')->name('announcements.')->middleware('feature:announcements')->group(function () {
        Route::get('/', [\App\Http\Controllers\AnnouncementController::class, 'index'])->name('index');
        Route::get('/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'show'])->name('show');
        Route::post('/{announcement}/dismiss', [\App\Http\Controllers\AnnouncementController::class, 'dismiss'])
            ->middleware('auth')
            ->name('dismiss');
        Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\AnnouncementController::class, 'downloadAttachment'])->name('attachments.download');
    });

    // Admin Announcements Management
    Route::prefix('admin/announcements')->name('admin.announcements.')->middleware(['feature:announcements', 'role:super_admin,admin_pusat,admin_unit'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AnnouncementController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\AnnouncementController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\AnnouncementController::class, 'store'])->name('store');
        Route::get('/{announcement}/edit', [\App\Http\Controllers\Admin\AnnouncementController::class, 'edit'])->name('edit');
        Route::put('/{announcement}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'update'])->name('update');
        Route::delete('/{announcement}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'destroy'])->name('destroy');

        // Quick Actions
        Route::patch('/{announcement}/toggle-active', [\App\Http\Controllers\Admin\AnnouncementController::class, 'toggleActive'])->name('toggle-active');
        Route::patch('/{announcement}/toggle-pin', [\App\Http\Controllers\Admin\AnnouncementController::class, 'togglePin'])->name('toggle-pin');

        // Attachments
        Route::post('/{announcement}/attachments', [\App\Http\Controllers\Admin\AnnouncementAttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{attachment}', [\App\Http\Controllers\Admin\AnnouncementAttachmentController::class, 'destroy'])->name('attachments.destroy');
    });
});

Route::post('/logout', function () {
    // Log logout event before session is invalidated
    $user = Auth::user();
    if ($user) {
        app(\App\Services\AuditService::class)->logAuth('logout', [
            'email' => $user->email,
        ]);
    }

    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Global Search
Route::middleware(['auth'])->group(function () {
    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search.index');
    Route::get('/api/search', [\App\Http\Controllers\SearchController::class, 'api'])
        ->middleware('throttle:15,1')
        ->name('search.api');
});

Route::post('/feedback', function (\Illuminate\Http\Request $request) {
    \App\Models\ActivityLog::create([
        'actor_id' => $request->user()->id,
        'action' => 'feedback_submitted',
        'subject_type' => \App\Models\User::class,
        'subject_id' => $request->user()->id,
        'payload' => ['rating' => (int) $request->input('rating'), 'message' => (string) $request->input('message')],
    ]);
    return back()->with('success', 'Terima kasih atas feedback Anda');
})->middleware('auth')->name('feedback.submit');

// Public API (token-only)
Route::prefix('api/reports')->middleware(['api_token', 'throttle:60,1'])->group(function () {
    Route::get('growth', [\App\Http\Controllers\ReportController::class, 'apiGrowth']);
    Route::get('mutations', [\App\Http\Controllers\ReportController::class, 'apiMutations']);

});
