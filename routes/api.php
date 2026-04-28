<?php

use Illuminate\Support\Facades\Route;

// Health check endpoint untuk service worker offline detection
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'app_env' => app()->environment(),
    ]);
})->name('api.health');

Route::prefix('mobile/v1')->name('api.mobile.')->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');
    Route::post('/auth/google/token', [\App\Http\Controllers\Api\Mobile\PlatformController::class, 'googleToken'])
        ->middleware('throttle:5,1')
        ->name('auth.google.token');
    Route::post('/auth/microsoft/token', [\App\Http\Controllers\Api\Mobile\PlatformController::class, 'microsoftToken'])
        ->middleware('throttle:5,1')
        ->name('auth.microsoft.token');
    Route::get('/member/card/verify/{token}', [\App\Http\Controllers\Api\Mobile\MemberCardController::class, 'verify'])
        ->middleware('throttle:60,1')
        ->name('member.card.verify');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/config', [\App\Http\Controllers\Api\Mobile\MetaController::class, 'config'])
            ->name('config');
        Route::get('/features', [\App\Http\Controllers\Api\Mobile\MetaController::class, 'features'])
            ->name('features');
        Route::get('/meta/lookups', [\App\Http\Controllers\Api\Mobile\MetaController::class, 'lookups'])
            ->name('meta.lookups');
        Route::get('/dashboard', [\App\Http\Controllers\Api\Mobile\DashboardController::class, 'show'])
            ->name('dashboard');

        Route::post('/auth/logout', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'logout'])
            ->name('auth.logout');
        Route::get('/me', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'me'])
            ->name('me');

        Route::get('/profile', [\App\Http\Controllers\Api\Mobile\ProfileController::class, 'show'])
            ->name('profile.show');
        Route::patch('/profile/update-request', [\App\Http\Controllers\Api\Mobile\ProfileController::class, 'requestUpdate'])
            ->name('profile.update_request');
        Route::post('/profile/photo', [\App\Http\Controllers\Api\Mobile\ProfileController::class, 'uploadPhoto'])
            ->name('profile.photo');
        Route::delete('/profile/photo', [\App\Http\Controllers\Api\Mobile\ProfileController::class, 'deletePhoto'])
            ->name('profile.photo.delete');
        Route::post('/profile/documents', [\App\Http\Controllers\Api\Mobile\ProfileController::class, 'uploadDocument'])
            ->name('profile.documents');
        Route::get('/member/card', [\App\Http\Controllers\Api\Mobile\MemberCardController::class, 'show'])
            ->name('member.card');
        Route::get('/member/card/qr', [\App\Http\Controllers\Api\Mobile\MemberCardController::class, 'qr'])
            ->name('member.card.qr');
        Route::get('/member/card/pdf', [\App\Http\Controllers\Api\Mobile\MemberCardController::class, 'pdf'])
            ->name('member.card.pdf');
        Route::post('/member/data/export-request', [\App\Http\Controllers\Api\Mobile\MemberDataController::class, 'exportRequest'])
            ->name('member.data.export_request');
        Route::post('/member/data/delete-request', [\App\Http\Controllers\Api\Mobile\MemberDataController::class, 'deleteRequest'])
            ->name('member.data.delete_request');

        Route::patch('/settings/profile', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'updateProfile'])
            ->name('settings.profile');
        Route::patch('/settings/password', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'updatePassword'])
            ->name('settings.password');
        Route::get('/settings/sessions', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'sessions'])
            ->name('settings.sessions');
        Route::post('/settings/sessions/revoke-others', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'revokeOtherSessions'])
            ->name('settings.sessions.revoke_others');
        Route::patch('/settings/notifications', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'updateNotifications'])
            ->name('settings.notifications');

        Route::get('/dues', [\App\Http\Controllers\Api\Mobile\DuesController::class, 'index'])
            ->name('dues.index');

        Route::get('/notifications', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::get('/notifications/recent', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'recent'])
            ->name('notifications.recent');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'markAllRead'])
            ->name('notifications.read_all');
        Route::post('/notifications/read-batch', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'markReadBatch'])
            ->name('notifications.read_batch');
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/{id}/unread', [\App\Http\Controllers\Api\Mobile\NotificationController::class, 'markUnread'])
            ->name('notifications.unread');

        Route::get('/aspirations', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'index'])
            ->name('aspirations.index');
        Route::post('/aspirations', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'store'])
            ->name('aspirations.store');
        Route::get('/aspiration-categories', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'categories'])
            ->name('aspirations.categories');
        Route::get('/aspiration-tags', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'tags'])
            ->name('aspirations.tags');
        Route::get('/aspirations/{aspiration}', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'show'])
            ->name('aspirations.show');
        Route::post('/aspirations/{aspiration}/support', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'support'])
            ->name('aspirations.support');
        Route::delete('/aspirations/{aspiration}/support', [\App\Http\Controllers\Api\Mobile\AspirationController::class, 'unsupport'])
            ->name('aspirations.unsupport');

        Route::get('/announcements', [\App\Http\Controllers\Api\Mobile\AnnouncementController::class, 'index'])
            ->name('announcements.index');
        Route::get('/announcements/attachments/{attachment}/download', [\App\Http\Controllers\Api\Mobile\AnnouncementController::class, 'downloadAttachment'])
            ->name('announcements.attachments.download');
        Route::get('/announcements/{announcement}', [\App\Http\Controllers\Api\Mobile\AnnouncementController::class, 'show'])
            ->name('announcements.show');
        Route::post('/announcements/{announcement}/dismiss', [\App\Http\Controllers\Api\Mobile\AnnouncementController::class, 'dismiss'])
            ->name('announcements.dismiss');

        Route::post('/feedback', [\App\Http\Controllers\Api\Mobile\FeedbackController::class, 'store'])
            ->name('feedback.store');

        Route::get('/letters/inbox', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'inbox'])->name('letters.inbox');
        Route::get('/letters/outbox', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'outbox'])->name('letters.outbox');
        Route::get('/letters/approvals', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'approvals'])->name('letters.approvals');
        Route::get('/letters/categories', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'categories'])->name('letters.categories');
        Route::get('/letters/approvers', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'approvers'])->name('letters.approvers');
        Route::get('/members/search', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'searchMembers'])->name('members.search');
        Route::get('/letters/template-render', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'templateRender'])->name('letters.template_render');
        Route::post('/letters', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'store'])->name('letters.store');
        Route::get('/letters/{letter}', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'show'])->name('letters.show');
        Route::put('/letters/{letter}', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'update'])->name('letters.update');
        Route::delete('/letters/{letter}', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'destroy'])->name('letters.destroy');
        Route::get('/letters/{letter}/preview', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'preview'])->name('letters.preview');
        Route::get('/letters/{letter}/pdf', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'pdf'])->name('letters.pdf');
        Route::get('/letters/{letter}/qr', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'qr'])->name('letters.qr');
        Route::post('/letters/{letter}/submit', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'submit'])->name('letters.submit');
        Route::post('/letters/{letter}/send', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'send'])->name('letters.send');
        Route::post('/letters/{letter}/archive', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'archive'])->name('letters.archive');
        Route::post('/letters/{letter}/approve', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'approve'])->name('letters.approve');
        Route::post('/letters/{letter}/revise', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'revise'])->name('letters.revise');
        Route::post('/letters/{letter}/reject', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'reject'])->name('letters.reject');
        Route::post('/letters/{letter}/attachments', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'storeAttachment'])->name('letters.attachments.store');
        Route::get('/letters/{letter}/attachments/{attachment}/download', [\App\Http\Controllers\Api\Mobile\LetterController::class, 'downloadAttachment'])->name('letters.attachments.download');

        Route::get('/admin/members', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'members'])->name('admin.members.index');
        Route::post('/admin/members', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'memberStore'])->name('admin.members.store');
        Route::get('/admin/members/search', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'memberSearch'])->name('admin.members.search');
        Route::post('/admin/members/export-request', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'memberExportRequest'])->name('admin.members.export_request');
        Route::get('/admin/members/{member}', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'memberShow'])->name('admin.members.show');
        Route::put('/admin/members/{member}', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'memberUpdate'])->name('admin.members.update');
        Route::get('/admin/onboarding', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'onboarding'])->name('admin.onboarding.index');
        Route::post('/admin/onboarding/{pending}/approve', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'approveOnboarding'])->name('admin.onboarding.approve');
        Route::post('/admin/onboarding/{pending}/reject', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'rejectOnboarding'])->name('admin.onboarding.reject');
        Route::get('/admin/updates', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'updates'])->name('admin.updates.index');
        Route::post('/admin/updates/{update}/approve', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'approveUpdate'])->name('admin.updates.approve');
        Route::post('/admin/updates/{update}/reject', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'rejectUpdate'])->name('admin.updates.reject');
        Route::get('/admin/mutations', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'mutations'])->name('admin.mutations.index');
        Route::post('/admin/mutations', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'mutationStore'])->name('admin.mutations.store');
        Route::get('/admin/mutations/{mutation}', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'mutationShow'])->name('admin.mutations.show');
        Route::post('/admin/mutations/{mutation}/approve', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'approveMutation'])->name('admin.mutations.approve');
        Route::post('/admin/mutations/{mutation}/reject', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'rejectMutation'])->name('admin.mutations.reject');
        Route::post('/admin/mutations/{mutation}/cancel', [\App\Http\Controllers\Api\Mobile\AdminWorkflowController::class, 'cancelMutation'])->name('admin.mutations.cancel');

        Route::get('/finance/categories', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'categories'])->name('finance.categories.index');
        Route::post('/finance/categories', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'categoryStore'])->name('finance.categories.store');
        Route::put('/finance/categories/{category}', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'categoryUpdate'])->name('finance.categories.update');
        Route::delete('/finance/categories/{category}', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'categoryDestroy'])->name('finance.categories.destroy');
        Route::get('/finance/ledgers', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgers'])->name('finance.ledgers.index');
        Route::post('/finance/ledgers', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerStore'])->name('finance.ledgers.store');
        Route::post('/finance/ledgers/export', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerExport'])->name('finance.ledgers.export');
        Route::put('/finance/ledgers/{ledger}', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerUpdate'])->name('finance.ledgers.update');
        Route::delete('/finance/ledgers/{ledger}', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerDestroy'])->name('finance.ledgers.destroy');
        Route::post('/finance/ledgers/{ledger}/approve', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerApprove'])->name('finance.ledgers.approve');
        Route::post('/finance/ledgers/{ledger}/reject', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'ledgerReject'])->name('finance.ledgers.reject');
        Route::get('/finance/dues', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'dues'])->name('finance.dues.index');
        Route::patch('/finance/dues/mass-update', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'duesMassUpdate'])->name('finance.dues.mass_update');
        Route::get('/finance/dues/dashboard', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'duesSummary'])->name('finance.dues.dashboard');
        Route::patch('/finance/dues/{dues}', [\App\Http\Controllers\Api\Mobile\FinanceController::class, 'duesUpdate'])->name('finance.dues.update');

        Route::get('/reports/growth', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'growth'])->name('reports.growth');
        Route::get('/reports/mutations', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'mutations'])->name('reports.mutations');
        Route::get('/reports/members', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'members'])->name('reports.members');
        Route::get('/reports/aspirations', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'aspirations'])->name('reports.aspirations');
        Route::get('/reports/dues', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'dues'])->name('reports.dues');
        Route::get('/reports/finance', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'finance'])->name('reports.finance');
        Route::get('/reports/export', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/export/status/{id}', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'exportStatus'])->name('reports.export.status');

        Route::get('/master/units', [\App\Http\Controllers\Api\Mobile\MasterDataController::class, 'units'])->name('master.units.index');
        Route::get('/master/union-positions', [\App\Http\Controllers\Api\Mobile\MasterDataController::class, 'unionPositions'])->name('master.union_positions.index');
        Route::get('/master/aspiration-categories', [\App\Http\Controllers\Api\Mobile\MasterDataController::class, 'aspirationCategories'])->name('master.aspiration_categories.index');
        Route::get('/master/letter-categories', [\App\Http\Controllers\Api\Mobile\MasterDataController::class, 'letterCategories'])->name('master.letter_categories.index');
        Route::get('/master/letter-approvers', [\App\Http\Controllers\Api\Mobile\MasterDataController::class, 'letterApprovers'])->name('master.letter_approvers.index');

        Route::get('/admin/roles', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'roles'])->name('admin.roles.index');
        Route::post('/admin/roles/{role}/assign', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'assignRole'])->name('admin.roles.assign');
        Route::get('/admin/users', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'users'])->name('admin.users.index');
        Route::get('/admin/users/{user}', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'userShow'])->name('admin.users.show');
        Route::patch('/admin/users/{user}', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'userUpdate'])->name('admin.users.update');
        Route::get('/admin/sessions', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'sessions'])->name('admin.sessions.index');
        Route::delete('/admin/sessions/{session}', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'revokeSession'])->name('admin.sessions.revoke');
        Route::get('/admin/audit-logs', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'auditLogs'])->name('admin.audit_logs.index');
        Route::get('/admin/activity-logs', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'activityLogs'])->name('admin.activity_logs.index');
        Route::get('/admin/ops', [\App\Http\Controllers\Api\Mobile\AdminOpsController::class, 'ops'])->name('admin.ops');

        Route::post('/devices', [\App\Http\Controllers\Api\Mobile\PlatformController::class, 'storeDevice'])->name('devices.store');
        Route::delete('/devices/{device}', [\App\Http\Controllers\Api\Mobile\PlatformController::class, 'deleteDevice'])->name('devices.destroy');
    });
});

Route::middleware(['api_token', 'throttle:60,1'])->group(function () {
    Route::get('/members', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');

        // Require unit_id parameter
        if (! $unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;
        $query = \App\Models\Member::query()
            ->select('id', 'full_name', 'status', 'organization_unit_id', 'nra', 'kta_number')
            ->where('organization_unit_id', $unitId);

        return response()->json($query->limit(100)->get());
    });

    Route::get('/mutations', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');

        // Require unit_id parameter
        if (! $unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;
        $items = \App\Models\MutationRequest::with(['member:id,full_name', 'fromUnit:id,name', 'toUnit:id,name'])
            ->where(function ($q) use ($unitId) {
                $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
            })
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($items);
    });

    Route::get('/documents', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');
        $memberId = (int) $request->query('member_id');

        // Require unit_id parameter
        if (! $unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;

        // Get member IDs in this unit
        $memberIds = \App\Models\Member::where('organization_unit_id', $unitId)
            ->pluck('id');

        $q = \App\Models\MemberDocument::query()
            ->select('member_id', 'type', 'original_name', 'size')
            ->whereIn('member_id', $memberIds);

        if ($memberId) {
            // Verify member is in the requested unit
            if (! $memberIds->contains($memberId)) {
                return response()->json(['error' => 'Member not in specified unit'], 403);
            }
            $q->where('member_id', $memberId);
        }

        return response()->json($q->limit(100)->get());
    });
});
