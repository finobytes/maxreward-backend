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
        Schema::create('cp_distribution_pools', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id');
            $table->unsignedBigInteger('source_member_id');
            $table->double('total_cp_amount')->default(0);
            $table->double('total_transaction_amount')->default(0);
            $table->double('total_cp_distributed')->default(0);
            $table->string('phone');
            $table->integer('total_referrals')->default(0);
            $table->integer('unlocked_level')->default(5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_distribution_pools');
    }
};
