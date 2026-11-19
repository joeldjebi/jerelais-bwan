<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Un lot de destinataires d'une annonce : push sur leurs appareils, SMS de secours si demandé, canal enregistré.
 */
class SendAnnouncementChunk implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public readonly int $announcementId, public readonly array $userIds) {}

    public function handle(NotifyUser $notify): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $announcement = Announcement::query()->find($this->announcementId);

        if ($announcement === null) {
            return;
        }

        $push = new PushMessage($announcement->title, $announcement->body, [
            'type' => 'announcement',
            'uuid' => $announcement->uuid,
            'screen' => $announcement->screen->value,
        ]);
        $sms = $announcement->with_sms ? (string) Str::limit('JE RELAIS : '.$announcement->title.' — '.$announcement->body, 300) : null;
        $counts = ['push' => 0, 'sms' => 0, 'none' => 0];

        foreach (User::query()->whereIn('id', $this->userIds)->get() as $user) {
            $channel = $notify->deliver($user, $push, $sms, 'announcement');
            $counts[$channel ?? 'none']++;

            DB::table('announcement_recipients')
                ->where('announcement_id', $announcement->id)->where('user_id', $user->id)
                ->update(['channel' => $channel, 'updated_at' => now()]);
        }

        Announcement::query()->whereKey($announcement->id)->incrementEach([
            'push_count' => $counts['push'],
            'sms_count' => $counts['sms'],
            'unreached_count' => $counts['none'],
        ]);
    }
}
