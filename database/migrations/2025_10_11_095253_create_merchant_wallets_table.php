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
        Schema::create('merchant_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->unique();
            $table->double('total_points', 15, 2)->default(0)->comment('Lifetime total points earned');
            $table->timestamps();
            $table->softDeletes();
            // Foreign Key
            $table->foreign('merchant_id')->references('id')->on('merchants');
            // Indexes
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_wallets');
    }
};
