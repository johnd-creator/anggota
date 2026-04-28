<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AspirationCategory;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\UnionPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'api' => [
                'version' => 'v1',
                'base_path' => '/api/mobile/v1',
            ],
            'limits' => [
                'photo_max_mb' => 5,
                'document_max_mb' => 2,
                'default_page_size' => 15,
            ],
            'dues' => [
                'default_amount' => (int) config('dues.default_amount', 30000),
                'due_day' => (int) config('dues.due_day', 10),
            ],
        ]);
    }

    public function features(Request $request): JsonResponse
    {
        return response()->json([
            'features' => [
                'announcements' => (bool) config('features.announcements', true),
                'letters' => (bool) config('features.letters', true),
                'finance' => (bool) config('features.finance', true),
                'reports' => (bool) config('features.reports', true),
            ],
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $user->currentUnitId();

        $units = $user->canViewGlobalScope()
            ? OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get()
            : OrganizationUnit::select('id', 'name', 'code')->where('id', $unitId)->get();

        return response()->json([
            'lookups' => [
                'organization_units' => $units,
                'union_positions' => UnionPosition::select('id', 'name')->orderBy('name')->get(),
                'aspiration_categories' => AspirationCategory::select('id', 'name')->orderBy('name')->get(),
                'letter_categories' => LetterCategory::select('id', 'name')->orderBy('name')->get(),
                'aspiration_statuses' => ['new', 'in_progress', 'resolved'],
                'member_document_types' => ['surat_pernyataan', 'ktp'],
                'notification_categories' => ['mutations', 'updates', 'onboarding', 'security', 'letters', 'announcements', 'dues', 'reports', 'finance'],
            ],
        ]);
    }
}
