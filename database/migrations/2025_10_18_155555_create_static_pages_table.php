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
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('terms_title', 100)->comment('terms_and_conditions/data_privacy_policy/about_us');
            $table->text('terms_description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Admin who last updated');
            $table->timestamps();
            
            $table->index('terms_title');
            $table->index('updated_by');
            
            $table->foreign('updated_by')->references('id')->on('admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
