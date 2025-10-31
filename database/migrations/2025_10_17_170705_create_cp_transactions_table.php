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
        Schema::create('cp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->nullable()->comment('References PURCHASES table');
            $table->unsignedBigInteger('source_member_id')->comment('Member who made the purchase');
            $table->unsignedBigInteger('receiver_member_id')->comment('Member receiving CP');
            $table->integer('level')->comment('Community level (1-30)');
            $table->decimal('cp_percentage', 5, 2)->comment('CP % for this level');
            $table->double('cp_amount')->comment('CP amount credited');
            $table->boolean('is_locked')->default(false)->comment('Was locked at distribution time');
            $table->enum('status', ['available', 'onhold', 'released'])->default('available');
            $table->enum('transaction_type', ['earned', 'unlocked', 'adjusted'])->default('earned');
            $table->dateTime('released_at')->nullable()->comment('When locked CP was released');
            $table->dateTime('locked_at')->nullable()->comment('When locked CP was locked');
            $table->timestamps();
            
            $table->index('purchase_id');
            $table->index('source_member_id');
            $table->index('receiver_member_id');
            $table->index('status');
            $table->index('level');
            
            $table->foreign('source_member_id')->references('id')->on('members');
            $table->foreign('receiver_member_id')->references('id')->on('members');
            // $table->foreign('purchase_id')->references('id')->on('purchases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_transactions');
    }
};
