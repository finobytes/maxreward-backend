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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            
            // Snapshot data at order time
            $table->string('name');
            $table->string('sku');
            
            $table->integer('quantity');
            $table->double('points');
            
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('merchant_id');
            $table->index('member_id');
            $table->index('product_id');
            $table->index('product_variation_id');
            $table->index('name');
            $table->index('sku');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('restrict');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
