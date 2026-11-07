@extends('layouts/default')

{{-- Page title --}}
@section('title')
    我的消息
    @parent
@stop

{{-- Page content --}}
@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">我的消息</h3>
                <div class="box-tools pull-right">
                    <form method="POST" action="{{ route('account.notifications.mark-read') }}" id="markAllReadForm" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-default btn-sm">
                            <x-icon type="checkmark" class="fa-fw"/> 全部标为已读
                        </button>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive">
                @if ($notifications->isEmpty())
                    <p class="text-muted text-center" style="padding: 30px 0;">暂无消息</p>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 90px;">状态</th>
                                <th style="width: 160px;">时间</th>
                                <th style="width: 160px;">标题</th>
                                <th>内容</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notifications as $notification)
                                <tr @if (is_null($notification->read_at)) class="warning" @endif>
                                    <td>
                                        @if (is_null($notification->read_at))
                                            <span class="label label-warning">未读</span>
                                        @else
                                            <span class="label label-default">已读</span>
                                        @endif
                                    </td>
                                    <td>{{ $notification->created_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $notification->title }}</td>
                                    <td>{{ $notification->body }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if ($notifications->hasPages())
                <div class="box-footer clearfix">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    // 「全部标为已读」走 AJAX，成功后刷新页面状态，与铃铛/弹窗的
    // mark-read 行为保持一致（不跳转、无整页表单提交）。
    $('#markAllReadForm').on('submit', function (event) {
        event.preventDefault();
        $.ajax({
            type: 'POST',
            url: $(this).attr('action'),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function () {
                window.location.reload();
            },
        });
    });
</script>
@stop
