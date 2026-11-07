<?php

namespace Tests\Feature\ReturnRequests\Ui;

use App\Enums\AssetReturnRequestState;
use App\Models\Asset;
use App\Models\AssetReturnRequest;
use App\Models\Location;
use App\Models\User;
use App\Models\UserNotification;
use Tests\TestCase;

class AssetReturnRequestTest extends TestCase
{
    /**
     * 把请求伪装成仓库管理员（2108）。测试环境里 config 的默认值
     * 可能不是真实库里的 2108，这里显式对齐到用户 id。
     */
    private function actingAsWarehouseAdmin(): User
    {
        $admin = User::factory()->checkinAssets()->create();
        config(['checkout_request.warehouse_admin_id' => $admin->id]);

        return $admin;
    }

    public function test_user_can_request_return_for_own_asset(): void
    {
        $admin = $this->actingAsWarehouseAdmin();
        $user = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($user)->create();

        $this->actingAs($user)
            ->post(route('account.return-request.store', $asset), ['note' => '不用了'])
            ->assertRedirect(route('view-assets').'#assets')
            ->assertSessionHas('success');

        $request = AssetReturnRequest::where('asset_id', $asset->id)->sole();
        $this->assertTrue($request->isPending());
        $this->assertSame($user->id, $request->user_id);

        // 仓库管理员收到「有新的资产归还申请」
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_RETURN_REQUESTED,
            'asset_return_request_id' => $request->id,
        ]);
    }

    public function test_user_cannot_request_return_for_asset_assigned_to_someone_else(): void
    {
        $this->actingAsWarehouseAdmin();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($owner)->create();

        $this->actingAs($other)
            ->post(route('account.return-request.store', $asset))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('asset_return_requests', ['asset_id' => $asset->id]);
    }

    public function test_duplicate_pending_return_request_is_rejected(): void
    {
        $this->actingAsWarehouseAdmin();
        $user = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($user)->create();

        $this->actingAs($user)->post(route('account.return-request.store', $asset));
        $this->actingAs($user)
            ->post(route('account.return-request.store', $asset))
            ->assertSessionHas('error');

        $this->assertSame(1, AssetReturnRequest::where('asset_id', $asset->id)->count());
    }

    public function test_user_can_cancel_own_pending_return_request(): void
    {
        $this->actingAsWarehouseAdmin();
        $user = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($user)->create();
        $request = AssetReturnRequest::create(['asset_id' => $asset->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('account.return-request.cancel', $request))
            ->assertSessionHas('success');

        $this->assertSame(AssetReturnRequestState::Canceled, $request->fresh()->state);
    }

    public function test_user_cannot_cancel_someone_elses_return_request(): void
    {
        $this->actingAsWarehouseAdmin();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($owner)->create();
        $request = AssetReturnRequest::create(['asset_id' => $asset->id, 'user_id' => $owner->id]);

        $this->actingAs($other)
            ->post(route('account.return-request.cancel', $request))
            ->assertForbidden();

        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_return_requests_queue_is_limited_to_superuser_and_warehouse_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('return-requests.index'))
            ->assertForbidden();

        $admin = $this->actingAsWarehouseAdmin();
        $this->actingAs($admin)->get(route('return-requests.index'))->assertOk();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('return-requests.index'))
            ->assertOk();
    }

    public function test_checkin_closes_return_request_sets_location_and_broadcasts(): void
    {
        $admin = $this->actingAsWarehouseAdmin();
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $asset = Asset::factory()->assignedToUser($user)->create([
            'rtd_location_id' => Location::factory()->create()->id,
        ]);
        $request = AssetReturnRequest::create(['asset_id' => $asset->id, 'user_id' => $user->id]);

        $this->actingAs($admin)
            ->post(route('hardware.checkin.store', $asset), [
                'status_id' => $asset->status_id,
                'location_id' => $location->id,
            ])
            ->assertSessionHas('success');

        $asset->refresh();
        $this->assertNull($asset->assigned_to);
        $this->assertSame($location->id, $asset->location_id);

        $request->refresh();
        $this->assertSame(AssetReturnRequestState::Fulfilled, $request->state);
        $this->assertSame($admin->id, $request->handled_by);
        $this->assertSame($location->id, $request->checkin_location_id);
        $this->assertNotNull($request->fulfilled_at);

        // 广播给所有未删除用户（含申请人与管理员）
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_ASSET_RETURNED,
            'asset_return_request_id' => $request->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ASSET_RETURNED,
            'asset_return_request_id' => $request->id,
        ]);
    }

    public function test_user_can_view_their_notifications_page(): void
    {
        $user = User::factory()->create();
        UserNotification::create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_ASSET_RETURNED,
            'title' => '资产归还通知',
            'body' => '测试',
        ]);

        $this->actingAs($user)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('资产归还通知');
    }

    public function test_view_assigned_items_page_renders_return_column_and_modal(): void
    {
        $user = User::factory()->create();
        Asset::factory()->assignedToUser($user)->create();

        $this->actingAs($user)
            ->get(route('view-assets'))
            ->assertOk()
            ->assertSee('returnRequestActionsFormatter')
            ->assertSee('returnRequestModal');
    }

    public function test_account_assets_api_exposes_return_request_state_per_row(): void
    {
        $this->actingAsWarehouseAdmin();
        $user = User::factory()->create();
        $plainAsset = Asset::factory()->assignedToUser($user)->create();
        $pendingAsset = Asset::factory()->assignedToUser($user)->create();
        $returnRequest = AssetReturnRequest::create([
            'asset_id' => $pendingAsset->id,
            'user_id' => $user->id,
        ]);

        $rows = collect($this->actingAsForApi($user)
            ->getJson(route('api.account.assets'))
            ->assertOk()
            ->json('rows'))->keyBy('id');

        $this->assertTrue($rows[$plainAsset->id]['return_request']['can_create']);
        $this->assertNull($rows[$plainAsset->id]['return_request']['request_id']);

        $this->assertFalse($rows[$pendingAsset->id]['return_request']['can_create']);
        $this->assertSame($returnRequest->id, $rows[$pendingAsset->id]['return_request']['request_id']);
    }
}
