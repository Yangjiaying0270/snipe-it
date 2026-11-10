@props([])

{{-- 登录后的站内提醒弹窗。
     1. 按类型分组展示（例如「审批通过 2 条」），每组整行可点击跳 targetUrl。
     2. 弹窗只留「我知道了」一个按钮；关闭后把当前未读全部标记为已读，
        避免新页面仍读到未读而重复弹窗。
     3. $unread 由 UserNotification 做请求级缓存，与铃铛角标共用一次查询。 --}}
@if (auth()->check() && ! ($unread = \App\Models\UserNotification::unreadFor((int) auth()->id()))->isEmpty())
    @php
        $grouped = $unread->groupBy(fn ($n) => $n->groupLabel());
    @endphp

    <div class="modal fade" id="notificationsModal" tabindex="-1" role="dialog" aria-labelledby="notificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close notification-dismiss" data-dismiss="modal" aria-label="{{ trans('general.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="notificationsModalLabel">
                        <x-icon type="bell" class="fa-fw" />
                        共 {{ $unread->count() }} 条未读消息
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        @foreach ($grouped as $label => $items)
                            @php
                                // 组内通知 type 同组，取第一条的 targetUrl 作为跳转目标。
                                $url = $items->first()->targetUrl();
                            @endphp
                            <a href="{{ $url }}"
                               class="list-group-item notification-group-link">
                                <div class="pull-right">
                                    <span class="badge badge-warning">{{ $items->count() }}</span>
                                    <i class="fas fa-chevron-right text-muted" style="margin-left: 8px;"></i>
                                </div>
                                <h4 class="list-group-item-heading" style="margin-bottom: 2px;">
                                    {{ $label }}
                                </h4>
                                <p class="list-group-item-text text-muted" style="margin-bottom: 0;">
                                    {{ $items->count() === 1
                                        ? $items->first()->body
                                        : $items->first()->body.' 等 '.$items->count().' 条' }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary notification-dismiss" data-dismiss="modal">
                        <x-icon type="checkmark" class="fa-fw" />
                        {{ trans('general.got_it') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .notification-group-link {
            transition: background-color 0.15s;
            white-space: normal;
        }
        .notification-group-link:hover {
            background-color: #f0f6ff;
            text-decoration: none;
        }
    </style>

    <script>
        (function () {
            var markReadUrl = @json(route('account.notifications.mark-read'));
            var csrfToken = @json(csrf_token());
            var markedRead = false;

            /** 全部置已读，只发一次请求；失败也不阻塞。 */
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
                var element = document.getElementById('notificationsModal');
                if (!element || typeof jQuery === 'undefined') {
                    return;
                }

                var modal = jQuery(element);
                modal.modal('show');

                // 点击分组整行 → 置已读后跳转该组的 targetUrl
                modal.find('.notification-group-link').on('click', function (e) {
                    e.preventDefault();
                    var href = this.getAttribute('href');
                    markAllRead(function () { window.location.href = href; });
                });

                // 「我知道了」→ 全部置已读
                modal.find('.notification-dismiss').on('click', function () {
                    markAllRead();
                });

                // X / 点遮罩等其它关闭路径也兜底标记已读
                modal.on('hidden.bs.modal', function () {
                    markAllRead();
                });
            });
        })();
    </script>
@endif
