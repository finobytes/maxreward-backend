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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 100)->unique()->comment('Generated from sku_short_code + attributes');

            // Pricing
            $table->decimal('regular_price', 10, 2)->default(0);
            $table->double('regular_point', 15, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->double('sale_point', 15, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();

            // Inventory
            $table->integer('actual_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(2);

            $table->string('ean_no', 50)->unique()->nullable()->comment('Barcode/EAN');
            $table->decimal('unit_weight', 8, 2)->default(0)->comment('Weight in grams');

            $table->json('images')->nullable()->comment('Array of Cloudinary URLs');

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('sku');
            $table->index('is_active');
            $table->index('deleted_at');

            // Foreign Keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
