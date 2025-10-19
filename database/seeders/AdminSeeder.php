<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Disable foreign key checks
         DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing data
        Admin::truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Super Admin (Only admin)
        Admin::create([
            'user_name'   => 'A100000001',
            'name'        => 'Super Admin',
            'phone'       => '01712345678',
            'address'     => 'Kuala Lumpur, Wilayah Persekutuan',
            'designation' => 'System Administrator',
            'email'       => 'admin@example.com',
            'password'    => Hash::make('password'),
            'type'        => 'admin',
            'status'      => 'active',
            'gender'      => 'male',
        ]);

        // Staff 1
        Admin::create([
            'user_name'   => 'A100000002',
            'name'        => 'Staff Member',
            'phone'       => '01812345678',
            'address'     => 'Johor Bahru, Johor',
            'designation' => 'Assistant',
            'email'       => 'staff@example.com',
            'password'    => Hash::make('password'),
            'type'        => 'staff',
            'status'      => 'active',
            'gender'      => 'female',
        ]);

        // Staff 2
        Admin::create([
            'user_name'   => 'A100000003',
            'name'        => 'Admin User',
            'phone'       => '01912345678',
            'address'     => 'George Town, Penang',
            'designation' => 'Manager',
            'email'       => 'manager@example.com',
            'password'    => Hash::make('password'),
            'type'        => 'staff',
            'status'      => 'active',
            'gender'      => 'male',
        ]);

        // Inactive Staff (for testing)
        Admin::create([
            'user_name'   => 'A100000004',
            'name'        => 'Inactive Staff',
            'phone'       => '01612345678',
            'address'     => 'Ipoh, Perak',
            'designation' => 'Support Staff',
            'email'       => 'inactive@example.com',
            'password'    => Hash::make('password'),
            'type'        => 'staff',
            'status'      => 'inactive',
            'gender'      => 'others',
        ]);
    }
}