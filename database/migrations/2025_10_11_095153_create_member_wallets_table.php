<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->unique();
            
            // Referral & Level Info
            $table->integer('total_referrals')->default(0)->comment('Direct referral count');
            $table->integer('unlocked_level')->default(0)->comment('Max accessible CP level (5,10,15,20,25,30)');
            
            // Points Breakdown
            $table->double('onhold_points', 15, 2)->default(0)->comment('Locked points (CP locked levels)');
            $table->double('total_points', 15, 2)->default(0)->comment('Lifetime total points earned');
            $table->double('available_points', 15, 2)->default(0)->comment('Usable balance');
            $table->double('total_rp', 15, 2)->default(0)->comment('Referral points balance');
            $table->double('total_pp', 15, 2)->default(0)->comment('Personal points balance');
            $table->double('total_cp', 15, 2)->default(0)->comment('Community points balance');
            
            $table->timestamps();
            
            // Foreign Key
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            
            // Indexes
            $table->index('member_id');
            $table->index('available_points');
            $table->index('unlocked_level');
            $table->index('total_referrals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_wallets');
    }
};
