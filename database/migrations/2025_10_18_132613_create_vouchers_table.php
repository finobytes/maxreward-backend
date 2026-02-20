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
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('voucher_id')->unique();
            // FPX columns for fpx transactions
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('fpx_transaction_id')->nullable();
            $table->enum('voucher_type', ['max', 'refer'])->comment('max=available points, refer=referral points');
            $table->text('denomination_history');
            $table->integer('quantity')->default(1);
            $table->enum('payment_method', ['online', 'manual']);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['success', 'failed', 'pending', 'approved', 'rejected'])->default('pending');
            $table->string('manual_payment_docs_url', 500)->nullable();
            $table->string('manual_payment_docs_cloudinary_id')->nullable();
            $table->text('rejected_reason')->nullable()->comment('Reason for rejection if status is rejected');
            $table->unsignedBigInteger('rejected_by')->nullable()->comment('Admin ID who rejected the voucher');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('voucher_type');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('merchant_id')->references('id')->on('merchants');
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
