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
        Schema::create('merchant_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('zone_id');
            $table->unsignedBigInteger('method_id');
            
            // Weight-based rates
            $table->decimal('weight_from', 8, 2)->default(0); // grams
            $table->decimal('weight_to', 8, 2); // grams
            
            // Pricing
            $table->double('base_points', 15, 2)->default(0);
            $table->double('per_kg_points', 15, 2)->default(0);
            
            // Optional: For merchants who want to offer free shipping
            $table->double('free_shipping_min_order', 15, 2)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
            $table->foreign('method_id')->references('id')->on('shipping_methods')->onDelete('cascade');
            
            $table->index(['merchant_id', 'zone_id', 'method_id']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_shipping_rates');
    }
};
