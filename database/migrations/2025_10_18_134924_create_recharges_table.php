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
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->enum('voucher_type', ['max', 'refer'])->comment('max=available points, refer=referral points');
            $table->double('total_amount');
            $table->enum('recharge_type', ['online', 'manual']);
            $table->string('manual_payment_docs_url', 500)->nullable();
            $table->string('manual_payment_docs_cloudinary_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('Admin ID who approved');
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('recharge_type');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('approved_by')->references('id')->on('admin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
