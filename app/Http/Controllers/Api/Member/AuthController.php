<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Services\CommunityTreeService;
use App\Models\Purchase;

class AuthController extends Controller implements HasMiddleware
{
    protected $treeService;

    public function __construct(CommunityTreeService $treeService) {
        $this->treeService = $treeService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:member', except: ['login']),
        ];
    }

    public function login(Request $request)
    {
       

        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('user_name', 'password');

        if (! $token = auth('member')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        // return response()->json(auth('member')->user());
        $member = auth('member')->user()->load('wallet');
        
        // Load statistics from your tree service
        $statistics = $this->treeService->getTreeStatistics($member->id);
        $member->community_members = $statistics['total_members'];

        // Calculate lifetime purchase total for this member
        $member->lifetime_purchase = Purchase::where('member_id', $member->id)
        ->approved() // include this if you want only approved purchases
        ->sum('transaction_amount');

        return response()->json($member);
    }


    public function logout()
    {
        auth('member')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('member')->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('member')->factory()->getTTL() * 60
        ]);
    }
}