<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CheckMemberStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('member')->check()) {
            try {
                $member = auth('member')->user();
                $token = JWTAuth::getToken();
                $payload = JWTAuth::getPayload($token);

                // Get last_status_changed_at from token claims
                $tokenLastStatusChanged = $payload->get('last_status_changed_at');

                // Get current last_status_changed_at from database
                $currentLastStatusChanged = $member->last_status_changed_at ? $member->last_status_changed_at->timestamp : null;

                // If timestamps don't match, it means status was changed after token was issued
                // Force logout by invalidating the token
                if ($tokenLastStatusChanged != $currentLastStatusChanged) {
                    auth('member')->logout();
                    return response()->json([
                        'error' => 'Your account status has been changed. Please login again.'
                    ], 401);
                }

                // Also check if member is suspended or blocked
                if (in_array($member->status, ['suspended', 'blocked'])) {
                    auth('member')->logout();
                    return response()->json([
                        'error' => 'Your account has been ' . $member->status . '.',
                        'reason' => $member->status == 'suspended' ? $member->suspended_reason : $member->block_reason
                    ], 403);
                }
            } catch (\Exception $e) {
                // If any error occurs, continue with the request
            }
        }

        return $next($request);
    }
}
