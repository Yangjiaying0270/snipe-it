<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 站内通知关联归还申请：归还申请类通知通过 asset_return_request_id
 * 回溯到 asset_return_requests，与既有 checkout_request_id 互补。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->integer('asset_return_request_id')->nullable()->index()->after('checkout_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['asset_return_request_id']);
            $table->dropColumn('asset_return_request_id');
        });
    }
};
