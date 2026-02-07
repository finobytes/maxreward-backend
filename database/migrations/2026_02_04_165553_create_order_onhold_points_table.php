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
        Schema::create('order_onhold_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('member_id');
            
            // Points breakdown
            $table->double('total_points', 15, 2)->comment('Total order amount including shipping');
            $table->double('shipping_points', 15, 2)->default(0);
            $table->double('items_points', 15, 2)->comment('Total items points');
            
            // Status tracking
            $table->enum('status', ['onhold', 'released', 'refunded'])->default('onhold');
            
            // Release tracking
            $table->dateTime('shipped_at')->nullable()->comment('When order was shipped');
            $table->dateTime('auto_release_at')->nullable()->comment('Scheduled auto-release date');
            $table->dateTime('released_at')->nullable()->comment('When points were actually released');
            
            // Refund tracking (if cancelled)
            $table->dateTime('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();

            $table->softDeletes();
            
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('merchant_id');
            $table->index('member_id');
            $table->index('status');
            $table->index('auto_release_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('restrict');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_onhold_points');
    }
};
