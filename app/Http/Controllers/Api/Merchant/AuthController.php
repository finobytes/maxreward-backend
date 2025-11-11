<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;  
use Illuminate\Routing\Controllers\Middleware;     
use Illuminate\Support\Facades\Validator;          

class AuthController extends Controller implements HasMiddleware
{
    
    // ✅ Laravel 12 এর নতুন পদ্ধতি
    public static function middleware(): array
    {
        return [
            new Middleware('auth:merchant', except: ['login']),
        ];
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('user_name', 'password');

        if (! $token = auth('merchant')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = auth('merchant')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Load merchant relationship (full data)
        $user->load('merchant.wallet', 'merchant.corporateMember');

        return response()->json($user);
    }

    public function logout()
    {
        auth('merchant')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('merchant')->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('merchant')->factory()->getTTL() * 60
        ]);
    }
}
