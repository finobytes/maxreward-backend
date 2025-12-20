<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use App\Models\MerchantStaff;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Assign role to admin user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoleToAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required|integer|exists:admin,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::findOrFail($request->admin_id);

            // Check if role exists for admin guard
            $role = Role::where('name', $request->role)
                       ->where('guard_name', 'admin')
                       ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found for admin guard',
                    'available_roles' => Role::where('guard_name', 'admin')->pluck('name')
                ], 404);
            }

            // Assign role
            $admin->syncRoles([$request->role]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => [
                    'admin' => $admin,
                    'role' => $request->role,
                    'permissions' => $admin->getAllPermissions()->pluck('name'),
                    'all_roles' => $admin->getRoleNames()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to merchant staff
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoleToMerchant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:merchant_staffs,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $staff = MerchantStaff::findOrFail($request->staff_id);

            // Check if role exists for merchant guard
            $role = Role::where('name', $request->role)
                       ->where('guard_name', 'merchant')
                       ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found for merchant guard',
                    'available_roles' => Role::where('guard_name', 'merchant')->pluck('name')
                ], 404);
            }

            // Assign role
            $staff->syncRoles([$request->role]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => [
                    'staff' => $staff,
                    'role' => $request->role,
                    'permissions' => $staff->getAllPermissions()->pluck('name'),
                    'all_roles' => $staff->getRoleNames()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove role from admin user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRoleFromAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required|integer|exists:admin,id',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::findOrFail($request->admin_id);
            $admin->removeRole($request->role);

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => [
                    'admin' => $admin,
                    'removed_role' => $request->role,
                    'remaining_roles' => $admin->getRoleNames()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove role from merchant staff
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRoleFromMerchant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:merchant_staffs,id',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $staff = MerchantStaff::findOrFail($request->staff_id);
            $staff->removeRole($request->role);

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => [
                    'staff' => $staff,
                    'removed_role' => $request->role,
                    'remaining_roles' => $staff->getRoleNames()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available roles
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllRoles(Request $request)
    {
        try {
            $guard = $request->get('guard', 'all');

            if ($guard === 'all') {
                $roles = Role::with('permissions')->get()->groupBy('guard_name');
            } else {
                $roles = Role::with('permissions')
                           ->where('guard_name', $guard)
                           ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available permissions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPermissions(Request $request)
    {
        try {
            $guard = $request->get('guard', 'all');

            if ($guard === 'all') {
                $permissions = Permission::all()->groupBy('guard_name');
            } else {
                $permissions = Permission::where('guard_name', $guard)->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user roles and permissions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRolesAndPermissions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|in:admin,merchant',
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->user_type === 'admin') {
                $user = Admin::findOrFail($request->user_id);
            } else {
                $user = MerchantStaff::findOrFail($request->user_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'User roles and permissions retrieved successfully',
                'data' => [
                    'user' => $user,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'direct_permissions' => $user->getDirectPermissions()->pluck('name')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user roles and permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
