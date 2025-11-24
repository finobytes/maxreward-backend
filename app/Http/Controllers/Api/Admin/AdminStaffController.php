<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use App\Helpers\CloudinaryHelper;

class AdminStaffController extends Controller
{
    /**
     * Generate admin staff username (A1 + 8 digits)
     */
    private function generateStaffUsername(): string
    {
        do {
            $username = 'A1' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Admin::where('user_name', $username)->exists());

        return $username;
    }

    /**
     * Create a new admin staff member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string|max:255|unique:admin,user_name',
            'name' => 'required|string|max:255',
            // 'phone' => 'required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:admin,phone',
            'phone' => 'required|string|max:20|unique:admin,phone',
            'email' => 'required|email|max:255|unique:admin,email',
            'password' => 'required|string|min:6',
            'address' => 'nullable|string|max:500',
            'designation' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,others',
            'status' => 'nullable|in:active,inactive',
            'profile_picture' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',
            'national_id_card' => 'nullable|array|size:2',
            'national_id_card.*' => 'image|mimes:jpeg,jpg,png,gif,svg|max:5120',
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

            // Generate unique username for staff
            // $staffUsername = $this->generateStaffUsername();

            // Handle profile picture upload to Cloudinary
            $profilePictureUrl = null;
            $profileCloudinaryId = null;

            if ($request->hasFile('profile_picture')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('profile_picture'),
                    'maxreward/admin/profile_pictures'
                );
                $profilePictureUrl = $uploadResult['url'];
                $profileCloudinaryId = $uploadResult['public_id'];
            }

            // Handle national ID card upload to Cloudinary (array of 2 images)
            $nationalIdCardData = null;

            if ($request->hasFile('national_id_card')) {
                $files = $request->file('national_id_card');
                $uploadedImages = [];

                // Upload each file
                foreach ($files as $index => $file) {
                    $uploadResult = CloudinaryHelper::uploadImage(
                        $file,
                        'maxreward/admin/national_id_cards'
                    );

                    $key = $index === 0 ? 'front' : 'back';
                    $uploadedImages[$key] = [
                        'url' => $uploadResult['url'],
                        'cloudinary_id' => $uploadResult['public_id'],
                    ];
                }

                $nationalIdCardData = $uploadedImages;
            }

            // Create Admin Staff
            $staff = Admin::create([
                // 'user_name' => $staffUsername,
                'user_name' => $request->user_name,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'address' => $request->address,
                'designation' => $request->designation,
                'type' => 'staff',
                'status' => $request->status ?? 'active',
                'gender' => $request->gender,
                'profile_picture' => $profilePictureUrl,
                'profile_cloudinary_id' => $profileCloudinaryId,
                'national_id_card' => $nationalIdCardData,
                'national_id_card_cloudinary_id' => null, // Not used anymore for JSON approach
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Admin staff created successfully',
                'data' => [
                    'staff' => [
                        'id' => $staff->id,
                        'user_name' => $staff->user_name,
                        'name' => $staff->name,
                        'phone' => $staff->phone,
                        'email' => $staff->email,
                        'address' => $staff->address,
                        'designation' => $staff->designation,
                        'type' => $staff->type,
                        'status' => $staff->status,
                        'gender' => $staff->gender,
                        'profile_picture' => $staff->profile_picture,
                        'national_id_card' => $staff->national_id_card,
                        'created_at' => $staff->created_at,
                    ],
                    'credentials' => [
                        'username' => $staffUsername,
                        'password' => $request->password,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create admin staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all admin staff members with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Admin::query();

            // Filter by type (only staff by default)
            if ($request->has('type')) {
                $query->where('type', $request->type);
            } else {
                $query->where('type', 'staff');
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name, email, username, phone, designation, or address (optional)
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('user_name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('designation', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('address', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch staffs with pagination
            $staffs = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Admin staffs retrieved successfully',
                'data' => $staffs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin staffs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single admin staff by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $staff = Admin::findOrFail($id);

            // Check if the admin is a staff member
            if (!$staff->isStaff()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This admin is not a staff member'
                ], 400);
            }

            // Make password visible in response (hashed)
            $staff->makeVisible('password');

            return response()->json([
                'success' => true,
                'message' => 'Admin staff retrieved successfully',
                'data' => $staff
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Admin staff not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update admin staff information
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
            'phone' => 'sometimes|required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:admin,phone,' . $id . ',id',
            'email' => 'sometimes|required|email|max:255|unique:admin,email,' . $id . ',id',
            'password' => 'nullable|string|min:6',
            'address' => 'nullable|string|max:500',
            'designation' => 'nullable|string|max:255',
            'gender' => 'sometimes|required|in:male,female,others',
            'status' => 'nullable|in:active,inactive',
            'profile_picture' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',
            'national_id_card' => 'nullable|array|size:2',
            'national_id_card.*' => 'image|mimes:jpeg,jpg,png,gif,svg|max:5120',
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
            $staff = Admin::findOrFail($id);

            // Check if the admin is a staff member
            if (!$staff->isStaff()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This admin is not a staff member'
                ], 400);
            }

            // Handle profile picture upload to Cloudinary
            if ($request->hasFile('profile_picture')) {
                // Delete old profile picture from Cloudinary if exists
                if ($staff->profile_cloudinary_id) {
                    CloudinaryHelper::deleteImage($staff->profile_cloudinary_id);
                }

                // Upload new profile picture
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('profile_picture'),
                    'maxreward/admin/profile_pictures'
                );

                // Update staff with new profile picture data
                $staff->profile_picture = $uploadResult['url'];
                $staff->profile_cloudinary_id = $uploadResult['public_id'];
                $staff->save();
            }

            // Handle national ID card upload to Cloudinary (array of 2 images)
            if ($request->hasFile('national_id_card')) {
                // Delete old national ID card images from Cloudinary if exists
                $existingData = $staff->national_id_card;
                if ($existingData) {
                    if (isset($existingData['front']['cloudinary_id'])) {
                        CloudinaryHelper::deleteImage($existingData['front']['cloudinary_id']);
                    }
                    if (isset($existingData['back']['cloudinary_id'])) {
                        CloudinaryHelper::deleteImage($existingData['back']['cloudinary_id']);
                    }
                }

                // Upload new images
                $files = $request->file('national_id_card');
                $uploadedImages = [];

                foreach ($files as $index => $file) {
                    $uploadResult = CloudinaryHelper::uploadImage(
                        $file,
                        'maxreward/admin/national_id_cards'
                    );

                    $key = $index === 0 ? 'front' : 'back';
                    $uploadedImages[$key] = [
                        'url' => $uploadResult['url'],
                        'cloudinary_id' => $uploadResult['public_id'],
                    ];
                }

                // Update staff with new national ID card data
                $staff->national_id_card = $uploadedImages;
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
            if ($request->has('address')) {
                $staffData['address'] = $request->address;
            }
            if ($request->has('designation')) {
                $staffData['designation'] = $request->designation;
            }
            if ($request->has('gender')) {
                $staffData['gender'] = $request->gender;
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

            // Refresh staff data
            $staff->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Admin staff updated successfully',
                'data' => $staff
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Admin staff not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update admin staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete admin staff member
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
            $staff = Admin::findOrFail($id);

            // Check if the admin is a staff member
            if (!$staff->isStaff()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This admin is not a staff member'
                ], 400);
            }

            // Store staff info for response
            $staffInfo = [
                'id' => $staff->id,
                'user_name' => $staff->user_name,
                'name' => $staff->name,
                'email' => $staff->email,
            ];

            // Delete profile picture from Cloudinary if exists
            if ($staff->profile_cloudinary_id) {
                CloudinaryHelper::deleteImage($staff->profile_cloudinary_id);
            }

            // Delete national ID card images from Cloudinary if exists (JSON format)
            if ($staff->national_id_card) {
                $nationalIdData = $staff->national_id_card;

                // Delete front image
                if (isset($nationalIdData['front']['cloudinary_id'])) {
                    CloudinaryHelper::deleteImage($nationalIdData['front']['cloudinary_id']);
                }

                // Delete back image
                if (isset($nationalIdData['back']['cloudinary_id'])) {
                    CloudinaryHelper::deleteImage($nationalIdData['back']['cloudinary_id']);
                }
            }

            // Delete the staff
            $staff->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Admin staff deleted successfully',
                'data' => $staffInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Admin staff not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete admin staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all admin staff members (without pagination)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStaffs()
    {
        try {
            // Get all staffs (only type='staff')
            $staffs = Admin::where('type', 'staff')
                          ->orderBy('created_at', 'desc')
                          ->get();

            return response()->json([
                'success' => true,
                'message' => 'Admin staffs retrieved successfully',
                'data' => [
                    'staffs' => $staffs,
                    'total' => $staffs->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin staffs',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function changePassword(Request $request)
    {
        try {
            $admin = auth()->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'new_password' => 'required|string|min:6|max:255',
                'confirmation_password' => 'required|string|same:new_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if current password is correct
            if (!Hash::check($request->password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as current password
            if (Hash::check($request->new_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password cannot be the same as current password'
                ], 400);
            }

            // Update password
            $admin->password = Hash::make($request->new_password);
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
