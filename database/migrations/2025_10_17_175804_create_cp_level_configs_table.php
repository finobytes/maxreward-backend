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
        Schema::create('cp_level_configs', function (Blueprint $table) {
            $table->id();
            $table->integer('level_from');
            $table->integer('level_to');
            $table->decimal('cp_percentage_per_level', 5, 2)->comment('CP % for EACH level in range');
            $table->decimal('total_percentage_for_range', 5, 2)->comment('Total % for entire range');
            $table->timestamps();
            
            $table->unique(['level_from', 'level_to'], 'unique_level_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_level_configs');
    }
};
