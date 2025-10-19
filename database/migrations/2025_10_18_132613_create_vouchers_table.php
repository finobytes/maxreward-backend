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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->enum('voucher_type', ['max', 'refer'])->comment('max=available points, refer=referral points');
            $table->unsignedBigInteger('denomination_id');
            $table->integer('quantity')->default(1);
            $table->enum('payment_method', ['online', 'manual']);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('voucher_type');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('denomination_id')->references('id')->on('denominations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
