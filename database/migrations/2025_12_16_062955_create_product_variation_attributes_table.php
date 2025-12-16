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
        Schema::create('product_variation_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variation_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('attribute_item_id');
            $table->timestamps();

            // Indexes
            $table->index('product_variation_id');
            $table->index('attribute_id');
            $table->index('attribute_item_id');

            // Foreign Keys
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('attribute_item_id')->references('id')->on('attribute_items')->onDelete('cascade');

            // Unique constraint to prevent duplicate attribute assignments
            $table->unique(['product_variation_id', 'attribute_id'], 'unique_variation_attribute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variation_attributes');
    }
};
