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
        Schema::create('whats_app_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id'); // Receiver
            $table->unsignedBigInteger('sent_by_member_id')->nullable(); // Sender (referrer)
            $table->string('phone_number'); // With country code
            $table->string('message_type', 100)->default('referral_invite'); // referral_invite, welcome, etc.
            $table->text('message_content');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('sent_by_member_id')->references('id')->on('members');
            
            $table->index(['member_id', 'message_type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_message_logs');
    }
};
