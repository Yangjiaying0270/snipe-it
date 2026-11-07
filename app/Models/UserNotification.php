<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 站内通知（登录提醒）。
 *
 * 五类工作流事件各写一条：提交→审批人、批准→仓库、退回→申请人、
 * 出库→中签者、失效→落选者。read_at 非 NULL 即已读。
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property int|null $checkout_request_id
 * @property Carbon|null $read_at
 *
 * @mixin IdeHelperUserNotification
 */
class UserNotification extends Model
{
    public const TYPE_REQUEST_SUBMITTED = 'request_submitted';

    public const TYPE_REQUEST_MODIFIED = 'request_modified';

    public const TYPE_REQUEST_APPROVED = 'request_approved';

    public const TYPE_REQUEST_REJECTED = 'request_rejected';

    public const TYPE_REQUEST_FULFILLED = 'request_fulfilled';

    public const TYPE_REQUEST_INVALIDATED = 'request_invalidated';

    public const TYPE_REQUEST_APPROVED_NOTICE = 'request_approved_notice';

    public const TYPE_RETURN_REQUESTED = 'return_requested';

    public const TYPE_ASSET_RETURNED = 'asset_returned';

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'checkout_request_id',
        'asset_return_request_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * 同一请求在一次页面渲染里可能被角标和弹窗各查一次，
     * 用请求级静态缓存避免重复查询。
     *
     * @var array<int, Collection<int, self>>
     */
    protected static array $unreadCache = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function checkoutRequest(): BelongsTo
    {
        return $this->belongsTo(CheckoutRequest::class, 'checkout_request_id', 'id');
    }

    /**
     * 按通知类型返回用于弹窗/铃铛分组展示的中文标签。
     * 同一语义的类型（如给仓库的审批通过和给申请人的审批通过）合并为一组。
     */
    public function groupLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REQUEST_SUBMITTED => '待审批',
            self::TYPE_REQUEST_APPROVED,
            self::TYPE_REQUEST_APPROVED_NOTICE => '审批通过',
            self::TYPE_REQUEST_REJECTED => '申请被拒绝',
            self::TYPE_REQUEST_FULFILLED => '成功签出',
            self::TYPE_REQUEST_INVALIDATED => '申请已失效',
            self::TYPE_RETURN_REQUESTED => '归还申请',
            self::TYPE_ASSET_RETURNED => '资产归还',
            default => '消息提醒',
        };
    }

    /**
     * @return Collection<int, self>
     */
    public static function unreadFor(?int $userId): Collection
    {
        if (! $userId) {
            return new Collection;
        }

        return static::$unreadCache[$userId] ??= static::where('user_id', $userId)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * 把某用户当前所有未读置为已读，返回受影响条数。
     */
    public static function markAllReadFor(int $userId): int
    {
        static::$unreadCache = [];

        return static::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * 提交/修改申请 → 通知审批人（未指定审批人时兜底通知仓库）。
     */
    public static function notifyApprovalRequested(CheckoutRequest $request, bool $isModification = false): void
    {
        $recipientId = (int) ($request->approver_id ?: config('checkout_request.warehouse_admin_id'));
        if (! $recipientId) {
            return;
        }

        static::create([
            'user_id' => $recipientId,
            'type' => $isModification ? self::TYPE_REQUEST_MODIFIED : self::TYPE_REQUEST_SUBMITTED,
            'title' => $isModification ? '有申请被修改，需要重新审批' : '有新的资产申请待审批',
            'body' => self::describeRequest($request),
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 审批通过 → 通知仓库管理员准备出库。
     */
    public static function notifyApproved(CheckoutRequest $request): void
    {
        $recipientId = (int) config('checkout_request.warehouse_admin_id');
        if (! $recipientId) {
            return;
        }

        static::create([
            'user_id' => $recipientId,
            'type' => self::TYPE_REQUEST_APPROVED,
            'title' => '有已批准的申请待出库',
            'body' => self::describeRequest($request),
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 审批退回 → 通知申请人修改或撤销。
     */
    public static function notifyRejected(CheckoutRequest $request): void
    {
        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_REQUEST_REJECTED,
            'title' => '您的申请被退回',
            'body' => self::describeRequest($request).'，请在「我的申请」中修改或撤销。',
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 出库完成 → 通知中签者申请已兑现。
     */
    public static function notifyFulfilled(CheckoutRequest $request): void
    {
        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_REQUEST_FULFILLED,
            'title' => '申请已成功出库',
            'body' => self::describeRequest($request).'，已成功发放给您。',
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 同一资产被他人先领用 → 通知落选者申请已失效。
     */
    public static function notifyInvalidated(CheckoutRequest $request): void
    {
        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_REQUEST_INVALIDATED,
            'title' => '申请的资产已被他人领用',
            'body' => self::describeRequest($request).'，该资产已被他人领用，您的申请已失效。',
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 审批通过 → 同时通知申请人申请已通过，等待仓库发放。
     */
    public static function notifyRequesterApproved(CheckoutRequest $request): void
    {
        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_REQUEST_APPROVED_NOTICE,
            'title' => '您的申请已审批通过',
            'body' => self::describeRequest($request).'，已审批通过，等待仓库发放。',
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 用户提交归还申请 → 通知仓库管理员（2108）准备签入。
     */
    public static function notifyReturnRequested(AssetReturnRequest $request): void
    {
        $recipientId = (int) config('checkout_request.warehouse_admin_id');
        if (! $recipientId) {
            return;
        }

        static::create([
            'user_id' => $recipientId,
            'type' => self::TYPE_RETURN_REQUESTED,
            'title' => '有新的资产归还申请',
            'body' => self::describeReturnRequest($request),
            'asset_return_request_id' => $request->id,
        ]);
    }

    /**
     * 归还完成 → 广播给所有用户「xxx 已归还 xxx」。
     *
     * 收件人是全体未删除用户，量可能很大，所以按块批量 insert，
     * 避免逐条 create 带来的 N 次往返。
     */
    public static function broadcastAssetReturned(AssetReturnRequest $request): void
    {
        $body = self::describeReturnRequest($request);
        $now = now();

        User::select('id')->chunkById(500, function ($users) use ($request, $body, $now): void {
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [
                    'user_id' => $user->id,
                    'type' => self::TYPE_ASSET_RETURNED,
                    'title' => '资产归还通知',
                    'body' => $body.'，已完成归还。',
                    'asset_return_request_id' => $request->id,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            static::insert($rows);
        });

        // 清掉请求级未读缓存，确保本次渲染之后读到的是最新状态。
        static::$unreadCache = [];
    }

    protected static function describeRequest(CheckoutRequest $request): string
    {
        $itemName = $request->itemRequested()?->display_name ?? '该物品';
        $requesterName = $request->user?->display_name ?? '申请人';

        return $requesterName.' 申请的「'.$itemName.'」';
    }

    protected static function describeReturnRequest(AssetReturnRequest $request): string
    {
        $assetName = $request->asset?->display_name ?? '该资产';
        $userName = $request->user?->display_name ?? '用户';

        return $userName.' 归还的「'.$assetName.'」';
    }
}
