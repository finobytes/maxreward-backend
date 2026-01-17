<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;  
use Illuminate\Routing\Controllers\Middleware;     
use Illuminate\Support\Facades\Validator;    
use App\Services\CommunityTreeService;
use App\Models\Purchase; 
use App\Helpers\CommonFunctionHelper;     

class AuthController extends Controller implements HasMiddleware
{

    protected $treeService;

    public function __construct(CommunityTreeService $treeService) {
        $this->treeService = $treeService;
    }

    
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
        // return response()->json(auth('merchant')->user());
        $user = auth('merchant')->user();

        // Load merchant relationship (full data)
        $user->load('merchant.wallet', 'merchant.corporateMember.wallet');

        $corporateMemberId = $user->merchant->corporateMember->id;

        if (!$corporateMemberId) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Load statistics from your tree service
        $statistics = $this->treeService->getTreeStatistics($corporateMemberId);
        $user->community_members = $statistics['total_members'];

        // Calculate total pending purchase total for this merchant
        $user->total_pending_purchase = Purchase::where('merchant_id', $user->merchant->id)
        ->pending()  // scopePending() is pending from Purchase model
        ->count();

        $user->referred_members = CommonFunctionHelper::sponsoredMembers($corporateMemberId);
        $user->permissions = $user->getAllPermissions()->pluck('name');
        $user->roles = $user->getRoleNames();

        // Add permissions and roles to response
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
        $user = auth('merchant')->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('merchant')->factory()->getTTL() * 60,
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ]);
    }
}
