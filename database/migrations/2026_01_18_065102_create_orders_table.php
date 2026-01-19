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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('member_id');
            
            $table->string('order_number')->unique()->comment('Auto: YYYYMMDDHMS-8 unique character');
            
            $table->enum('status', ['pending', 'completed', 'returned', 'cancelled'])->default('pending');
            
            // Points breakdown
            $table->double('shipping_points')->default(0);
            $table->double('total_points')->default(0)->comment('Final amount paid');
            
            // Customer details (snapshot at order time)
            $table->string('customer_full_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone');
            $table->text('customer_address');
            $table->string('customer_postcode')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('customer_country')->nullable();
            
            // Shipping details
            $table->string('tracking_number')->nullable();
            $table->decimal('total_weight', 10, 2)->nullable()->comment('Total order weight in grams');
            
            // Timestamps
            $table->dateTime('completed_at')->nullable();
            
            $table->unsignedBigInteger('cancelled_by')->nullable()->comment('merchant_staff_id or admin_id');
            $table->text('cancelled_reason')->nullable();
            
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('deleted_at')->nullable();
            
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('member_id');
            $table->index('order_number');
            $table->index('status');
            $table->index('created_at');
            $table->index('customer_full_name');
            $table->index('customer_email');
            $table->index('customer_postcode');
            $table->index('customer_city');
            $table->index('customer_country');
            
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('restrict');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
