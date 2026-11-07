<?php

namespace App\Http\Controllers\Account;

use App\Actions\ReturnRequests\CreateReturnRequestAction;
use App\Exceptions\DuplicateReturnRequest;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 用户侧的归还申请：在「View Assigned Items」提交 / 撤销。
 */
class AssetReturnRequestController extends Controller
{
    /**
     * 提交归还申请。
     */
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        try {
            CreateReturnRequestAction::run($asset, $request->user(), $request->input('note'));
        } catch (DuplicateReturnRequest $e) {
            return redirect()->route('view-assets')->with('error', '该资产已有待处理的归还申请');
        } catch (ValidationException $e) {
            return redirect()->route('view-assets')->with('error', '该资产不在您的名下，无法申请归还');
        }

        return redirect()->route('view-assets')->withFragment('assets')
            ->with('success', '归还申请已提交，等待仓库管理员签入');
    }

    /**
     * 撤销归还申请。仅申请人本人、且申请仍处于 pending 时可撤销。
     */
    public function destroy(AssetReturnRequest $returnRequest): RedirectResponse
    {
        if ((int) $returnRequest->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! $returnRequest->cancel()) {
            return redirect()->route('view-assets')->withFragment('assets')
                ->with('error', '该归还申请已被处理，无法撤销');
        }

        return redirect()->route('view-assets')->withFragment('assets')
            ->with('success', '归还申请已撤销');
    }
}
