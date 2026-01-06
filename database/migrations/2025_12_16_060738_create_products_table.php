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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();

            $table->string('name', 255);
            $table->string('slug', 300)->unique();
            $table->string('sku_short_code', 50)->unique()->comment('Base SKU for variations');

            // Pricing in Points & Currency
            $table->decimal('regular_price', 10, 2)->default(0)->comment('Cash price');
            $table->double('regular_point', 15, 2)->default(0)->comment('Point price');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Discounted cash price');
            $table->double('sale_point', 15, 2)->nullable()->comment('Discounted point price');
            $table->decimal('cost_price', 10, 2)->nullable()->comment('Merchant cost');

            $table->decimal('unit_weight', 8, 2)->default(0)->comment('Weight in grams');

            $table->string('short_description', 500)->nullable();
            $table->text('description');

            $table->json('images')->nullable()->comment('Array of Cloudinary URLs');

            $table->enum('type', ['simple', 'variable'])->default('simple');
            $table->enum('status', ['active', 'inactive', 'draft', 'out_of_stock'])->default('draft');

            $table->unsignedBigInteger('deleted_by')->nullable()->comment('Soft delete - merchant_staff_id');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Last updated by merchant_staff_id');
            $table->softDeletes();

            $table->timestamps();

            // Indexes
            $table->index('merchant_id');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('brand_id');
            $table->index('model_id');
            $table->index('gender_id');
            $table->index('status');
            $table->index('type');
            $table->index('slug');
            $table->index('sku_short_code');
            $table->index('deleted_at');

            // Foreign Keys
            // $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('subcategory_id')->references('id')->on('sub_categories');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('model_id')->references('id')->on('models');
            $table->foreign('gender_id')->references('id')->on('genders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
