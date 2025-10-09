<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Member::create([
            'user_name' => 'member1',
            'name' => 'Member One',
            'phone' => '1234567890',
            'email' => 'member1@example.com',
            'password' => Hash::make('password'),
            'member_type' => 'general',
            'gender_type' => 'male',
            'status' => 'active',
            'merchant_id' => null,
            'member_created_by' => 'general',
            'referral_code' => 'MEMBER1',
        ]);

        Member::create([
            'user_name' => 'member2',
            'name' => 'Member Two',
            'phone' => '1234567891',
            'email' => 'member2@example.com',
            'password' => Hash::make('password'),
            'member_type' => 'general',
            'gender_type' => 'female',
            'status' => 'active',
            'merchant_id' => null,
            'member_created_by' => 'general',
            'referral_code' => 'MEMBER2',
        ]);

        Member::create([
            'user_name' => 'member3',
            'name' => 'Member Three',
            'phone' => '1234567892',
            'email' => 'member3@example.com',
            'password' => Hash::make('password'),
            'member_type' => 'general',
            'gender_type' => 'male',
            'status' => 'active',
            'merchant_id' => null,
            'member_created_by' => 'general',
            'referral_code' => 'MEMBER3',
        ]);

        Member::create([
            'user_name' => 'member4',
            'name' => 'Member Four',
            'phone' => '1234567893',
            'email' => 'member4@example.com',
            'password' => Hash::make('password'),
            'member_type' => 'general',
            'gender_type' => 'female',
            'status' => 'active',
            'merchant_id' => null,
            'member_created_by' => 'general',
            'referral_code' => 'MEMBER4',
        ]);

        Member::create([
            'user_name' => 'member5',
            'name' => 'Member Five',
            'phone' => '1234567894',
            'email' => 'member5@example.com',
            'password' => Hash::make('password'),
            'member_type' => 'general',
            'gender_type' => 'male',
            'status' => 'active',
            'merchant_id' => null,
            'member_created_by' => 'general',
            'referral_code' => 'MEMBER5',
        ]);
    }
}