<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Access\Enums\CommuneAssignment;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Notifications\RelaisOverdue;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Escalade des relais en retard (COO-17, §8.2), lancée chaque minute :
 * niveau 1 au délai cible (suppléant de la commune), niveau 2 un délai cible plus tard (superviseur régional),
 * niveau 3 encore après (administration). Un niveau sans destinataire passe directement au suivant.
 */
class EscalateOverdueRelais
{
    public const MAX_LEVEL = 3;

    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return int nombre de relais escaladés
     */
    public function handle(): int
    {
        $escalated = 0;

        RelaisItem::withoutCommuneRestriction()
            ->with('commune')
            ->whereIn('state', [Received::getMorphClass(), InReview::getMorphClass()])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->where('escalation_level', '<', self::MAX_LEVEL)
            ->orderBy('sla_due_at')
            ->each(function (RelaisItem $relais) use (&$escalated): void {
                if ($this->escalate($relais)) {
                    $escalated++;
                }
            });

        return $escalated;
    }

    private function escalate(RelaisItem $relais): bool
    {
        $sla = max(1, $this->settings->integer($relais->urgency->slaSetting()));
        $overdueMinutes = (int) $relais->sla_due_at?->diffInMinutes(now(), true);
        $dueLevel = min(self::MAX_LEVEL, intdiv($overdueMinutes, $sla) + 1);

        if ($dueLevel <= $relais->escalation_level) {
            return false;
        }

        $level = $relais->escalation_level + 1;
        $recipients = $this->recipientsFor($relais, $level);

        while ($recipients->isEmpty() && $level < self::MAX_LEVEL) {
            $recipients = $this->recipientsFor($relais, ++$level);
        }

        $relais->forceFill(['escalation_level' => $level, 'escalated_at' => now()])->save();

        Notification::send($recipients, new RelaisOverdue($relais, $level));

        $this->audit->record('relais.escalated', $relais, [
            'level' => $level,
            'recipients' => $recipients->count(),
        ], communeId: $relais->commune_id);

        return true;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(RelaisItem $relais, int $level): Collection
    {
        $active = User::query()->where('status', UserStatus::Active->value);

        return match ($level) {
            1 => $active->whereHas('communes', fn ($query) => $query
                ->where('communes.id', $relais->commune_id)
                ->where('commune_user.assignment', CommuneAssignment::Suppleant->value))->get(),
            2 => $relais->commune?->region_id === null
                ? new Collection
                : $active->role(Role::Superviseur->value)->whereHas('regions', fn ($query) => $query->where('regions.id', $relais->commune->region_id))->get(),
            default => $active->role(Role::Admin->value)->get(),
        };
    }
}
