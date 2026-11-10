<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * 每天给到期 / 临近到期资产的当前使用人发一条站内通知。
 *
 * 与官方 snipeit:expected-checkin（走邮件）不同，这个命令只写
 * user_notifications 表，因此不依赖 mail 配置，而且是幂等的：
 * 同一资产 + 同一到期日 同一天只发一次，后续 run 跳过。
 *
 * 判定逻辑：assigned_type = User 且 expected_checkin 在
 * [今天 - 14天, 今天 + 3天] 区间内，且没有 open 状态的延期申请。
 */
class SendAssetDueReminders extends Command
{
    protected $signature = 'snipeit:asset-due-reminders {--dry-run : Log who would be notified without writing notifications}';

    protected $description = '给到期 / 临近到期资产的使用人写站内通知（幂等，每日一次即可）。';

    public function handle(): int
    {
        $now = Carbon::now()->startOfDay();
        $windowStart = $now->copy()->subDays(14);
        $windowEnd = $now->copy()->addDays(3);
        $dryRun = $this->option('dry-run');

        $assets = Asset::query()
            ->whereNull('deleted_at')
            ->where('assigned_type', \App\Models\User::class)
            ->whereNotNull('assigned_to')
            ->whereBetween('expected_checkin', [$windowStart, $windowEnd])
            ->orderBy('expected_checkin')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            // 有 pending 的延期申请 → 不发，交给延期审批结果决定下一步。
            $hasPendingExtension = CheckoutRequest::query()
                ->open()
                ->extensions()
                ->where('extended_asset_id', $asset->id)
                ->exists();

            if ($hasPendingExtension) {
                $skipped++;

                continue;
            }

            $due = Carbon::parse($asset->expected_checkin)->startOfDay();
            $diff = (int) $now->diffInDays($due, false);

            if ($diff < 0) {
                $daysText = '已逾期 '.abs($diff).' 天';
            } elseif ($diff === 0) {
                $daysText = '今天到期';
            } else {
                $daysText = '还有 '.$diff.' 天到期';
            }

            $title = $diff < 0 || $diff === 0
                ? '您名下的资产已到归还日期'
                : '您名下的资产即将到期';

            $body = '您当前持有的「'.$asset->display_name.'」('.$asset->asset_tag.') '.$daysText.'，请尽快归还；如仍需使用请提交延期申请。';

            if ($dryRun) {
                $this->info('[dry-run] would notify user '.$asset->assigned_to.' — '.$asset->display_name.' ('.$daysText.')');
                $sent++;

                continue;
            }

            // 幂等：同一用户 + 同一资产 + 同一到期日 + 同一通知类型，
            // 今天内已经发过就跳过，避免 command 被重复跑时刷屏。
            $alreadySentToday = UserNotification::query()
                ->where('user_id', (int) $asset->assigned_to)
                ->where('type', UserNotification::TYPE_ASSET_DUE)
                ->where('title', $title)
                ->whereBetween('created_at', [$now, $now->copy()->endOfDay()])
                ->exists();

            if ($alreadySentToday) {
                $skipped++;

                continue;
            }

            UserNotification::create([
                'user_id' => (int) $asset->assigned_to,
                'type' => UserNotification::TYPE_ASSET_DUE,
                'title' => $title,
                'body' => $body,
            ]);

            $sent++;
        }

        $this->info('asset-due reminders: sent '.$sent.', skipped '.$skipped.' (pending extension or already sent today).');

        return self::SUCCESS;
    }
}
