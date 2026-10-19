<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Exceptions\RelaisLocked;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Verrou de traitement (§8.2, COO-14) : le relais ouvert par un coordonnateur est réservé
 * pendant 10 minutes (paramétrable) pour éviter les décisions contradictoires. Le premier qui ouvre l'obtient.
 */
class LockRelaisItem
{
    public function __construct(
        private readonly Settings $settings,
        private readonly TransitionRelaisItem $transition,
    ) {}

    public function handle(RelaisItem $relais, User $user): RelaisItem
    {
        $until = now()->addMinutes($this->settings->integer('validation.lock_minutes'));

        $acquired = RelaisItem::withoutCommuneRestriction()
            ->whereKey($relais->id)
            ->where(fn ($query) => $query->whereNull('locked_by')->orWhere('locked_by', $user->id)->orWhere('locked_until', '<', now()))
            ->update(['locked_by' => $user->id, 'locked_until' => $until]);

        $relais->refresh();

        if ($acquired === 0) {
            throw RelaisLocked::by($relais);
        }

        if ($relais->state instanceof Received) {
            DB::transaction(fn () => $this->transition->handle($relais, InReview::class, $user));
        }

        return $relais;
    }

    /**
     * Libère le verrou (décision prise ou abandon) ; le relais revient dans la file s'il est encore en examen.
     */
    public function release(RelaisItem $relais, User $user): void
    {
        if ($relais->locked_by !== $user->id) {
            return;
        }

        $relais->forceFill(['locked_by' => null, 'locked_until' => null])->save();

        if ($relais->state instanceof InReview) {
            $this->transition->handle($relais, Received::class, $user, __('Verrou libéré sans décision'));
        }
    }
}
