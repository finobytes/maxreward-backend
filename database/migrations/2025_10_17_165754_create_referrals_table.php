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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_member_id')->nullable()->comment('Referrer member ID');
            $table->unsignedBigInteger('child_member_id')->comment('New member ID');
            $table->timestamps();
            
            $table->index('parent_member_id');
            $table->index('child_member_id');
            
            $table->foreign('parent_member_id')->references('id')->on('members');
            $table->foreign('child_member_id')->references('id')->on('members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
