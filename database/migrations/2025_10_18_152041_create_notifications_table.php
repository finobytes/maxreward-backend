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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->enum('type', [
                'referral_invite',
                'point_approval',
                'redemption',
                'milestone',
                'voucher_purchase',
                'cp_unlock',
                'purchase_approved',
                'purchase_rejected',
                'level_unlocked',
                'system_alert'
            ])->comment('Notification type');
            $table->string('title', 200)->nullable()->comment('Notification title');
            $table->text('message')->comment('Notification message');
            $table->json('data')->nullable()->comment('Additional data (purchase_id, amount, etc)');
            $table->enum('status', ['unread', 'read'])->default('unread');
            $table->boolean('is_count_read')->default(false)->comment('All new notifications count read');
            $table->boolean('is_read')->default(false)->comment('Individual notification read');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            
            $table->index('member_id');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
