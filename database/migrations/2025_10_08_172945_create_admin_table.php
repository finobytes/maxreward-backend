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
        Schema::create('admin', function (Blueprint $table) {
            $table->id();
            $table->string('user_name')->unique()->comment('Unique ID (e.g., A196345678)');
            $table->string('name');
            $table->string('phone')->unique()->nullable();
            $table->text('address')->nullable();
            $table->string('designation')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('type', ['admin', 'staff'])->default('staff');
            $table->string('profile_picture')->nullable()->comment('Cloudinary base URL');
            $table->string('profile_cloudinary_id')->nullable();
            $table->json('national_id_card')->nullable()->comment('Cloudinary URLs');
            $table->string('national_id_card_cloudinary_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('gender', ['male', 'female', 'others'])->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin');
    }
};
