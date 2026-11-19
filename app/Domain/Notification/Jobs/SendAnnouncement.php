<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Enums\AnnouncementStatus;
use App\Domain\Notification\Models\Announcement;
use App\Domain\Notification\Support\AnnouncementTargets;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Lance l'envoi d'une annonce (MOB-21) : liste figée des destinataires (boîte de réception de l'application), puis
 * envoi par lots de 50 Relais en parallèle ; l'annonce passe « envoyée » quand tous les lots sont terminés.
 */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    public const CHUNK = 50;

    public function __construct(public readonly int $announcementId) {}

    public function handle(): void
    {
        // Un seul envoi, même si la commande programmée et un clic se croisent.
        $claimed = Announcement::query()->whereKey($this->announcementId)
            ->where('status', AnnouncementStatus::Scheduled->value)
            ->update(['status' => AnnouncementStatus::Sending->value]);

        if ($claimed === 0) {
            return;
        }

        $announcement = Announcement::query()->findOrFail($this->announcementId);
        $userIds = AnnouncementTargets::profiles($announcement->audience, $announcement->audience_ids ?? [])
            ->pluck('relais_profiles.user_id')->map(fn (mixed $id): int => (int) $id)->unique()->values();

        $now = now();

        foreach ($userIds->chunk(500) as $chunk) {
            DB::table('announcement_recipients')->insertOrIgnore($chunk->map(fn (int $userId): array => [
                'announcement_id' => $announcement->id, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }

        $announcement->forceFill(['recipients_count' => $userIds->count()])->save();

        if ($userIds->isEmpty()) {
            $announcement->forceFill(['status' => AnnouncementStatus::Sent, 'sent_at' => now()])->save();

            return;
        }

        $id = $announcement->id;

        Bus::batch($userIds->chunk(self::CHUNK)->map(fn ($chunk) => new SendAnnouncementChunk($id, array_values($chunk->all())))->values()->all())
            ->name('annonce '.$announcement->uuid)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($id): void {
                Announcement::query()->whereKey($id)->update(['status' => AnnouncementStatus::Sent->value, 'sent_at' => now()]);
            })
            ->dispatch();
    }
}
