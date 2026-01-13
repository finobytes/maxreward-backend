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
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id')->nullable()->after('guard_name');
            $table->index('merchant_id');

            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['name', 'guard_name', 'merchant_id']);

            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropUnique(['name', 'guard_name', 'merchant_id']);
            $table->dropIndex(['merchant_id']);
            $table->dropColumn('merchant_id');

            $table->unique(['name', 'guard_name']);
        });
    }
};
