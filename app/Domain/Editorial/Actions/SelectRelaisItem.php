<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Exploitation d'un relais validé (ADM-10, ADM-13) : sélection avec affectation à un journaliste ou une émission,
 * retrait de la sélection, puis marquage « diffusé » (date, émission, lien) qui prévient le Relais.
 */
class SelectRelaisItem
{
    public function __construct(
        private readonly TransitionRelaisItem $transition,
        private readonly AuditLogger $audit,
    ) {}

    public function select(RelaisItem $relais, User $editor, ?User $journalist, ?string $programme): RelaisItem
    {
        if (! $relais->state instanceof Validated && ! $relais->state instanceof Selected) {
            throw new EditorialRefused(__('editorial.errors.not_validated'));
        }

        DB::transaction(function () use ($relais, $editor, $journalist, $programme): void {
            $relais->forceFill([
                'selected_by' => $editor->id,
                'selected_at' => $relais->selected_at ?? now(),
                'assigned_to' => $journalist?->id,
                'programme' => $programme === null ? null : (trim($programme) ?: null),
            ])->save();

            if ($relais->state instanceof Validated) {
                $this->transition->handle($relais, Selected::class, $editor);
            }

            $this->audit->record('relais.selected', $relais, array_filter([
                'journalist' => $journalist?->name,
                'programme' => $relais->programme,
            ]), actor: $editor, communeId: $relais->commune_id);
        });

        return $relais;
    }

    public function unselect(RelaisItem $relais, User $editor): RelaisItem
    {
        if (! $relais->state instanceof Selected) {
            throw new EditorialRefused(__('editorial.errors.not_selected'));
        }

        DB::transaction(function () use ($relais, $editor): void {
            $relais->forceFill(['selected_by' => null, 'selected_at' => null, 'assigned_to' => null, 'programme' => null])->save();
            $this->transition->handle($relais, Validated::class, $editor, __('Retiré de la sélection'));
            $this->audit->record('relais.unselected', $relais, actor: $editor, communeId: $relais->commune_id);
        });

        return $relais;
    }

    public function markBroadcast(RelaisItem $relais, User $editor, CarbonImmutable $at, string $programme, ?string $url): RelaisItem
    {
        if (! $relais->state instanceof Selected) {
            throw new EditorialRefused(__('editorial.errors.not_selected'));
        }

        DB::transaction(function () use ($relais, $editor, $at, $programme, $url): void {
            $relais->forceFill([
                'broadcast_at' => $at,
                'broadcast_programme' => trim($programme),
                'broadcast_url' => $url === null ? null : (trim($url) ?: null),
            ])->save();

            $this->transition->handle($relais, Broadcast::class, $editor, $programme);
            $this->audit->record('relais.broadcast', $relais, array_filter([
                'programme' => $relais->broadcast_programme,
                'at' => $at->toIso8601String(),
                'url' => $relais->broadcast_url,
            ]), actor: $editor, communeId: $relais->commune_id);
        });

        return $relais;
    }
}
