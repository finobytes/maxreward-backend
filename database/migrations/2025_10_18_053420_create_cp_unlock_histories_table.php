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
        Schema::create('cp_unlock_histories', function (Blueprint $table) {
            $table->id();
             $table->unsignedBigInteger('member_id');
            $table->integer('previous_referrals');
            $table->integer('new_referrals');
            $table->integer('previous_unlocked_level');
            $table->integer('new_unlocked_level');
            $table->double('released_cp_amount')->default(0)->comment('Amount moved from onhold to available');
            // $table->timestamp('created_at')->useCurrent();
            
            $table->index('member_id');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_unlock_histories');
    }
};
