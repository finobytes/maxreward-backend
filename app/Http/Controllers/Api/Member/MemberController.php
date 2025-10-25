<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Helpers\CloudinaryHelper;

class MemberController extends Controller
{
    /**
     * Get all members with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Member::query();

            // Filter by member type (optional)
            if ($request->has('member_type')) {
                $query->where('member_type', $request->member_type);
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by gender (optional)
            if ($request->has('gender_type')) {
                $query->where('gender_type', $request->gender_type);
            }

            // Filter by merchant (for corporate members)
            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            // Search by name (optional)
            if ($request->has('name')) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            }

            // Search by phone (optional)
            if ($request->has('phone')) {
                $query->where('phone', 'LIKE', '%' . $request->phone . '%');
            }

            // Search by email (optional)
            if ($request->has('email')) {
                $query->where('email', 'LIKE', '%' . $request->email . '%');
            }

            // Search by address (optional)
            if ($request->has('address')) {
                $query->where('address', 'LIKE', '%' . $request->address . '%');
            }

            // General search by name, phone, email, user_name, address (optional)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $search . '%')
                      ->orWhere('user_name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%')
                      ->orWhere('address', 'LIKE', '%' . $search . '%');
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch members with relationships
            $members = $query->with([
                'wallet',     // Include member wallet
                'merchant'    // Include linked merchant (for corporate members)
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single member by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant.wallet'  // Include merchant and its wallet
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member by username (phone or corporate ID)
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByUsername($username)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant'
            ])->where('user_name', $username)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found with this username'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member by referral code
     * 
     * @param string $referralCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByReferralCode($referralCode)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant'
            ])->where('referral_code', $referralCode)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found with this referral code'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get general members only
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGeneralMembers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $members = Member::with('wallet')
                ->where('member_type', 'general')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'General members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve general members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get corporate members only
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCorporateMembers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $members = Member::with(['wallet', 'merchant'])
                ->where('member_type', 'corporate')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Corporate members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve corporate members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update member information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find the member
            $member = Member::findOrFail($id);

            // Validate request
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20|unique:members,phone,' . $id,
                'address' => 'sometimes|string|max:500',
                'email' => 'sometimes|email|max:255|unique:members,email,' . $id,
                'status' => 'sometimes|in:active,inactive,suspended',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            ]);

            // Handle image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($member->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($member->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/members/images'
                );

                // Update member with new image data
                $member->image = $uploadResult['url'];
                $member->image_cloudinary_id = $uploadResult['public_id'];
                $member->save();
            }

            // Update only the fields that are present in the request
            $member->update($validatedData);

            // Commit transaction
            DB::commit();

            // Reload member with relationships
            $member->load(['wallet', 'merchant']);

            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}