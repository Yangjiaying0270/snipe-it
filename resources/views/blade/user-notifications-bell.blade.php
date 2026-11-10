@props([])

{{-- 导航栏站内提醒入口。显示未读数量角标 + 下拉列表。
     仅在已登录且存在未读时渲染；未读集合由 UserNotification 做
     请求级缓存，同一页面内角标和弹窗不会重复查询。 --}}
@if (auth()->check() && ! ($unread = \App\Models\UserNotification::unreadFor((int) auth()->id()))->isEmpty())
    <li class="dropdown notifications-menu">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" tabindex="-1" aria-haspopup="true" aria-expanded="false"
           data-tooltip="true" data-placement="bottom" data-title="{{ trans('general.notifications') }}">
            <x-icon type="bell" class="fa-fw" />
            <span class="label label-danger">{{ min($unread->count(), 99) }}</span>
            <span class="sr-only">{{ trans('general.notifications') }}</span>
        </a>
        <ul class="dropdown-menu">
            <li class="header">{{ trans_choice('general.notifications_unread_count', $unread->count(), ['count' => $unread->count()]) }}</li>
            <li>
                <ul class="menu">
                    @foreach ($unread as $notification)
                        <li>
                            <a href="{{ $notification->targetUrl() }}" class="bell-notification-link"
                               data-read-url="{{ route('account.notifications.read', $notification->id) }}"
                               style="white-space: normal;">
                                <strong>{{ $notification->title }}</strong>
                                @if ($notification->body)
                                    <div class="text-muted">{{ $notification->body }}</div>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
            <li class="footer">
                <a href="{{ route('account.notifications.index') }}" id="bell-view-all">查看全部消息</a>
            </li>
        </ul>
    </li>

    <script>
        (function () {
            var markAllUrl = @json(route('account.notifications.mark-read'));
            var csrfToken = @json(csrf_token());

            document.addEventListener('DOMContentLoaded', function () {
                if (typeof jQuery === 'undefined') {
                    return;
                }

                // 点击单条通知 → 置已读 + 跳 targetUrl
                jQuery('.bell-notification-link').on('click', function (e) {
                    e.preventDefault();
                    var href = this.getAttribute('href');
                    var readUrl = this.getAttribute('data-read-url');

                    jQuery.ajax({
                        type: 'POST',
                        url: readUrl,
                        data: { _token: csrfToken },
                        complete: function () {
                            window.location.href = href;
                        }
                    });
                });

                // 点「查看全部消息」→ 全部置已读后跳转
                jQuery('#bell-view-all').on('click', function (e) {
                    e.preventDefault();
                    var href = this.getAttribute('href');

                    jQuery.ajax({
                        type: 'POST',
                        url: markAllUrl,
                        data: { _token: csrfToken },
                        complete: function () {
                            window.location.href = href;
                        }
                    });
                });
            });
        })();
    </script>
@endif
