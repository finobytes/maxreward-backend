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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('user_name')->unique()->comment('Phone number (e.g., 60146275114) for general / (e.g., C12457552) for corporate');
            $table->string('name');
            $table->string('phone')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->text('address')->nullable();
            $table->string('image')->nullable()->comment('Cloudinary base URL');
            $table->string("image_cloudinary_id")->nullable();
            $table->enum('member_type', ['general', 'corporate'])->default('general');
            $table->enum('gender_type', ['male', 'female', 'others'])->nullable();
            $table->enum('status', ['active', 'suspended', 'blocked'])->default('active');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->onDelete('set null');
            $table->enum('member_created_by', ['general', 'corporate', 'merchant','admin'])->default('general')->nullable();
            $table->string('referral_code', 8)->unique()->nullable()->comment('8 character unique code');
            $table->timestamps();
            $table->softDeletes();
            // Indexes
            $table->index('name');
            $table->index('user_name');
            $table->index('phone');
            $table->index('referral_code');
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
