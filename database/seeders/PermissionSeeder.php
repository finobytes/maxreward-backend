<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==================== MERCHANT PERMISSIONS ====================
        $this->createMerchantPermissions();

        // ==================== ADMIN PERMISSIONS ====================
        $this->createAdminPermissions();
    }

    /**
     * Create Merchant Staff permissions and roles
     */
    private function createMerchantPermissions()
    {
        $guardName = 'merchant';

        // Define all merchant permissions
        $permissions = [
            // Product Management
            'product.view' => 'View products',
            'product.create' => 'Create new products',
            'product.edit' => 'Edit products',
            'product.delete' => 'Delete products',
            'product.manage' => 'Full product management',

            // Order Management
            'order.view' => 'View orders',
            'order.create' => 'Create orders',
            'order.edit' => 'Edit orders',
            'order.manage' => 'Full order management',

            // Staff Management
            'staff.view' => 'View staff members',
            'staff.create' => 'Create staff members',
            'staff.edit' => 'Edit staff members',
            'staff.delete' => 'Delete staff members',
            'staff.manage' => 'Full staff management',

            // Report & Analytics
            'report.view' => 'View reports and analytics',
            'report.export' => 'Export reports',

            // Merchant Settings
            'merchant.settings' => 'Manage merchant settings',
            'merchant.profile' => 'Edit merchant profile',

            // Transaction & Wallet
            'transaction.view' => 'View transactions',
            'wallet.view' => 'View wallet balance',
            'wallet.manage' => 'Manage wallet',
        ];

        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName]
            );
        }

        // Create Roles and assign permissions

        // 1. Owner Role - Full access to everything
        $ownerRole = Role::firstOrCreate(
            ['name' => 'owner', 'guard_name' => $guardName]
        );
        $ownerRole->syncPermissions(array_keys($permissions));

        // 2. Manager Role - Most permissions except critical ones
        $managerRole = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => $guardName]
        );
        $managerRole->syncPermissions([
            'product.view',
            'product.create',
            'product.edit',
            'order.view',
            'order.create',
            'order.edit',
            'order.manage',
            'staff.view',
            'report.view',
            'report.export',
            'transaction.view',
            'wallet.view',
        ]);

        // 3. Staff Role - Basic access
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => $guardName]
        );
        $staffRole->syncPermissions([
            'product.view',
            'order.view',
            'order.create',
            'transaction.view',
        ]);

        // 4. Sales Person Role - Sales focused
        $salesRole = Role::firstOrCreate(
            ['name' => 'sales', 'guard_name' => $guardName]
        );
        $salesRole->syncPermissions([
            'product.view',
            'order.view',
            'order.create',
            'order.edit',
            'report.view',
        ]);

        $this->command->info('✅ Merchant permissions and roles created successfully!');
    }

    /**
     * Create Admin permissions and roles
     */
    private function createAdminPermissions()
    {
        $guardName = 'admin';

        // ==================== ROLE-SPECIFIC PERMISSIONS ====================

        // SUPER ADMIN PERMISSIONS
        $superAdminPermissions = [
            'super_admin.merchant.view' => 'View merchants',
            'super_admin.merchant.create' => 'Create merchants',
            'super_admin.merchant.edit' => 'Edit merchants',
            'super_admin.merchant.delete' => 'Delete merchants',
            'super_admin.merchant.approve' => 'Approve merchants',
            'super_admin.merchant.suspend' => 'Suspend merchants',
            'super_admin.member.view' => 'View members',
            'super_admin.member.edit' => 'Edit members',
            'super_admin.member.delete' => 'Delete members',
            'super_admin.member.manage' => 'Full member management',
            'super_admin.product.view' => 'View all products',
            'super_admin.product.create' => 'Create products',
            'super_admin.product.edit' => 'Edit any product',
            'super_admin.product.delete' => 'Delete any product',
            'super_admin.category.manage' => 'Manage categories',
            'super_admin.brand.manage' => 'Manage brands',
            'super_admin.attribute.manage' => 'Manage attributes',
            'super_admin.transaction.view' => 'View all transactions',
            'super_admin.transaction.manage' => 'Manage transactions',
            'super_admin.report.view' => 'View admin reports',
            'super_admin.report.export' => 'Export admin reports',
            'super_admin.staff.view' => 'View admin staff',
            'super_admin.staff.create' => 'Create admin staff',
            'super_admin.staff.edit' => 'Edit admin staff',
            'super_admin.staff.delete' => 'Delete admin staff',
            'super_admin.settings.manage' => 'Manage system settings',
            'super_admin.settings.view' => 'View system settings',
        ];

        // ADMIN PERMISSIONS
        $adminPermissions = [
            'admin.merchant.view' => 'View merchants',
            'admin.merchant.edit' => 'Edit merchants',
            'admin.merchant.approve' => 'Approve merchants',
            'admin.member.view' => 'View members',
            'admin.member.edit' => 'Edit members',
            'admin.product.view' => 'View products',
            'admin.product.edit' => 'Edit products',
            'admin.category.manage' => 'Manage categories',
            'admin.brand.manage' => 'Manage brands',
            'admin.attribute.manage' => 'Manage attributes',
            'admin.transaction.view' => 'View transactions',
            'admin.report.view' => 'View reports',
            'admin.report.export' => 'Export reports',
            'admin.settings.view' => 'View settings',
        ];

        // STAFF PERMISSIONS
        $staffPermissions = [
            'staff.merchant.view' => 'View merchants',
            'staff.member.view' => 'View members',
            'staff.product.view' => 'View products',
            'staff.transaction.view' => 'View transactions',
            'staff.report.view' => 'View reports',
        ];

        // ADMINISTRATOR PERMISSIONS (Custom role with specific access)
        $administratorPermissions = [
            'administrator.merchant.view' => 'View merchants',
            'administrator.merchant.edit' => 'Edit merchants',
            'administrator.member.view' => 'View members',
            'administrator.member.edit' => 'Edit members',
            'administrator.product.view' => 'View products',
            'administrator.product.create' => 'Create products',
            'administrator.product.edit' => 'Edit products',
            'administrator.product.delete' => 'Delete products',
            'administrator.category.manage' => 'Manage categories',
            'administrator.brand.manage' => 'Manage brands',
            'administrator.transaction.view' => 'View transactions',
            'administrator.report.view' => 'View reports',
            'administrator.report.export' => 'Export reports',
            'administrator.staff.view' => 'View staff',
        ];

        // Create all permissions
        $allPermissions = array_merge(
            $superAdminPermissions,
            $adminPermissions,
            $staffPermissions,
            $administratorPermissions
        );

        foreach ($allPermissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName]
            );
        }

        // Create Roles and assign permissions

        // 1. Super Admin Role - Full access
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => $guardName]
        );
        $superAdminRole->syncPermissions(array_keys($superAdminPermissions));

        // 2. Admin Role - Most permissions
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guardName]
        );
        $adminRole->syncPermissions(array_keys($adminPermissions));

        // 3. Staff Role - Limited access
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => $guardName]
        );
        $staffRole->syncPermissions(array_keys($staffPermissions));

        // 4. Administrator Role - Custom access
        $administratorRole = Role::firstOrCreate(
            ['name' => 'administrator', 'guard_name' => $guardName]
        );
        $administratorRole->syncPermissions(array_keys($administratorPermissions));

        $this->command->info('✅ Admin permissions and roles created successfully!');
        $this->command->info('   - super_admin: ' . count($superAdminPermissions) . ' permissions');
        $this->command->info('   - admin: ' . count($adminPermissions) . ' permissions');
        $this->command->info('   - staff: ' . count($staffPermissions) . ' permissions');
        $this->command->info('   - administrator: ' . count($administratorPermissions) . ' permissions');
    }
}
