<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\AssetReturnRequestsTransformer;
use App\Models\AssetReturnRequest;
use Illuminate\Http\Request;

/**
 * 归还请求队列 API：供 /return-requests 页面的 bootstrap-table 消费。
 * 只暴露 pending 申请；已签入 / 已撤销的历史不在队列里展示。
 */
class AssetReturnRequestsController extends Controller
{
    public function index(Request $request): array
    {
        $this->authorize('viewReturnRequests');

        $query = AssetReturnRequest::with([
            'asset.model',
            'asset.location',
            'asset.defaultLoc',
            'asset.assignedTo',
            'user',
        ])->pending();

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search): void {
                $q->where('note', 'LIKE', $search)
                    ->orWhereHas('asset', function ($a) use ($search): void {
                        $a->where('name', 'LIKE', $search)
                            ->orWhere('asset_tag', 'LIKE', $search)
                            ->orWhere('serial', 'LIKE', $search);
                    })
                    ->orWhereHas('user', function ($u) use ($search): void {
                        $u->where('first_name', 'LIKE', $search)
                            ->orWhere('last_name', 'LIKE', $search)
                            ->orWhere('username', 'LIKE', $search);
                    });
            });
        }

        $allowedSorts = ['id', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'created_at';
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $total = $query->count();

        $offset = ($request->filled('offset') && $request->input('offset') >= 0) ? (int) $request->input('offset') : 0;
        $limit = $request->filled('limit') ? (int) $request->input('limit') : 50;

        $requests = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        return (new AssetReturnRequestsTransformer)->transformAssetReturnRequests($requests, $total);
    }
}
