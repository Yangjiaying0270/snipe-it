<?php

namespace App\Enums;

/**
 * Lifecycle for an AssetReturnRequest.
 *
 *   pending    用户在「View Assigned Items」点击归还后创建，等待仓库签入。
 *   fulfilled  仓库管理员完成签入（无论是否经由归还请求入口），请求关闭。
 *   canceled   申请人主动撤销归还申请。
 *
 * state 列是 varchar，后续新增状态无需改表结构。
 */
enum AssetReturnRequestState: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待签入',
            self::Fulfilled => '已签入',
            self::Canceled => '已撤销',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Fulfilled => 'success',
            self::Canceled => 'default',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::Canceled], true);
    }
}
