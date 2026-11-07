@props([])

{{-- 导航栏站内提醒入口。显示未读数量角标 + 下拉列表。
     仅在已登录且存在未读时渲染；未读集合由 UserNotification 做
     请求级缓存，同一页面内角标和弹窗不会重复查询。 --}}
@if (auth()->check() && ! ($unread = \App\Models\UserNotification::unreadFor((int) auth()->id()))->isEmpty())
    <li class="dropdown notifications-menu">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" tabindex="-1" aria-haspopup="true" aria-expanded="false"
           data-tooltip="true" data-placement="bottom" data-title="{{ trans('general.notifications') }}">
            <x-icon type="bell" class="fa-fw" />
            <span class="label label-danger">{{ $unread->count() }}</span>
            <span class="sr-only">{{ trans('general.notifications') }}</span>
        </a>
        <ul class="dropdown-menu">
            <li class="header">{{ trans_choice('general.notifications_unread_count', $unread->count(), ['count' => $unread->count()]) }}</li>
            <li>
                <ul class="menu">
                    @foreach ($unread as $notification)
                        <li>
                            <a href="{{ route('account.requested') }}" style="white-space: normal;">
                                <strong>{{ $notification->title }}</strong>
                                @if ($notification->body)
                                    <div class="text-muted">{{ $notification->body }}</div>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
            <li class="footer"><a href="{{ route('account.notifications.index') }}">查看全部消息</a></li>
        </ul>
    </li>
@endif
