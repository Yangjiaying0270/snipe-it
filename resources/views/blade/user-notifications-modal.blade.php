{{-- 登录后首次弹窗：按类型分组 + 每组整行可点击跳转。 --}}

@php
    $grouped = $unread->groupBy(fn ($n) => $n->groupLabel());
    $total = $unread->count();
@endphp

<div class="modal fade" id="notificationsModal" tabindex="-1" role="dialog" aria-labelledby="notificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close notification-dismiss" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="notificationsModalLabel">
                    <i class="fas fa-bell"></i>
                    共 {{ $total }} 条未读消息
                </h4>
            </div>
            <div class="modal-body">
                @if ($grouped->isEmpty())
                    <p class="text-muted text-center">没有新消息。</p>
                @else
                    <div class="list-group">
                        @foreach ($grouped as $label => $items)
                            @php
                                // 组内所有通知 type 相同（groupLabel() 按 type 分组），
                                // 取第一条的 targetUrl 作为点击跳转目标。
                                $url = $items->first()->targetUrl();
                            @endphp
                            <a href="{{ $url }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center notification-group-link">
                                <div>
                                    <h4 class="list-group-item-heading" style="margin-bottom: 2px;">
                                        {{ $label }}
                                    </h4>
                                    <p class="list-group-item-text text-muted" style="margin-bottom: 0;">
                                        {{ $items->count() === 1
                                            ? $items->first()->body
                                            : $items->first()->body.' 等 '.$items->count().' 条' }}
                                    </p>
                                </div>
                                <span>
                                    <span class="badge badge-warning">{{ $items->count() }}</span>
                                    <i class="fas fa-chevron-right text-muted" style="margin-left: 8px;"></i>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default notification-dismiss" data-dismiss="modal">
                    {{ trans('general.got_it', default: '我知道了') }}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .notification-group-link {
        transition: background-color 0.15s;
    }
    .notification-group-link:hover {
        background-color: #f0f6ff;
        text-decoration: none;
    }
</style>

<script>
    $(function () {
        var $modal = $('#notificationsModal');

        /**
         * 向服务器 POST /account/notifications/mark-read，失败也不阻塞。
         */
        function markAllRead() {
            $.post("{{ route('account.notifications.mark-read') }}", {
                _token: $('meta[name="csrf-token"]').attr('content'),
            }).fail(function () { /* silent */ });
        }

        /**
         * 把一个 a.link-group-item 里的每条通知置为已读，然后跳转。
         */
        function markGroupReadBeforeNavigate($link, href) {
            $link.addClass('disabled').css('pointer-events', 'none');
            $link.find('.list-group-item-heading').append(' <i class="fas fa-spinner fa-spin"></i>');

            // 标记已读后再跳转（避免 race-condition：弹窗的 markAllRead
            // 还没发出请求，另一个请求又读到未读状态）。
            $.when(
                $.post("{{ route('account.notifications.mark-read') }}", {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                })
            ).always(function () {
                window.location.href = href;
            });
        }

        // 点击分组 = 置已读 + 跳转
        $modal.on('click', '.notification-group-link', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            markGroupReadBeforeNavigate($(this), href);
        });

        // 点「我知道了」或右上角关闭 → 全部置已读
        $modal.on('click', '.notification-dismiss', function () {
            markAllRead();
        });

        // 点遮罩也全部置已读，保证"只弹一次"
        $modal.on('hidden.bs.modal', function () {
            markAllRead();
        });
    });
</script>
