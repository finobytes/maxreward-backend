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
        Schema::create('shipping_zone_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->string('postcode_prefix', 10); // "50", "58", "40"
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
            
            $table->foreign('zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
            $table->index(['postcode_prefix', 'zone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_areas');
    }
};
