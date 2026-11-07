@extends('layouts/default')

{{-- Page title --}}
@section('title')
    归还请求
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box name="assetReturnRequests" sr_only_title>
            <x-slot:table_header>
                归还请求
            </x-slot:table_header>

            <x-table
                name="assetReturnRequests"
                :presenter="\App\Presenters\AssetReturnRequestPresenter::dataTableLayout()"
                :api_url="route('api.return-requests.index')"
                :sort_field="null"
                :fixed_right_number="1"
                :show_search="true"
                export_filename="export-return-requests-{{ date('Y-m-d') }}"
            />
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table')
@stop
