{{-- Action URL is set per-click by snipeit.js from the trigger button's
     data-request-url. Same shape as the adjust-quantity modal so the
     moving parts stay familiar. --}}
<x-modals
    id="requestItemModal"
    stacked
    :submit_label="trans('button.request')"
    form_attrs='id="requestItemForm" accept-charset="utf-8"'>
    <x-slot:header>
        <h4 class="modal-title" id="requestItemModalLabel">
            {{ trans('general.request_item') }}
            <small class="request-item-name text-muted"></small>
        </h4>
    </x-slot:header>

    {{-- Snapshot of the tab the user was on when they
         opened the modal. Snipeit.js reads the enclosing
         .tab-pane on click and writes its id here so the
         controller can restore the same tab on the
         post-submit redirect. --}}
    <input type="hidden" name="active_tab" id="requestItemActiveTab" value="">

    {{-- 修改模式下携带被修改申请的 id。新建时为空，控制器据此走
         CreateCheckoutRequestAction；非空且属于当前用户时走更新分支，
         重置 approval_status=pending、fulfilled_quantity=0。 --}}
    <input type="hidden" name="request_id" id="requestItemRequestId" value="">

    {{-- Qty row is hidden by JS when the trigger sets
         data-item-type="asset" or "license". Assets are 1:1
         (you request THE asset, not N of it). Licenses are
         one-seat-per-request by convention. The input stays
         in the DOM with value=1 so the POST shape stays
         uniform across every requestable type. --}}
    <div id="requestItemQuantityRow">
        <x-form.row
            id="requestItemQuantity"
            name="request-quantity"
            type="number"
            :label="trans('general.qty')"
            :min="1"
            default="1"
            required />
    </div>

    {{-- Start / end dates are optional. Requesters who just
         want "whenever this becomes available" leave both
         blank. Requesters reserving for a specific window
         (offsite event, project sprint) fill them in.
         end_date validates as after_or_equal:start_date in
         the controller. --}}

    <x-form.row
        id="requestItemCheckoutReason"
        name="checkout_reason"
        type="textarea"
        :rows="3"
        :label="'申请理由'"
        required />

    <div class="row">
        <x-form.row
            class="col-md-6"
            id="requestItemEndDate"
            name="end_date"
            type="datepicker"
            :label="trans('general.end_date')" />
    </div>

    {{-- 用户申请时填写的地址 --}}
    <div class="row">
        <x-input.location-select
            :label="'申请地址'"
            name="location_id"
            :selected="old('location_id')"
            required />
    </div>
    <div class="row">
        <x-input.approver-select admin-only
            :label="'审批人'"
            name="approver_id"
            :selected="old('approver_id', $user->approver_id ?? null)"
            required />
    </div>

    {{-- Optional free-text notes so the requester can
         attach context (why they need it, budget code,
         project, etc). Persisted on
         checkout_requests.notes and surfaced on the
         admin queue + in the "new request"
         notification. --}}
    <x-form.row
        id="requestItemNotes"
        name="notes"
        type="textarea"
        :rows="3"
        :label="trans('general.notes')" />
</x-modals>