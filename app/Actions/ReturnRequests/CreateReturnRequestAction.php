<?php

namespace App\Actions\ReturnRequests;

use App\Exceptions\DuplicateReturnRequest;
use App\Models\Asset;
use App\Models\AssetReturnRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 创建资产归还申请。
 *
 * 只有资产当前持有人可以申请归还；同一资产同时只允许一条 pending
 * 申请。成功后站内通知仓库管理员。
 */
class CreateReturnRequestAction
{
    /**
     * @throws ValidationException 资产不在该用户名下
     * @throws DuplicateReturnRequest 已有待处理的归还申请
     */
    public static function run(Asset $asset, User $user, ?string $note = null): AssetReturnRequest
    {
        return DB::transaction(function () use ($asset, $user, $note) {
            // 锁住行，防止并发的归还/签出与重复申请产生竞态。
            $lockedAsset = Asset::where('id', $asset->id)->lockForUpdate()->first();

            if (! $lockedAsset
                || $lockedAsset->assigned_type !== User::class
                || (int) $lockedAsset->assigned_to !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'asset' => '该资产不在您的名下，无法申请归还',
                ]);
            }

            $exists = AssetReturnRequest::where('asset_id', $lockedAsset->id)
                ->pending()
                ->exists();

            if ($exists) {
                throw new DuplicateReturnRequest('该资产已有待处理的归还申请');
            }

            $returnRequest = AssetReturnRequest::create([
                'asset_id' => $lockedAsset->id,
                'user_id' => $user->id,
                'note' => $note,
            ]);

            UserNotification::notifyReturnRequested($returnRequest);

            return $returnRequest;
        });
    }
}
