<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\MerchantStaff;
use App\Models\Merchant;
use App\Helpers\CloudinaryHelper;

class MerchantStaffController extends Controller
{
    /**
     * Generate merchant staff username (M1 + 8 digits)
     */
    private function generateStaffUsername(): string
    {
        do {
            $username = 'M1' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (MerchantStaff::where('user_name', $username)->exists());

        return $username;
    }

    /**
     * Create a new staff member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|integer|exists:merchants,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/|unique:merchant_staffs,phone',
            'email' => 'required|email|max:255|unique:merchant_staffs,email',
            'password' => 'required|string|min:6',
            'gender_type' => 'required|in:male,female,other',
            'status' => 'nullable|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Verify merchant exists
            $merchant = Merchant::findOrFail($request->merchant_id);

            // Generate unique username for staff
            $staffUsername = $this->generateStaffUsername();

            // Handle staff image upload to Cloudinary
            $staffImageUrl = null;
            $imageCloudinaryId = null;

            if ($request->hasFile('image')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/merchant-staffs/images'
                );
                $staffImageUrl = $uploadResult['url'];
                $imageCloudinaryId = $uploadResult['public_id'];
            }

            // Create Staff
            $staff = MerchantStaff::create([
                'merchant_id' => $request->merchant_id,
                'user_name' => $staffUsername,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'type' => 'staff',
                'status' => $request->status ?? 'active',
                'gender_type' => $request->gender_type,
                'image' => $staffImageUrl,
                'image_cloudinary_id' => $imageCloudinaryId,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff created successfully',
                'data' => [
                    'staff' => [
                        'id' => $staff->id,
                        'merchant_id' => $staff->merchant_id,
                        'user_name' => $staff->user_name,
                        'name' => $staff->name,
                        'phone' => $staff->phone,
                        'email' => $staff->email,
                        'type' => $staff->type,
                        'status' => $staff->status,
                        'gender_type' => $staff->gender_type,
                        'image' => $staff->image,
                        'image_cloudinary_id' => $staff->image_cloudinary_id,
                        'created_at' => $staff->created_at,
                    ],
                    'credentials' => [
                        'username' => $staffUsername,
                        'password' => $request->password,
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all staff members with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = MerchantStaff::with('merchant');

            // Filter by merchant_id (optional)
            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            // Filter by type (optional) - by default only staff type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            } else {
                $query->where('type', 'staff');
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
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

            // Search by staff_id (user_name) (optional)
            if ($request->has('staff_id')) {
                $query->where('id', 'LIKE', '%' . $request->staff_id . '%');
            }

            // General search by name, email, phone or user_name (optional)
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('user_name', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch staffs with pagination
            $staffs = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Staffs retrieved successfully',
                'data' => $staffs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staffs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single staff by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $staff = MerchantStaff::with('merchant')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully',
                'data' => $staff
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update staff information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:merchant_staffs,phone,' . $id,
            'email' => 'sometimes|required|email|max:255|unique:merchant_staffs,email,' . $id,
            'password' => 'nullable|string|min:6',
            'gender_type' => 'sometimes|required|in:male,female,other',
            'status' => 'nullable|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Find staff
            $staff = MerchantStaff::findOrFail($id);

            // Handle staff image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($staff->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($staff->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/merchant-staffs/images'
                );

                // Update staff with new image data
                $staff->image = $uploadResult['url'];
                $staff->image_cloudinary_id = $uploadResult['public_id'];
                $staff->save();
            }

            // Update staff data (only fields that are provided)
            $staffData = [];

            if ($request->has('name')) {
                $staffData['name'] = $request->name;
            }
            if ($request->has('phone')) {
                $staffData['phone'] = $request->phone;
            }
            if ($request->has('email')) {
                $staffData['email'] = $request->email;
            }
            if ($request->has('gender_type')) {
                $staffData['gender_type'] = $request->gender_type;
            }
            if ($request->has('status')) {
                $staffData['status'] = $request->status;
            }
            if ($request->has('password') && !empty($request->password)) {
                $staffData['password'] = Hash::make($request->password);
            }

            if (!empty($staffData)) {
                $staff->update($staffData);
            }

            // Commit transaction
            DB::commit();

            // Load fresh data with relationship
            $staff->load('merchant');

            return response()->json([
                'success' => true,
                'message' => 'Staff updated successfully',
                'data' => $staff
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete staff member
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find staff
            $staff = MerchantStaff::findOrFail($id);

            // Store staff info for response
            $staffInfo = [
                'id' => $staff->id,
                'user_name' => $staff->user_name,
                'name' => $staff->name,
                'email' => $staff->email,
            ];

            // Delete the staff
            $staff->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff deleted successfully',
                'data' => $staffInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all staffs by merchant ID
     *
     * @param int $merchantId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByMerchant($merchantId)
    {
        try {
            // Verify merchant exists
            $merchant = Merchant::findOrFail($merchantId);

            // Get all staffs for this merchant (only type='staff')
            $staffs = MerchantStaff::where('merchant_id', $merchantId)
                                   ->where('type', 'staff')
                                   ->orderBy('created_at', 'desc')
                                   ->get();

            return response()->json([
                'success' => true,
                'message' => 'Staffs retrieved successfully',
                'data' => [
                    'merchant' => [
                        'id' => $merchant->id,
                        'business_name' => $merchant->business_name,
                    ],
                    'staffs' => $staffs,
                    'total' => $staffs->count(),
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staffs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
