{{-- 归还申请弹窗。Action URL 由 snipeit.js 的 .request-return
     处理器从触发按钮的 data-return-url 写入，形状与
     request-item / adjust-quantity 弹窗一致。 --}}
<x-modals
    id="returnRequestModal"
    stacked
    submit_label="提交归还申请"
    form_attrs='id="returnRequestForm" accept-charset="utf-8"'>
    <x-slot:header>
        <h4 class="modal-title" id="returnRequestModalLabel">
            申请归还
            <small class="return-request-asset-name text-muted"></small>
        </h4>
    </x-slot:header>

    <x-form.row
        id="returnRequestNote"
        name="note"
        type="textarea"
        :rows="3"
        :label="trans('general.notes')" />
</x-modals>
