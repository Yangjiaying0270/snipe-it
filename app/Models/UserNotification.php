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

    /** 资产到期/临近到期提醒（定时任务发出）。 */
    public const TYPE_ASSET_DUE = 'asset_due';

    /** 延期申请已提交，通知审批人。 */
    public const TYPE_EXTEND_SUBMITTED = 'extend_submitted';

    /** 延期申请审批通过，通知申请人。 */
    public const TYPE_EXTEND_APPROVED = 'extend_approved';

    /** 延期申请审批拒绝，通知申请人。 */
    public const TYPE_EXTEND_REJECTED = 'extend_rejected';

    /** 耗材已出库，通知抄送人。 */
    public const TYPE_CONSUMABLE_FULFILLED = 'consumable_fulfilled';

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
            self::TYPE_REQUEST_SUBMITTED,
            self::TYPE_EXTEND_SUBMITTED => '待审批',
            self::TYPE_REQUEST_APPROVED,
            self::TYPE_REQUEST_APPROVED_NOTICE => '审批通过',
            self::TYPE_REQUEST_REJECTED,
            self::TYPE_EXTEND_REJECTED => '申请被拒绝',
            self::TYPE_REQUEST_FULFILLED => '成功签出',
            self::TYPE_REQUEST_INVALIDATED => '申请已失效',
            self::TYPE_RETURN_REQUESTED => '归还申请',
            self::TYPE_ASSET_RETURNED => '资产归还',
            self::TYPE_EXTEND_APPROVED => '延期已批准',
            self::TYPE_ASSET_DUE => '资产到期提醒',
            self::TYPE_CONSUMABLE_FULFILLED => '耗材已出库',
            default => '消息提醒',
        };
    }

    /**
     * 点击该通知（或该分组）时应跳转的路由。
     * 弹窗/铃铛/红点角标三处都复用这个映射，避免每个视图
     * 自己硬编码跳转目标。
     */
    public function targetUrl(): string
    {
        return match ($this->type) {
            // 给审批人/仓库看的 → 待处理申请队列
            self::TYPE_REQUEST_SUBMITTED,
            self::TYPE_REQUEST_MODIFIED,
            self::TYPE_EXTEND_SUBMITTED,
            self::TYPE_RETURN_REQUESTED,
            self::TYPE_REQUEST_APPROVED => route('requests.index'),

            // 申请人视角 → 我的申请
            self::TYPE_REQUEST_REJECTED,
            self::TYPE_REQUEST_INVALIDATED,
            self::TYPE_REQUEST_FULFILLED,
            self::TYPE_REQUEST_APPROVED_NOTICE => route('account.requested'),

            // 资产生命周期 → 我的资产（查看名下）
            self::TYPE_ASSET_DUE,
            self::TYPE_EXTEND_APPROVED,
            self::TYPE_EXTEND_REJECTED,
            self::TYPE_ASSET_RETURNED => route('account.assets'),

            // 抄送人视角 → 没有强目标，回到控制台
            self::TYPE_CONSUMABLE_FULFILLED => route('home'),

            default => route('home'),
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

    // =====================================================================
    // 延期申请通知
    // =====================================================================

    /**
     * 用户提交延期申请 → 通知审批人（申请人选的 approver_id，兜底仓库管理员）。
     */
    public static function notifyExtendSubmitted(CheckoutRequest $request): void
    {
        $recipientId = (int) ($request->approver_id ?: config('checkout_request.warehouse_admin_id'));
        if (! $recipientId) {
            return;
        }

        static::create([
            'user_id' => $recipientId,
            'type' => self::TYPE_EXTEND_SUBMITTED,
            'title' => '有新的延期申请待审批',
            'body' => self::describeExtendRequest($request),
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 延期审批通过 → 通知申请人（资产归还日期已更新）。
     */
    public static function notifyExtendApproved(CheckoutRequest $request): void
    {
        $newReturn = $request->expected_return_date?->format('Y-m-d') ?? '未设置';

        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_EXTEND_APPROVED,
            'title' => '您的延期申请已批准',
            'body' => self::describeExtendRequest($request).'，已批准，新的归还日期为 '.$newReturn.'。',
            'checkout_request_id' => $request->id,
        ]);
    }

    /**
     * 延期审批拒绝 → 通知申请人，资产继续按原归还日期执行。
     */
    public static function notifyExtendRejected(CheckoutRequest $request): void
    {
        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_EXTEND_REJECTED,
            'title' => '您的延期申请未批准',
            'body' => self::describeExtendRequest($request).'，未批准，请按时归还。',
            'checkout_request_id' => $request->id,
        ]);
    }

    // =====================================================================
    // 耗材申请通知
    // =====================================================================

    /**
     * 耗材已出库 → 通知抄送人（cc_user_ids 数组里每个用户各发一条）。
     */
    public static function notifyConsumableFulfilled(CheckoutRequest $request): void
    {
        $ccIds = $request->cc_user_ids ?? [];
        if (! is_array($ccIds) || $ccIds === []) {
            return;
        }

        $consumableName = $request->itemRequested()?->display_name ?? '耗材';
        $qty = (int) $request->fulfilled_quantity ?: (int) $request->quantity;
        $requesterName = $request->user?->display_name ?? '申请人';

        $body = $requesterName.' 领用了 '.$qty.' 个「'.$consumableName.'」，用途：'.($request->purpose ?? '未填写').'。';

        $now = now();
        $rows = [];
        foreach ($ccIds as $ccId) {
            $rows[] = [
                'user_id' => (int) $ccId,
                'type' => self::TYPE_CONSUMABLE_FULFILLED,
                'title' => '耗材领用通知',
                'body' => $body,
                'checkout_request_id' => $request->id,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            static::insert($rows);
        }
    }

    /**
     * 仓库管理员删除/撤销待出库的耗材申请 → 通知申请人。
     */
    public static function notifyConsumableCanceled(CheckoutRequest $request): void
    {
        $consumableName = $request->itemRequested()?->display_name ?? '耗材';
        $qty = (int) $request->quantity;

        static::create([
            'user_id' => (int) $request->user_id,
            'type' => self::TYPE_REQUEST_INVALIDATED,
            'title' => '耗材申请已撤销',
            'body' => '您申请领用的 '.$qty.' 个「'.$consumableName.'」已被仓库撤销。',
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

    protected static function describeExtendRequest(CheckoutRequest $request): string
    {
        $assetName = $request->extendedAsset()?->display_name ?? '该资产';
        $newDate = $request->expected_return_date?->format('Y-m-d') ?? '未设置';
        $requesterName = $request->user?->display_name ?? '申请人';

        return $requesterName.' 申请将「'.$assetName.'」的归还日期延期至 '.$newDate;
    }

    protected static function describeReturnRequest(AssetReturnRequest $request): string
    {
        $assetName = $request->asset?->display_name ?? '该资产';
        $userName = $request->user?->display_name ?? '用户';

        return $userName.' 归还的「'.$assetName.'」';
    }
}
