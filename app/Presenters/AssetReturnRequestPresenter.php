<?php

namespace App\Presenters;

/**
 * bs-table column layout for the /return-requests queue.
 * Pairs with GET /api/v1/return-requests + AssetReturnRequestsTransformer.
 */
class AssetReturnRequestPresenter extends Presenter
{
    public static function dataTableLayout(): string
    {
        $layout = [
            [
                'field' => 'asset.image',
                'scope' => 'col',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'visible' => true,
                'title' => trans('general.image'),
                'formatter' => 'imageFormatter',
            ], [
                'field' => 'asset.asset_tag',
                'scope' => 'col',
                'searchable' => true,
                'sortable' => false,
                'title' => trans('general.asset_tag'),
                'visible' => true,
            ], [
                'field' => 'asset.name',
                'scope' => 'col',
                'searchable' => true,
                'sortable' => false,
                'title' => trans('general.name'),
                'visible' => true,
            ], [
                'field' => 'user.name',
                'scope' => 'col',
                'searchable' => true,
                'sortable' => false,
                'title' => trans('admin/hardware/table.requesting_user'),
                'visible' => true,
            ], [
                'field' => 'asset.location.name',
                'scope' => 'col',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'visible' => true,
                'title' => trans('general.location'),
            ], [
                'field' => 'asset.rtd_location.name',
                'scope' => 'col',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'visible' => true,
                'title' => trans('admin/hardware/form.default_location'),
            ], [
                'field' => 'requested_at',
                'scope' => 'col',
                'searchable' => false,
                'sortable' => true,
                'title' => '申请时间',
                'visible' => true,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'note',
                'scope' => 'col',
                'searchable' => true,
                'sortable' => false,
                'switchable' => true,
                'visible' => false,
                'title' => trans('general.notes'),
                'formatter' => 'notesFormatter',
            ], [
                'field' => 'actions',
                'scope' => 'col',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('button.actions'),
                'visible' => true,
                'formatter' => 'returnRequestQueueActionsFormatter',
                'printIgnore' => true,
                'class' => 'hidden-print',
            ],
        ];

        return json_encode($layout);
    }
}
