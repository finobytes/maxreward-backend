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
        Schema::create('attribute_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->string('name', 100)->comment('S, M, L, Red, Blue, etc');
            $table->string('slug', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('attribute_id');
            $table->index('is_active');
            $table->unique(['attribute_id', 'slug'], 'unique_attribute_item');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_items');
    }
};
