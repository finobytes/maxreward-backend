<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        Admin::truncate();

        // Super Admin
        Admin::create([
            'user_name' => 'A196345678',
            'name' => 'Super Admin',
            'phone' => '01712345678',
            'address' => 'Dhaka, Bangladesh',
            'designation' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'), // Must use Hash::make()
            'type' => 'admin',
            'status' => 'active',
            'gender' => 'male',
        ]);

        // Staff Member 1
        Admin::create([
            'user_name' => 'S196345679',
            'name' => 'Staff Member',
            'phone' => '01812345678',
            'address' => 'Chittagong, Bangladesh',
            'designation' => 'Assistant',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'), // Must use Hash::make()
            'type' => 'staff',
            'status' => 'active',
            'gender' => 'female',
        ]);

        // Additional Admin
        Admin::create([
            'user_name' => 'A196345680',
            'name' => 'Admin User',
            'phone' => '01912345678',
            'address' => 'Sylhet, Bangladesh',
            'designation' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'), // Must use Hash::make()
            'type' => 'admin',
            'status' => 'active',
            'gender' => 'male',
        ]);

        // Inactive Staff (for testing)
        Admin::create([
            'user_name' => 'S196345681',
            'name' => 'Inactive Staff',
            'phone' => '01612345678',
            'address' => 'Rajshahi, Bangladesh',
            'designation' => 'Support Staff',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'), // Must use Hash::make()
            'type' => 'staff',
            'status' => 'inactive',
            'gender' => 'others',
        ]);
    }
}