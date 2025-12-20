<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission name to check (e.g., 'product.create')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Get authenticated user from any guard
        $user = $this->getAuthenticatedUser();

        // dd($user);

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
                'error' => 'unauthorized'
            ], 401);
        }

        // Get the guard name for the authenticated user
        $guardName = $this->getAuthenticatedGuard();

        // For admin guard, prefix the permission with 'admin.'
        // This handles the difference in permission naming:
        // - Merchant permissions: product.view, product.create, etc.
        // - Admin permissions: admin.product.view, admin.product.edit, etc.
        $permissionToCheck = $permission;
        if ($guardName === 'admin') {
            // If permission doesn't already start with 'admin.', prefix it
            if (!str_starts_with($permission, 'admin.')) {
                $permissionToCheck = 'admin.' . $permission;
            }
        }

        // Check if user has the required permission
        if (!$user->hasPermissionTo($permissionToCheck)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
                'error' => 'forbidden',
                'required_permission' => $permissionToCheck,
                'your_permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ], 403);
        }

        return $next($request);
    }

    /**
     * Get authenticated user from any guard
     */
    private function getAuthenticatedUser()
    {
        // Check all guards for authenticated user
        foreach (['admin', 'member', 'merchant'] as $guard) {
            if (auth($guard)->check()) {
                return auth($guard)->user();
            }
        }

        return null;
    }

    /**
     * Get the guard name of the authenticated user
     */
    private function getAuthenticatedGuard(): ?string
    {
        // Check all guards and return the name of the authenticated guard
        foreach (['admin', 'member', 'merchant'] as $guard) {
            if (auth($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }
}
