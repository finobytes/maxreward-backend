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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }

        // Get authenticated user
        $user = auth()->user();

        // Check if user has any of the allowed roles
        // For Member model
        if (method_exists($user, 'isCorporate') && in_array('member', $roles)) {
            return $next($request);
        }

        // For MerchantStaff model
        if (method_exists($user, 'isMerchant')) {
            if ((in_array('merchant', $roles) && $user->isMerchant()) ||
                (in_array('staff', $roles) && $user->isStaff())) {
                return $next($request);
            }
        }

        // For Admin model (you'll need to implement this)
        if (isset($user->type) && $user->type === 'admin' && in_array('admin', $roles)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden. You do not have permission to access this resource.'
        ], 403);
    }
}
