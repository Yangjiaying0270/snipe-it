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
            // approver_id：审批人 ID，关联 users 表。
            // 使用 unsignedInteger 以匹配 users.id 的 int unsigned 类型。
            // nullable() 允许为空（申请时尚未分配审批人的场景）。
            // 放在 checkout_location_id 之后，与申请相关的元数据列聚在一起。
            if (! Schema::hasColumn('checkout_requests', 'approver_id')) {
                $table->unsignedInteger('approver_id')
                    ->nullable()
                    ->after('checkout_location_id')
                    ->comment('审批人ID');

                $table->foreign('approver_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            // checkout_reason：申请理由，使用 text 类型以防理由过长，允许为空。
            if (! Schema::hasColumn('checkout_requests', 'checkout_reason')) {
                $table->text('checkout_reason')
                    ->nullable()
                    ->after('approver_id')
                    ->comment('申请理由');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            // 回滚时先删外键再删列，顺序不能反。
            if (Schema::hasColumn('checkout_requests', 'approver_id')) {
                $table->dropForeign(['approver_id']);
                $table->dropColumn('approver_id');
            }
            if (Schema::hasColumn('checkout_requests', 'checkout_reason')) {
                $table->dropColumn('checkout_reason');
            }
        });
    }
};
