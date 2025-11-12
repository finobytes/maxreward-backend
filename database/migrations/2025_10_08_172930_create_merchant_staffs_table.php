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
        Schema::create('merchant_staffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('user_name')->unique()->comment('Unique ID (e.g., M123456789)');
            $table->string('name')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string("image")->nullable()->comment('Cloudinary base URL');
            $table->string("image_cloudinary_id")->nullable();
            $table->string('password');
            $table->text('address')->nullable();
            $table->enum('type', ['merchant', 'staff']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('gender_type', ['male', 'female', 'others'])->nullable();
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('user_name');
            $table->index('name');
            $table->index('email');
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_staffs');
    }
};
