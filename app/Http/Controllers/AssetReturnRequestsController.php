<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * 归还请求队列页面（超级用户 + 仓库管理员）。
 *
 * 列表数据由 Api\AssetReturnRequestsController 提供；
 * 签入动作复用标准 check-in 页面，不在这里单独实现。
 */
class AssetReturnRequestsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewReturnRequests');

        return view('return-requests/index');
    }
}
