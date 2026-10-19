<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arbitrage de la rédaction (§8.2) : décision sur un relais « sensible », ou retour sur la décision
 * d'un coordonnateur (validé ↔ rejeté). Motif obligatoire, l'ancienne décision reste dans l'historique et l'audit.
 */
class ArbitrateRelaisItem
{
    public function __construct(
        private readonly TransitionRelaisItem $transition,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(RelaisItem $relais, bool $validate, User $editor, string $reason, ?bool $blurRequired = null): RelaisItem
    {
        $reason = trim($reason);
        $allowed = $relais->state instanceof Sensitive
            || ($validate && $relais->state instanceof Rejected)
            || (! $validate && $relais->state instanceof Validated);

        if (! $allowed) {
            throw new EditorialRefused(__('editorial.errors.not_arbitrable'));
        }

        if ($reason === '') {
            throw new EditorialRefused(__('editorial.errors.reason_required'));
        }

        DB::transaction(function () use ($relais, $validate, $editor, $reason, $blurRequired): void {
            $from = $relais->state->getValue();

            if ($validate && $blurRequired !== null) {
                $relais->forceFill(['blur_required' => $blurRequired])->save();
            }

            $this->transition->handle($relais, $validate ? Validated::class : Rejected::class, $editor, $reason);

            $this->audit->record('relais.arbitrated', $relais, [
                'from' => $from,
                'to' => $relais->state->getValue(),
                'reason' => $reason,
            ], actor: $editor, communeId: $relais->commune_id);
        });

        return $relais;
    }
}
