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
     * Create a new role
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'guard_name' => 'required|in:admin,merchant,member',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if role already exists
            $existingRole = Role::where('name', $request->name)
                              ->where('guard_name', $request->guard_name)
                              ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role already exists for this guard',
                    'data' => $existingRole
                ], 409);
            }

            // Create role
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            // Assign permissions if provided
            if ($request->has('permissions') && count($request->permissions) > 0) {
                // Filter permissions by guard
                $validPermissions = Permission::where('guard_name', $request->guard_name)
                                             ->whereIn('name', $request->permissions)
                                             ->pluck('name')
                                             ->toArray();

                $role->syncPermissions($validPermissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => [
                    'role' => $role,
                    'permissions' => $role->permissions->pluck('name')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a role
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $role = Role::findOrFail($id);

            // Update name if provided
            if ($request->has('name')) {
                // Check if new name already exists
                $existingRole = Role::where('name', $request->name)
                                  ->where('guard_name', $role->guard_name)
                                  ->where('id', '!=', $id)
                                  ->first();

                if ($existingRole) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Role name already exists for this guard'
                    ], 409);
                }

                $role->name = $request->name;
                $role->save();
            }

            // Update permissions if provided
            if ($request->has('permissions')) {
                // Filter permissions by guard
                $validPermissions = Permission::where('guard_name', $role->guard_name)
                                             ->whereIn('name', $request->permissions)
                                             ->pluck('name')
                                             ->toArray();

                $role->syncPermissions($validPermissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => [
                    'role' => $role,
                    'permissions' => $role->permissions->pluck('name')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a role
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteRole($id)
    {
        try {
            $role = Role::findOrFail($id);

            // Check if any users have this role
            $usersWithRole = \DB::table('model_has_roles')
                              ->where('role_id', $id)
                              ->count();

            if ($usersWithRole > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role. ' . $usersWithRole . ' user(s) currently have this role.',
                    'users_count' => $usersWithRole
                ], 409);
            }

            $roleName = $role->name;
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
                'data' => [
                    'deleted_role' => $roleName
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
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
     * Create a new permission
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'guard_name' => 'required|in:admin,merchant,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if permission already exists
            $existingPermission = Permission::where('name', $request->name)
                                           ->where('guard_name', $request->guard_name)
                                           ->first();

            if ($existingPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission already exists for this guard',
                    'data' => $existingPermission
                ], 409);
            }

            // Create permission
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a permission
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePermission($id)
    {
        try {
            $permission = Permission::findOrFail($id);

            // Check if any roles have this permission
            $rolesWithPermission = \DB::table('role_has_permissions')
                                     ->where('permission_id', $id)
                                     ->count();

            if ($rolesWithPermission > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete permission. ' . $rolesWithPermission . ' role(s) currently have this permission.',
                    'roles_count' => $rolesWithPermission
                ], 409);
            }

            $permissionName = $permission->name;
            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully',
                'data' => [
                    'deleted_permission' => $permissionName
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign permissions to a role
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissionsToRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $role = Role::findOrFail($id);

            // Filter permissions by guard to ensure they match the role's guard
            $validPermissions = Permission::where('guard_name', $role->guard_name)
                                         ->whereIn('name', $request->permissions)
                                         ->pluck('name')
                                         ->toArray();

            if (empty($validPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid permissions found for this role guard',
                    'data' => [
                        'role_guard' => $role->guard_name,
                        'requested_permissions' => $request->permissions
                    ]
                ], 400);
            }

            // Sync permissions (replaces all existing permissions)
            $role->syncPermissions($validPermissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions assigned successfully',
                'data' => [
                    'role' => $role,
                    'permissions' => $role->permissions->pluck('name'),
                    'assigned_count' => count($validPermissions)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoleToMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
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
            $member = \App\Models\Member::findOrFail($request->member_id);

            // Check if role exists for member guard
            $role = Role::where('name', $request->role)
                       ->where('guard_name', 'member')
                       ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found for member guard',
                    'available_roles' => Role::where('guard_name', 'member')->pluck('name')
                ], 404);
            }

            // Assign role
            $member->syncRoles([$request->role]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => [
                    'member' => $member,
                    'role' => $request->role,
                    'permissions' => $member->getAllPermissions()->pluck('name'),
                    'all_roles' => $member->getRoleNames()
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
     * Remove role from member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRoleFromMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
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
            $member = \App\Models\Member::findOrFail($request->member_id);
            $member->removeRole($request->role);

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => [
                    'member' => $member,
                    'removed_role' => $request->role,
                    'remaining_roles' => $member->getRoleNames()
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
     * Assign direct permissions to merchant staff (in addition to role permissions)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignDirectPermissionsToStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:merchant_staffs,id',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
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

            // Filter permissions by merchant guard
            $validPermissions = Permission::where('guard_name', 'merchant')
                                         ->whereIn('name', $request->permissions)
                                         ->pluck('name')
                                         ->toArray();

            if (empty($validPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid permissions found for merchant guard',
                ], 400);
            }

            // Give direct permissions (in addition to role permissions)
            $staff->givePermissionTo($validPermissions);

            return response()->json([
                'success' => true,
                'message' => 'Direct permissions assigned successfully',
                'data' => [
                    'staff' => $staff,
                    'role_permissions' => $staff->getPermissionsViaRoles()->pluck('name'),
                    'direct_permissions' => $staff->getDirectPermissions()->pluck('name'),
                    'all_permissions' => $staff->getAllPermissions()->pluck('name'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove direct permissions from merchant staff
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeDirectPermissionsFromStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:merchant_staffs,id',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string',
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

            // Revoke direct permissions
            $staff->revokePermissionTo($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Direct permissions removed successfully',
                'data' => [
                    'staff' => $staff,
                    'role_permissions' => $staff->getPermissionsViaRoles()->pluck('name'),
                    'direct_permissions' => $staff->getDirectPermissions()->pluck('name'),
                    'all_permissions' => $staff->getAllPermissions()->pluck('name'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permissions',
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
            'user_type' => 'required|in:admin,merchant,member',
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
            } elseif ($request->user_type === 'merchant') {
                $user = MerchantStaff::findOrFail($request->user_id);
            } else {
                $user = \App\Models\Member::findOrFail($request->user_id);
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
