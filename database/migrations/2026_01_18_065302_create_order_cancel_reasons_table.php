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
        Schema::create('order_cancel_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('cancelled_by_type', ['member', 'merchant'])->comment('Who cancelled');
            $table->unsignedBigInteger('cancelled_by_id')->comment('User ID who cancelled');
            $table->string('reason_type')->comment('E.g: out_of_stock, customer_request, wrong_order');
            $table->text('reason_details')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('reason_type');
            $table->index('cancelled_by_type');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_cancel_reasons');
    }
};
