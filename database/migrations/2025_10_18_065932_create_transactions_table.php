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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('referral_member_id')->nullable()->comment('Who receives the invite/points');
            $table->decimal('transaction_points', 10, 2);
            $table->decimal('bap', 10, 2)->default(0)->comment('Balance available points');
            $table->decimal('brp', 10, 2)->default(0)->comment('Balance referral points');
            $table->enum('transaction_type', ['pp', 'rp', 'cp', 'cr', 'dp', 'ap', 'vrp', 'vap'])
                ->comment('pp=personal, rp=referral, cp=community, cr=company reserve, dp=deducted, ap=added points, vrp=voucher referral, vap=voucher available');
            $table->enum('points_type', ['debited', 'credited']);
            $table->text('transaction_reason')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('transaction_type');
            $table->index('points_type');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('referral_member_id')->references('id')->on('members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
