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

        // Define all admin permissions
        $permissions = [
            // Merchant Management
            'merchant.view' => 'View merchants',
            'merchant.create' => 'Create merchants',
            'merchant.edit' => 'Edit merchants',
            'merchant.delete' => 'Delete merchants',
            'merchant.approve' => 'Approve merchant applications',
            'merchant.suspend' => 'Suspend merchants',

            // Member Management
            'member.view' => 'View members',
            'member.edit' => 'Edit members',
            'member.delete' => 'Delete members',
            'member.manage' => 'Full member management',

            // Product Management (Admin)
            'admin.product.view' => 'View all products',
            'admin.product.edit' => 'Edit any product',
            'admin.product.delete' => 'Delete any product',

            // Category & Brand Management
            'category.manage' => 'Manage categories',
            'brand.manage' => 'Manage brands',
            'attribute.manage' => 'Manage attributes',

            // Transaction Management
            'admin.transaction.view' => 'View all transactions',
            'admin.transaction.manage' => 'Manage transactions',

            // Report & Analytics
            'admin.report.view' => 'View admin reports',
            'admin.report.export' => 'Export admin reports',

            // Staff Management
            'admin.staff.view' => 'View admin staff',
            'admin.staff.create' => 'Create admin staff',
            'admin.staff.edit' => 'Edit admin staff',
            'admin.staff.delete' => 'Delete admin staff',

            // System Settings
            'settings.manage' => 'Manage system settings',
            'settings.view' => 'View system settings',
        ];

        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName]
            );
        }

        // Create Admin Roles

        // 1. Super Admin - Full access
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => $guardName]
        );
        $superAdminRole->syncPermissions(array_keys($permissions));

        // 2. Admin - Most permissions
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guardName]
        );
        $adminRole->syncPermissions([
            'merchant.view',
            'merchant.edit',
            'merchant.approve',
            'member.view',
            'member.edit',
            'admin.product.view',
            'admin.product.edit',
            'category.manage',
            'brand.manage',
            'attribute.manage',
            'admin.transaction.view',
            'admin.report.view',
            'admin.report.export',
            'settings.view',
        ]);

        // 3. Staff - Limited access
        $adminStaffRole = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => $guardName]
        );
        $adminStaffRole->syncPermissions([
            'merchant.view',
            'member.view',
            'admin.product.view',
            'admin.transaction.view',
            'admin.report.view',
        ]);

        $this->command->info('✅ Admin permissions and roles created successfully!');
    }
}
