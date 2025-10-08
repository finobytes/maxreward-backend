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
            $table->string('name');
            $table->string('phone')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
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
