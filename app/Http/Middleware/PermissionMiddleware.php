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
     * @param  string  $permission  The permission name to check (e.g., 'product.view' or 'administrator.product.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Get authenticated user from any guard
        $user = $this->getAuthenticatedUser();

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

        // Build permission patterns to check based on user's roles
        $permissionsToCheck = $this->buildPermissionPatterns($user, $permission, $guardName);

        // Check if user has any of the required permissions
        $hasPermission = false;
        foreach ($permissionsToCheck as $perm) {
            if ($user->hasPermissionTo($perm)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
            $userRoles = $user->getRoleNames()->toArray();

            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permission to perform this action.',
                'error' => 'forbidden',
                'details' => [
                    'required_permission' => $permission,
                    'checked_permissions' => $permissionsToCheck,
                    'your_role' => $userRoles[0] ?? null,
                    'your_roles' => $userRoles,
                    'your_permissions' => $userPermissions,
                    'permissions_count' => count($userPermissions),
                ],
                'suggestion' => count($userPermissions) === 0
                    ? 'You have no permissions assigned. Please contact your administrator to assign you a role with appropriate permissions.'
                    : 'Your current role does not have access to this resource. Required: ' . $permission
            ], 403);
        }

        return $next($request);
    }

    /**
     * Build permission patterns based on user's roles
     *
     * @param  mixed  $user
     * @param  string  $permission
     * @param  string  $guardName
     * @return array
     */
    private function buildPermissionPatterns($user, string $permission, string $guardName): array
    {
        $patterns = [];

        // If permission is already fully qualified (e.g., 'administrator.product.view'), use it as-is
        if (str_contains($permission, '.') && !str_starts_with($permission, 'admin.')) {
            $parts = explode('.', $permission);
            if (count($parts) >= 2) {
                // Check if first part is a role name
                $possibleRole = $parts[0];
                if ($user->hasRole($possibleRole)) {
                    $patterns[] = $permission;
                    return $patterns;
                }
            }
        }

        // Get user's roles
        $userRoles = $user->getRoleNames();

        // For admin and member guards, build role-specific permissions
        // For merchant guard, use shared permissions
        if ($guardName === 'admin' || $guardName === 'member') {
            // Build role-specific permissions
            foreach ($userRoles as $role) {
                // Build: {role}.{permission}
                // Example: administrator.product.view, premium_member.voucher.create
                $patterns[] = $role . '.' . $permission;
            }

            // Fallback for admin guard: Also check guard-based permission
            if ($guardName === 'admin') {
                if (!str_starts_with($permission, 'admin.')) {
                    $patterns[] = 'admin.' . $permission;
                } else {
                    $patterns[] = $permission;
                }
            }
        } else {
            // For merchant guard, use shared permissions (permission as-is)
            $patterns[] = $permission;
        }

        return array_unique($patterns);
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
