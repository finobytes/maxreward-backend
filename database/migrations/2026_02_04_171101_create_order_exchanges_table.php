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
        Schema::create('order_exchanges', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('member_id');
            
            // Original product details
            $table->unsignedBigInteger('original_product_variation_id');
            $table->string('original_variant_name')->comment('e.g., Red - Size S');
            
            // Exchange to product details
            $table->unsignedBigInteger('exchange_product_variation_id');
            $table->string('exchange_variant_name')->comment('e.g., Blue - Size M');
            
            $table->integer('quantity')->default(1);
            $table->text('reason')->nullable()->comment('Why exchange requested');
            
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable()->comment('Merchant staff who processed');
            $table->dateTime('processed_at')->nullable();

            $table->softDeletes();
            
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('merchant_id');
            $table->index('member_id');
            $table->index('status');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('restrict');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('restrict');
            $table->foreign('original_product_variation_id')->references('id')->on('product_variations')->onDelete('restrict');
            $table->foreign('exchange_product_variation_id')->references('id')->on('product_variations')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_exchanges');
    }
};
