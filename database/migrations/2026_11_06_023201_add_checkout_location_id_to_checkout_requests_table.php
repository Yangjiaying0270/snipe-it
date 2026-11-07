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
        Schema::table('checkout_requests', function (Blueprint $table) {
            // 地址主键为unsignedInteger
            $table->unsignedInteger('checkout_location_id')
                ->nullable()
                ->after('notes');

            // 加locations表的外键
            $table->foreign('checkout_location_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            //
            $table->dropForeign(['checkout_location_id']);
            $table->dropColumn('checkout_location_id');
        });
    }
};
