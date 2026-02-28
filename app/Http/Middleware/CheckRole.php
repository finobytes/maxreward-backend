<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        $authenticatedUser = null;
        
        // Check which guard has authenticated user
        foreach (['admin', 'member', 'merchant', 'staff'] as $guard) {
            if (auth($guard)->check()) { 
                $authenticatedUser = auth($guard)->user();
                break;
            }
        }

        // If no user is authenticated
        if (!$authenticatedUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
                'error' => 'unauthorized'
            ], 401);
        }

        // Check for Member model
        if ($authenticatedUser instanceof \App\Models\Member) {
            if (in_array('member', $roles)) {
                return $next($request);
            }
        }

        // Check for MerchantStaff model
        if ($authenticatedUser instanceof \App\Models\MerchantStaff) {
            // Check if user type matches allowed roles
            if (in_array($authenticatedUser->type, $roles)) {
                return $next($request);
            }
        }

        // Check for Admin model
        if ($authenticatedUser instanceof \App\Models\Admin) {
            // Check if user type matches allowed roles
            if (in_array($authenticatedUser->type, $roles)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden. You do not have permission to access this resource.',
            'error' => 'forbidden',
            'required_roles' => $roles,
            'your_role' => $authenticatedUser->type ?? 'member'
        ], 403);
    }
}