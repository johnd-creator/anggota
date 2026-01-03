<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Role hierarchy: roles that implicitly include other roles' privileges.
     *
     * Example: admin_unit/bendahara are still anggota (member) in business terms,
     * so routes protected by `role:anggota` should also allow these roles.
     */
    private const IMPLIED_ROLES = [
        'anggota' => ['admin_unit', 'bendahara'],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user() || !$request->user()->role) {
            return redirect()->route('login');
        }

        $allowedRoles = $this->expandAllowedRoles($roles);
        if (in_array($request->user()->role->name, $allowedRoles, true)) {
            return $next($request);
        }

        // Fallback for Reguler users trying to access restricted pages
        if ($request->user()->role->name === 'reguler') {
            return redirect()->route('itworks');
        }

        abort(403, 'Unauthorized');
    }

    /**
     * Expand role list using IMPLIED_ROLES mapping.
     *
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    private function expandAllowedRoles(array $roles): array
    {
        $expanded = [];
        foreach ($roles as $role) {
            $expanded[] = $role;
            foreach (self::IMPLIED_ROLES[$role] ?? [] as $implied) {
                $expanded[] = $implied;
            }
        }
        return array_values(array_unique($expanded));
    }
}
