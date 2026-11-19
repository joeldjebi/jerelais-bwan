<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notification\Enums\AnnouncementAudience;
use App\Domain\Notification\Enums\AnnouncementScreen;
use App\Domain\Notification\Enums\AnnouncementStatus;
use App\Domain\Notification\Jobs\SendAnnouncement;
use App\Domain\Notification\Models\Announcement;
use App\Domain\Notification\Support\AnnouncementTargets;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Annonce de l'administration aux Relais (MOB-21) : envoyée tout de suite ou à l'heure programmée
 * (commande announcements:send-due, chaque minute). Inscrite au journal d'audit.
 */
class PublishAnnouncement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $ids  uuid des régions, des communes ou du compte du Relais
     *
     * @throws InvalidArgumentException ciblage vide ou date passée (message prêt à afficher)
     */
    public function handle(
        User $author,
        string $title,
        string $body,
        AnnouncementAudience $audience,
        array $ids,
        AnnouncementScreen $screen,
        bool $withSms,
        ?CarbonImmutable $scheduledAt = null,
    ): Announcement {
        if (in_array($audience, [AnnouncementAudience::Regions, AnnouncementAudience::Communes], true) && $ids === []) {
            throw new InvalidArgumentException(__('announcements.errors.zone_required'));
        }

        if ($audience === AnnouncementAudience::Relais && $ids === []) {
            throw new InvalidArgumentException(__('announcements.errors.relais_required'));
        }

        if ($scheduledAt !== null && $scheduledAt->isPast()) {
            throw new InvalidArgumentException(__('announcements.errors.scheduled_in_past'));
        }

        if (AnnouncementTargets::profiles($audience, $ids)->doesntExist()) {
            throw new InvalidArgumentException(__('announcements.errors.no_recipient'));
        }

        $announcement = Announcement::query()->create([
            'title' => trim($title),
            'body' => trim($body),
            'screen' => $screen,
            'audience' => $audience,
            'audience_ids' => $ids === [] ? null : $ids,
            'with_sms' => $withSms,
            'status' => AnnouncementStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
            'created_by' => $author->id,
        ]);

        $this->audit->record('announcement.created', $announcement, [
            'title' => $announcement->title,
            'audience' => $audience->value,
            'sms' => $withSms,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
        ], actor: $author);

        if ($scheduledAt === null) {
            SendAnnouncement::dispatch($announcement->id);
        }

        return $announcement->refresh();
    }

    public function cancel(Announcement $announcement, User $author): void
    {
        $cancelled = Announcement::query()->whereKey($announcement->id)
            ->where('status', AnnouncementStatus::Scheduled->value)
            ->update(['status' => AnnouncementStatus::Cancelled->value]);

        if ($cancelled === 0) {
            throw new InvalidArgumentException(__('announcements.errors.not_cancellable'));
        }

        $this->audit->record('announcement.cancelled', $announcement, actor: $author);
    }
}
