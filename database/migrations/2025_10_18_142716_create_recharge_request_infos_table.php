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
        Schema::create('recharge_request_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->enum('type', ['before_recharge_request', 'after_recharge_request']);
            $table->json('payload')->nullable()->comment('Store request/response data');
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('type');
            
            $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharge_request_infos');
    }
};
