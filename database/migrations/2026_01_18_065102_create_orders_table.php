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
            $table->unsignedBigInteger('shipping_zone_id')->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            
            $table->string('order_number')->unique()->comment('Auto: ORD-YYYYMMDD-XXXXXX Example: ORD-20260204-200201');
            
            $table->enum('status', ['pending', 'shipped', 'completed', 'cancelled', 'exchanged'])->default('pending');
            
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
            $table->string('tracking_number')->nullable()->unique();
            $table->decimal('total_weight', 10, 2)->nullable()->comment('Total order weight in grams');
            
            // Timestamps
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            
            $table->unsignedBigInteger('cancelled_by')->nullable()->comment('merchant_staff_id');
            $table->string('cancelled_reason_type')->nullable()->comment('out_of_stock, customer_request, wrong_order, etc.');
            $table->text('cancelled_reason')->nullable();
            
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->softDeletes();
            
            $table->timestamps();
            
            $table->index(['merchant_id', 'status']);
            $table->index(['member_id', 'status']);

            $table->index('status');
            $table->index('created_at');
            $table->index('customer_full_name');
            $table->index('customer_email');
            $table->index('customer_postcode');
            $table->index('customer_city');
            $table->index('customer_country');
            $table->index('shipping_zone_id');
            $table->index('shipping_method_id');
            
            // $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            // $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            // $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones')->onDelete('set null');
            // $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->onDelete('set null');
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
