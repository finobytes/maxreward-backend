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
        Schema::create('member_community_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->integer('level')->comment('Community level (1-30)');
            $table->double('total_cp')->default(0)->comment('Total CP earned at this level');
            $table->double('available_cp')->default(0)->comment('Unlocked CP at this level');
            $table->double('onhold_cp')->default(0)->comment('Locked CP at this level (until referral milestone)');
            $table->boolean('is_locked')->default(true)->comment('TRUE if this level is locked');
            $table->timestamps();
            
            $table->unique(['member_id', 'level'], 'unique_member_level');
            $table->index('member_id');
            $table->index('level');
            $table->index('is_locked');
            
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_community_points');
    }
};
