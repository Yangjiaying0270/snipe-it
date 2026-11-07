<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('checkout_requests', 'approval_status')) {
            Schema::table('checkout_requests', function (Blueprint $table) {
                $table->string('approval_status', 20)
                    ->default('pending')
                    ->after('state')
                    ->index();
            });
        }

        // 已签出的历史记录直接视为"已满足"。
        DB::table('checkout_requests')
            ->where('state', 'fulfilled')
            ->update(['approval_status' => 'fulfilled']);

        // 上一版"批准"实现会把 approver_id 改写为仓库管理员，
        // 这部分记录已经审批通过，需要标记为 approved。
        DB::table('checkout_requests')
            ->where('state', 'pending')
            ->where('approver_id', (int) config('checkout_request.warehouse_admin_id'))
            ->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('checkout_requests', 'approval_status')) {
            Schema::table('checkout_requests', function (Blueprint $table) {
                $table->dropColumn('approval_status');
            });
        }
    }
};
