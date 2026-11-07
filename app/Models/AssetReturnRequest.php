<?php

namespace App\Models;

use App\Enums\AssetReturnRequestState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 资产归还申请。
 *
 * 用户在「View Assigned Items」对自己的资产点击归还时创建（pending），
 * 仓库管理员签入该资产后关闭（fulfilled），申请人可撤销（canceled）。
 *
 * @property AssetReturnRequestState $state
 *
 * @mixin IdeHelperAssetReturnRequest
 */
class AssetReturnRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'asset_return_requests';

    protected $fillable = [
        'asset_id',
        'user_id',
        'state',
        'note',
        'checkin_location_id',
        'handled_by',
        'fulfilled_at',
        'canceled_at',
    ];

    protected $casts = [
        'state' => AssetReturnRequestState::class,
        'fulfilled_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected $attributes = [
        'state' => 'pending',
    ];

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id', 'id')->withTrashed();
    }

    /**
     * 申请归还的用户（资产当前持有人）。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 执行签入的管理员。
     *
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'id');
    }

    /**
     * 仓库签入时确认的资产位置 a。
     *
     * @return BelongsTo<Location, $this>
     */
    public function checkinLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'checkin_location_id', 'id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', AssetReturnRequestState::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->state === AssetReturnRequestState::Pending;
    }

    /**
     * 申请人撤销归还申请。仅 pending 可撤销。
     */
    public function cancel(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->state = AssetReturnRequestState::Canceled;
        $this->canceled_at = now();

        return $this->save();
    }

    /**
     * 签入完成后关闭该资产的所有 pending 归还申请。
     * 无论签入是否经由归还请求队列发起（普通 check-in 页面也会
     * 走到这里），挂起的归还申请都应视为已被归还动作兑现。
     *
     * @return \Illuminate\Support\Collection<int, self> 本次被关闭的申请
     */
    public static function fulfillForAsset(Asset $asset, User $handler): \Illuminate\Support\Collection
    {
        $requests = static::where('asset_id', $asset->id)->pending()->get();

        foreach ($requests as $request) {
            $request->state = AssetReturnRequestState::Fulfilled;
            $request->handled_by = $handler->id;
            $request->checkin_location_id = $asset->location_id;
            $request->fulfilled_at = now();
            $request->save();
        }

        return $requests;
    }
}
