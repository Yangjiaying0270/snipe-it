<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * request_type 标识这条 CheckoutRequest 是三类流程里的哪一种：
     *   normal        普通资产申请（默认），需要审批
     *   extend        延期申请，对某个已签出资产请求延长归还日
     *   consumable    耗材申请，跳过审批，直接到仓库管理员出库
     *
     * approval_status 新增 auto_approved：用于 consumable（跳过审批直接到出库队列）。
     * 普通申请和延期申请依然走 pending→approved/rejected。
     *
     * expected_return_date   用户申请时填写的预计归还日期（原 end_date 的语义升级）
     * extended_asset_id      延期申请所指向的已签出资产（assets.id）
     * original_checkout_request_id  延期申请可选关联的原普通申请
     * purpose                耗材用途说明（consumable 类型必填）
     * cc_user_ids            抄送人 user_id 数组 JSON（consumable 类型）
     */
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('request_type', 20)
                ->default('normal')
                ->after('state')
                ->index();

            $table->date('expected_return_date')
                ->nullable()
                ->after('end_date');

            $table->unsignedInteger('extended_asset_id')
                ->nullable()
                ->after('expected_return_date');

            $table->unsignedInteger('original_checkout_request_id')
                ->nullable()
                ->after('extended_asset_id');

            $table->text('purpose')
                ->nullable()
                ->after('original_checkout_request_id');

            $table->json('cc_user_ids')
                ->nullable()
                ->after('purpose');
        });

        // 历史记录全部视为 normal
        DB::table('checkout_requests')
            ->where('request_type', '')
            ->orWhereNull('request_type')
            ->update(['request_type' => 'normal']);

        // 顺便把 end_date 里实际是"预计归还日"的历史值同步到新字段，
        // 让 Asset::returnStatus() 立刻有数据可用。
        DB::table('checkout_requests')
            ->whereNotNull('end_date')
            ->whereNull('expected_return_date')
            ->update(['expected_return_date' => DB::raw('end_date')]);
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn([
                'request_type',
                'expected_return_date',
                'extended_asset_id',
                'original_checkout_request_id',
                'purpose',
                'cc_user_ids',
            ]);
        });
    }
};
