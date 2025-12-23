<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    Route::get('/audit-logs', function (\Illuminate\Http\Request $request) {
        // Use Gate for policy-based authorization
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Models\AuditLog::class);

        $filters = $request->only(['role', 'date_start', 'date_end', 'category', 'event', 'unit_id', 'request_id']);

        $logs = \App\Models\AuditLog::with(['user.role', 'organizationUnit'])
            ->filter($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Log access to audit logs (audit viewing itself)
        app(\App\Services\AuditService::class)->log('audit_log.viewed', [
            'filters' => array_filter($filters),
        ]);

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => ['auth', 'member', 'mutation', 'surat', 'iuran', 'export', 'system'],
        ]);
    })->middleware('auth')->name('audit-logs');

    Route::get('/ui/components', function () {
        return Inertia::render('UI/ComponentsGallery');
    })->middleware('role:super_admin')->name('ui.components');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read_all');
    Route::post('/notifications/{id}/unread', [\App\Http\Controllers\NotificationController::class, 'markUnread'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.unread');
    Route::post('/notifications/read-batch', [\App\Http\Controllers\NotificationController::class, 'markReadBatch'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.read_batch');
    Route::get('/notifications/recent', [\App\Http\Controllers\NotificationController::class, 'recent'])->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('notifications.recent');

    Route::get('/settings', function () {
        $user = Auth::user();
        $pref = \App\Models\NotificationPreference::where('user_id', $user?->id)->first();
        $profile = [
            'name' => optional($user?->member)->full_name ?? $user?->name,
            'email' => $user?->email,
        ];
        $canQuickActions = optional($user?->role)->name === 'super_admin';
        return Inertia::render('Settings/Index', [
            'notification_prefs' => $pref ? [
                'channels' => $pref->channels,
                'digest_daily' => (bool) $pref->digest_daily,
                'updated_at' => $pref->updated_at,
            ] : null,
            'profile' => $profile,
            'can_quick_actions' => $canQuickActions,
        ]);
    })->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('settings.index');

    Route::patch('/settings/notifications', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $data = $request->validate([
            'channels' => ['required', 'array'],
            'channels.mutations' => ['array'],
            'channels.updates' => ['array'],
            'channels.onboarding' => ['array'],
            'channels.security' => ['array'],
            'digest_daily' => ['boolean'],
        ]);
        $pref = \App\Models\NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['channels' => $data['channels'], 'digest_daily' => (bool) ($data['digest_daily'] ?? false), 'updated_at' => now()]
        );
        return response()->json(['status' => 'ok', 'updated_at' => $pref->updated_at?->toISOString()]);
    })->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('settings.notification_prefs');

    Route::get('/help', function () {
        return Inertia::render('Help/Index');
    })->middleware('role:super_admin,admin_unit,anggota,reguler,bendahara')->name('help.index');

    // root path handled above (guest: login page, auth: dashboard)

    Route::prefix('reports')->middleware('role:super_admin,admin_unit')->group(function () {
        Route::get('growth', [\App\Http\Controllers\ReportController::class, 'growth'])->name('reports.growth');
        Route::get('mutations', [\App\Http\Controllers\ReportController::class, 'mutations'])->name('reports.mutations');
        Route::get('documents', [\App\Http\Controllers\ReportController::class, 'documents'])->name('reports.documents');
        Route::post('{type}/export', function (\Illuminate\Http\Request $request, string $type) {
            if ($type === 'growth') {
                $unitId = (int) $request->input('unit_id');
                $dateStart = $request->input('date_start');
                $dateEnd = $request->input('date_end');
                $query = \App\Models\Member::query();
                if ($unitId)
                    $query->where('organization_unit_id', $unitId);
                if ($dateStart)
                    $query->whereDate('join_date', '>=', $dateStart);
                if ($dateEnd)
                    $query->whereDate('join_date', '<=', $dateEnd);
                $filename = 'report_growth_' . now()->format('Ymd_His') . '.csv';
                return \App\Services\ReportExporter::streamCsv($filename, ['Month', 'Count'], function ($out) use ($query) {
                    $months = collect(range(0, 11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
                    $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))->groupBy('ym')->get()->keyBy('ym');
                    foreach ($months as $m)
                        fputcsv($out, [$m, (int) optional($rows->get($m))->c]);
                });
            } elseif ($type === 'mutations') {
                $unitId = (int) $request->input('unit_id');
                $status = $request->input('status');
                $dateStart = $request->input('date_start');
                $dateEnd = $request->input('date_end');
                $query = \App\Models\MutationRequest::query()->with(['member', 'fromUnit', 'toUnit']);
                if ($unitId)
                    $query->where(function ($q) use ($unitId) {
                        $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
                    });
                if ($status)
                    $query->where('status', $status);
                if ($dateStart)
                    $query->whereDate('effective_date', '>=', $dateStart);
                if ($dateEnd)
                    $query->whereDate('effective_date', '<=', $dateEnd);
                $filename = 'report_mutations_' . now()->format('Ymd_His') . '.csv';
                return \App\Services\ReportExporter::streamCsv($filename, ['ID', 'Anggota', 'Asal', 'Tujuan', 'Status', 'Tanggal Efektif'], function ($out) use ($query) {
                    $query->orderBy('id')->chunk(500, function ($rows) use (&$out) {
                        foreach ($rows as $r)
                            fputcsv($out, [$r->id, optional($r->member)->full_name, optional($r->fromUnit)->name, optional($r->toUnit)->name, $r->status, $r->effective_date]);
                    });
                });
            } elseif ($type === 'documents') {
                $unitId = (int) $request->input('unit_id');
                $status = $request->input('status');
                $query = \App\Models\Member::query()->select('id', 'full_name', 'email', 'organization_unit_id', 'photo_path', 'documents', 'kta_number', 'nip', 'union_position_id')->with(['unit', 'unionPosition']);
                if ($unitId)
                    $query->where('organization_unit_id', $unitId);
                if ($status)
                    $query->where('status', $status);
                $filename = 'report_documents_' . now()->format('Ymd_His') . '.csv';
                return \App\Services\ReportExporter::streamCsv($filename, ['ID', 'Nama', 'Email', 'KTA', 'NIP', 'Jabatan', 'Unit', 'Foto', 'Dokumen'], function ($out) use ($query) {
                    $query->orderBy('id')->chunk(500, function ($rows) use (&$out) {
                        foreach ($rows as $m)
                            fputcsv($out, [$m->id, $m->full_name, $m->email, $m->kta_number, $m->nip, optional($m->unionPosition)->name, optional($m->unit)->name, $m->photo_path ? 'YA' : 'TIDAK', $m->documents ? 'ADA' : 'TIDAK']);
                    });
                });
            }
            return response()->json(['error' => 'Unknown report'], 404);
        })->name('reports.export');
    });



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

        Route::resource('members', \App\Http\Controllers\Admin\MemberController::class);
        Route::resource('union-positions', \App\Http\Controllers\Admin\UnionPositionController::class)->middleware('role:super_admin')->names('union_positions');
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class)->middleware('role:super_admin');
        Route::post('roles/{role}/assign', [\App\Http\Controllers\Admin\RoleController::class, 'assign'])->middleware('role:super_admin')->name('roles.assign');
        Route::delete('roles/{role}/users/{user}', [\App\Http\Controllers\Admin\RoleController::class, 'removeUser'])->middleware('role:super_admin')->name('roles.remove_user');

        // Admin Aspirations (Categories & Main)
        Route::resource('aspiration-categories', \App\Http\Controllers\Admin\AspirationCategoryController::class);
        Route::resource('letter-categories', \App\Http\Controllers\Admin\LetterCategoryController::class)->middleware('role:super_admin');
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
        })->name('activity-logs.index');
        Route::get('onboarding', [\App\Http\Controllers\Admin\OnboardingController::class, 'index'])->name('onboarding.index');
        Route::post('onboarding/{pending}/approve', [\App\Http\Controllers\Admin\OnboardingController::class, 'approve'])->name('onboarding.approve');
        Route::post('onboarding/{pending}/reject', [\App\Http\Controllers\Admin\OnboardingController::class, 'reject'])->name('onboarding.reject');
        Route::get('updates', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'index'])->name('updates.index');
        Route::post('updates/{update_request}/approve', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'approve'])->name('updates.approve');
        Route::post('updates/{update_request}/reject', [\App\Http\Controllers\Admin\MemberUpdateController::class, 'reject'])->name('updates.reject');
        Route::get('mutations', [\App\Http\Controllers\Admin\MutationController::class, 'index'])->name('mutations.index');
        Route::post('mutations', [\App\Http\Controllers\Admin\MutationController::class, 'store'])->name('mutations.store');
        Route::get('mutations/{mutation}', [\App\Http\Controllers\Admin\MutationController::class, 'show'])->name('mutations.show');
        Route::post('mutations/{mutation}/approve', [\App\Http\Controllers\Admin\MutationController::class, 'approve'])->middleware(['role:super_admin', 'throttle:10,1'])->name('mutations.approve');
        Route::post('mutations/{mutation}/reject', [\App\Http\Controllers\Admin\MutationController::class, 'reject'])->middleware(['role:super_admin', 'throttle:10,1'])->name('mutations.reject');

        Route::get('members-export', function (\Illuminate\Http\Request $request) {
            $user = Auth::user();
            $unitId = (int) $request->query('unit_id');
            if ($user && $user->role && $user->role->name === 'admin_unit') {
                $unitId = (int) ($user->organization_unit_id ?? 0);
            }
            $query = \App\Models\Member::query()->select(['id', 'full_name', 'email', 'phone', 'status', 'organization_unit_id', 'nra', 'kta_number', 'nip', 'union_position_id', 'join_date'])->with(['unit', 'unionPosition']);
            if ($unitId)
                $query->where('organization_unit_id', $unitId);
            $filename = 'members_export_' . now()->format('Ymd_His') . '.csv';
            Cache::put('export:members:' . $user->id, ['status' => 'started', 'time' => now()->toISOString()], 300);
            return response()->streamDownload(function () use ($query, $user) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Nama', 'Email', 'Telepon', 'Status', 'Unit', 'NRA', 'KTA', 'NIP', 'Jabatan Serikat', 'Join Date']);
                $count = 0;
                $query->orderBy('id')->chunk(500, function ($rows) use (&$out, &$count) {
                    foreach ($rows as $m) {
                        fputcsv($out, [$m->id, $m->full_name, $m->email, $m->phone, $m->status, $m->unit?->name, $m->nra, $m->kta_number, $m->nip, optional($m->unionPosition)->name, $m->join_date]);
                        $count++;
                    }
                });
                Cache::put('export:members:' . $user->id, ['status' => 'completed', 'count' => $count, 'time' => now()->toISOString()], 300);
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv']);
        })->name('members.export');

        Route::get('members/import/template', [\App\Http\Controllers\Admin\MemberImportController::class, 'template'])->middleware('role:admin_unit')->name('members.import.template');
        Route::post('members/import', [\App\Http\Controllers\Admin\MemberImportController::class, 'store'])->middleware('role:admin_unit')->name('members.import');

        Route::get('mutations/export', function (\Illuminate\Http\Request $request) {
            $user = Auth::user();
            $unitId = (int) $request->query('unit_id');
            $query = \App\Models\MutationRequest::query()->select(['id', 'member_id', 'from_unit_id', 'to_unit_id', 'status', 'effective_date'])->with(['member', 'fromUnit', 'toUnit']);
            if ($unitId)
                $query->where(function ($q) use ($unitId) {
                    $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
                });
            $filename = 'mutations_export_' . now()->format('Ymd_His') . '.csv';
            return response()->streamDownload(function () use ($query) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Anggota', 'Asal', 'Tujuan', 'Status', 'Tanggal Efektif']);
                $query->orderBy('id')->chunk(500, function ($rows) use (&$out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->id, optional($r->member)->full_name, optional($r->fromUnit)->name, optional($r->toUnit)->name, $r->status, $r->effective_date]);
                    }
                });
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv']);
        })->name('admin.mutations.export');
    });

    Route::prefix('finance')->name('finance.')->middleware('role:super_admin,admin_unit,bendahara')->group(function () {
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
            Route::post('/', [\App\Http\Controllers\LetterController::class, 'store'])->name('store');
            Route::get('{letter}/edit', [\App\Http\Controllers\LetterController::class, 'edit'])->name('edit');
            Route::put('{letter}', [\App\Http\Controllers\LetterController::class, 'update'])->name('update');
            Route::delete('{letter}', [\App\Http\Controllers\LetterController::class, 'destroy'])->name('destroy');
            Route::post('{letter}/submit', [\App\Http\Controllers\LetterController::class, 'submit'])->name('submit');
            Route::post('{letter}/send', [\App\Http\Controllers\LetterController::class, 'send'])->name('send');
            Route::post('{letter}/archive', [\App\Http\Controllers\LetterController::class, 'archive'])->name('archive');
        });

        // Approval actions - for Ketua/Sekretaris (policy check inside)
        Route::post('{letter}/approve', [\App\Http\Controllers\LetterController::class, 'approve'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('approve');
        Route::post('{letter}/revise', [\App\Http\Controllers\LetterController::class, 'revise'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('revise');
        Route::post('{letter}/reject', [\App\Http\Controllers\LetterController::class, 'reject'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('reject');

        // Preview - accessible by viewer (policy check)
        Route::get('{letter}/preview', [\App\Http\Controllers\LetterController::class, 'preview'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('preview');

        // QR code image for preview
        Route::get('{letter}/qr.png', [\App\Http\Controllers\LetterController::class, 'qrCode'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('qr');

        // Attachments
        Route::post('{letter}/attachments', [\App\Http\Controllers\LetterController::class, 'storeAttachment'])
            ->middleware('role:admin_unit,admin_pusat,super_admin')
            ->name('attachments.store');
        Route::get('{letter}/attachments/{attachment}', [\App\Http\Controllers\LetterController::class, 'downloadAttachment'])
            ->middleware('role:anggota,bendahara,admin_unit,admin_pusat,super_admin')
            ->name('attachments.download');

        // PDF export
        Route::get('{letter}/pdf', [\App\Http\Controllers\LetterController::class, 'pdf'])
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
    // Member Aspirations
    Route::get('/member/aspirations', [\App\Http\Controllers\Member\AspirationController::class, 'index'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.index');
    Route::get('/member/aspirations/create', [\App\Http\Controllers\Member\AspirationController::class, 'create'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.create');
    Route::post('/member/aspirations', [\App\Http\Controllers\Member\AspirationController::class, 'store'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.store');
    Route::get('/member/aspirations/{aspiration}', [\App\Http\Controllers\Member\AspirationController::class, 'show'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.show');
    Route::post('/member/aspirations/{aspiration}/support', [\App\Http\Controllers\Member\AspirationController::class, 'support'])->middleware('role:anggota,bendahara,super_admin,admin_pusat,admin_unit')->name('member.aspirations.support');
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
Route::prefix('api/reports')->middleware(['api_token'])->group(function () {
    Route::get('growth', [\App\Http\Controllers\ReportController::class, 'apiGrowth']);
    Route::get('mutations', [\App\Http\Controllers\ReportController::class, 'apiMutations']);
    Route::get('documents', [\App\Http\Controllers\ReportController::class, 'apiDocuments']);
});
