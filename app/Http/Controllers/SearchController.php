<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    protected SearchService $searchService;
    protected \App\Services\AuditService $auditService;

    public function __construct(SearchService $searchService, \App\Services\AuditService $auditService)
    {
        $this->searchService = $searchService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|min:2|max:80',
            'type' => 'nullable|string|alpha_dash',
        ]);

        $query = trim($request->input('q', ''));
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);

        $results = [];
        $allowedTypes = $this->searchService->allowedTypes($request->user());

        if ($query) {
            $this->logPrivilegedSearch($request->user(), 'search.page', $query, $type);

            if ($type === 'all') {
                $results = $this->searchService->search($request->user(), $query, [], 5)['results'];
            } else {
                if (!in_array($type, $allowedTypes)) {
                    abort(403);
                }
                $results = [$type => $this->searchService->paginate($request->user(), $query, $type, 15)];
            }
        }

        return Inertia::render('Search/Index', [
            'query' => $query,
            'activeType' => $type,
            'results' => $results,
            'allowed_types' => $allowedTypes,
        ]);
    }

    public function api(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:80',
            'limit' => 'integer|min:1|max:20',
        ]);

        $query = trim($request->input('q'));
        $types = $request->input('types', []);
        $limit = $request->input('limit', 5);

        $this->logPrivilegedSearch($request->user(), 'search.api', $query, 'all');

        $data = $this->searchService->search($request->user(), $query, $types, (int) $limit);

        return response()->json($data);
    }

    protected function logPrivilegedSearch(\App\Models\User $user, string $event, string $query, string $typeScope)
    {
        $roleName = $user->role?->name ?? 'anggota';
        // Only log for privileged users who might be searching outside their own data extensively
        if (in_array($roleName, ['super_admin', 'admin_pusat'])) {
            $this->auditService->log($event, [
                'query_hash' => hash('sha256', strtolower($query)),
                'query_len' => strlen($query),
                'type_scope' => $typeScope,
                'role' => $roleName,
            ], null, $user->id);
        }
    }
}
