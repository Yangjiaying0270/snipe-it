<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 资产归还申请表：用户在「View Assigned Items」点击归还后生成，
 * 仓库管理员签入后关闭。
 *
 * 关系列一律用普通 integer（本项目不使用外键约束）。
 * state 为字符串状态机：pending / fulfilled / canceled。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_return_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('asset_id')->index();
            $table->integer('user_id')->index();
            $table->string('state', 20)->default('pending')->index();
            $table->text('note')->nullable();
            $table->integer('checkin_location_id')->nullable();
            $table->integer('handled_by')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_return_requests');
    }
};
