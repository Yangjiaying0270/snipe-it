<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 站内通知表：用于申请-审批-出库工作流的登录提醒。
 *
 * 关系列一律用普通 integer（本项目不使用外键约束），
 * checkout_request_id 关联 checkout_requests.id，
 * read_at 为 NULL 表示未读。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index();
            $table->string('type', 40)->index();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->integer('checkout_request_id')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
