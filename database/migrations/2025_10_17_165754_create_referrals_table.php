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
            $table->unsignedBigInteger('sponsor_member_id')->nullable()->comment('ID of member who actually referred (sponsor)');
            $table->unsignedBigInteger('parent_member_id')->nullable()->comment('Placement parent id');
            $table->unsignedBigInteger('child_member_id')->comment('New member ID');
            $table->enum('position', ['left', 'right'])->nullable()->comment('Position in binary tree: left or right');
            $table->timestamps();
            
            $table->index('sponsor_member_id');
            $table->index('parent_member_id');
            $table->index('child_member_id');
            $table->index('position');
            
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
