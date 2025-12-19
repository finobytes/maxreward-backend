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

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
                'error' => 'unauthorized'
            ], 401);
        }

        // Check if user has the required permission
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার এই কাজ করার অনুমতি নেই। (You do not have permission to perform this action)',
                'error' => 'forbidden',
                'required_permission' => $permission,
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
}
