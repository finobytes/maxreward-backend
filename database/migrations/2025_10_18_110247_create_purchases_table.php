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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('member_id');
            $table->enum('merchant_selection_type', ['qrcode', 'unique_number']);
            $table->decimal('transaction_amount', 10, 2)->comment('Total purchase amount');
            $table->decimal('redeem_amount', 10, 2)->default(0)->comment('Points redeemed');
            $table->decimal('cash_redeem_amount', 10, 2)->default(0)->comment('Cash equivalent');
            $table->enum('payment_method', ['online', 'offline'])->default('offline');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('member_id');
            $table->index('status');
            $table->index('created_at');
            
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
