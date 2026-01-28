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
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->text('address')->nullable();
            $table->string('image')->nullable()->comment('Cloudinary base URL');
            $table->string("image_cloudinary_id")->nullable();
            $table->enum('member_type', ['general', 'corporate'])->default('general');
            $table->enum('gender_type', ['male', 'female', 'others'])->nullable();
            $table->enum('status', ['active', 'suspended', 'blocked'])->default('active');
            $table->enum('member_created_by', ['general', 'corporate', 'merchant','admin'])->default('general')->nullable();
            $table->string('referral_code', 8)->unique()->nullable()->comment('8 character unique code');
            $table->string('block_reason')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->integer('country_id')->nullable();
            $table->string('country_code')->nullable();
            
            // NEW FIELDS FOR BRANDING/LOGO INHERITANCE
            $table->unsignedBigInteger('company_id')->nullable()->comment('Reference to company_infos table - for showing company logo');
            $table->unsignedBigInteger('merchant_id')->nullable()->comment('Reference to merchants table - for corporate members or members referred by corporate');
            
            $table->unsignedBigInteger('suspended_by')->nullable()->comment('Admin ID who suspended');
            $table->unsignedBigInteger('blocked_by')->nullable()->comment('Admin ID who blocked');
            $table->unsignedBigInteger('referred_by')->nullable()->comment('Member ID who referred');
            $table->timestamp('last_status_changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('company_infos')->onDelete('set null');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('set null');

            // IMPORTANT: Composite unique constraint
            // This allows same phone to be used once for 'general' and once for 'corporate'
            $table->unique(['phone', 'member_type'], 'unique_phone_member_type');
            $table->unique(['email', 'member_type'], 'unique_email_member_type');

            // Indexes
            $table->index('name');
            $table->index('user_name');
            $table->index('phone');
            $table->index('referral_code');
            $table->index('company_id');
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