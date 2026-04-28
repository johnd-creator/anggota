<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\AspirationCategory;
use App\Models\LetterApprover;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\UnionPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    use MobileApiHelpers;

    public function units(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OrganizationUnit::class);
        $query = OrganizationUnit::query()->orderBy('name');
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('id', $request->user()->currentUnitId());
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'units');
    }

    public function unionPositions(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat']), 403);

        return $this->paginated(UnionPosition::orderBy('name')->paginate($this->perPage($request)), 'union_positions');
    }

    public function aspirationCategories(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat']), 403);

        return response()->json(['items' => AspirationCategory::orderBy('name')->get()]);
    }

    public function letterCategories(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat']), 403);

        return response()->json(['items' => LetterCategory::ordered()->get()]);
    }

    public function letterApprovers(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat']), 403);
        $query = LetterApprover::with(['user:id,name,email', 'unit:id,name,code'])->orderBy('organization_unit_id')->orderBy('signer_type');
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'letter_approvers');
    }
}
