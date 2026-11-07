@props([])

{{-- 登录后的站内提醒弹窗。
     1. 按类型分组展示（例如「审批通过 2 条」），避免未读太多时逐条堆叠；
        每组可展开查看明细。
     2. 弹窗只弹一次：点击页脚任意按钮（我的申请 / 查看全部消息 / 我知道了）
        或关闭（X）后，立即把当前未读全部标记为已读，跳转链接会在标记完成后
        再跳，避免新页面仍读到未读而重复弹窗。 --}}
@if (auth()->check() && ! ($unread = \App\Models\UserNotification::unreadFor((int) auth()->id()))->isEmpty())
    @php
        // 按 groupLabel 分组，保留每组最新一条作为摘要。
        $grouped = $unread->groupBy(fn ($n) => $n->groupLabel());
    @endphp
    <div class="modal fade" id="userNotificationsModal" tabindex="-1" role="dialog" aria-labelledby="userNotificationsModalTitle">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('general.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="userNotificationsModalTitle">
                        <x-icon type="bell" class="fa-fw" />
                        {{ trans('general.notifications') }}
                    </h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted small" style="margin-bottom: 10px;">
                        共 {{ $unread->count() }} 条未读消息
                    </p>
                    @foreach ($grouped as $label => $items)
                        <div class="notification-group" style="border: 1px solid #f4f4f4; border-radius: 4px; margin-bottom: 8px;">
                            <div class="notification-group-header"
                                 style="padding: 10px 12px; cursor: pointer; background: #fafafa; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;"
                                 data-toggle="collapse"
                                 data-target="#notification-group-{{ $loop->index }}">
                                <span><strong>{{ $label }}</strong></span>
                                <span class="badge bg-primary">{{ $items->count() }} 条</span>
                            </div>
                            <div id="notification-group-{{ $loop->index }}" class="collapse">
                                <ul class="list-unstyled" style="margin: 0; padding: 6px 12px;">
                                    @foreach ($items as $notification)
                                        <li style="padding: 6px 0; border-bottom: 1px solid #f4f4f4;">
                                            @if ($notification->body)
                                                <div>{{ $notification->body }}</div>
                                            @else
                                                <div>{{ $notification->title }}</div>
                                            @endif
                                            @if ($notification->created_at)
                                                <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <a href="{{ route('account.requested') }}" class="btn btn-default notification-action-link">{{ trans('general.my_requests') }}</a>
                    <a href="{{ route('account.notifications.index') }}" class="btn btn-default notification-action-link">查看全部消息</a>
                    <button type="button" class="btn btn-primary notification-dismiss" data-dismiss="modal">{{ trans('general.got_it') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var markReadUrl = @json(route('account.notifications.mark-read'));
            var csrfToken = @json(csrf_token());
            var markedRead = false;

            function markAllRead(done) {
                if (markedRead) {
                    if (typeof done === 'function') { done(); }
                    return;
                }
                markedRead = true;
                jQuery.ajax({
                    type: 'POST',
                    url: markReadUrl,
                    data: { _token: csrfToken },
                    complete: function () {
                        jQuery('.notifications-menu').remove();
                        if (typeof done === 'function') { done(); }
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var element = document.getElementById('userNotificationsModal');
                if (!element || typeof jQuery === 'undefined') {
                    return;
                }

                var modal = jQuery(element);
                modal.modal('show');

                // 任意按钮/链接点击 → 先标记已读，再执行跳转或关闭。
                modal.find('.notification-action-link').on('click', function (e) {
                    e.preventDefault();
                    var href = this.getAttribute('href');
                    markAllRead(function () { window.location.href = href; });
                });

                modal.find('.notification-dismiss').on('click', function () {
                    markAllRead();
                });

                // X 关闭按钮、点遮罩等其它关闭路径也兜底标记已读。
                modal.on('hidden.bs.modal', function () {
                    markAllRead();
                });
            });
        })();
    </script>
@endif
