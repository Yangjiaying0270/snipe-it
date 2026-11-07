<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\AssetReturnRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class AssetReturnRequestsTransformer
{
    public function transformAssetReturnRequests(Collection $requests, $total): array
    {
        $array = [];
        foreach ($requests as $request) {
            $array[] = self::transformAssetReturnRequest($request);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformAssetReturnRequest(AssetReturnRequest $request): array
    {
        $asset = $request->asset;

        return [
            'id' => (int) $request->id,
            'asset' => $asset ? [
                'id' => (int) $asset->id,
                'name' => e($asset->display_name),
                'asset_tag' => e($asset->asset_tag),
                'image' => $asset->getImageUrl(),
                'location' => $asset->location ? ['id' => (int) $asset->location->id, 'name' => e($asset->location->name)] : null,
                'rtd_location' => $asset->defaultLoc ? ['id' => (int) $asset->defaultLoc->id, 'name' => e($asset->defaultLoc->name)] : null,
            ] : null,
            'user' => $request->user ? [
                'id' => (int) $request->user->id,
                'name' => e($request->user->display_name),
            ] : null,
            'note' => e($request->note),
            'state' => $request->state->label(),
            'state_color' => $request->state->color(),
            'requested_at' => Helper::getFormattedDateObject($request->created_at, 'datetime'),
            'created_at' => Helper::getFormattedDateObject($request->created_at, 'datetime'),
            'available_actions' => [
                // 签入按钮跳转标准 check-in 页面，要求操作人本身有
                // checkin 权限；归还申请存在不等于自动获得签入权。
                'checkin' => $asset
                    && $request->isPending()
                    && Gate::allows('checkin', $asset),
            ],
        ];
    }
}
