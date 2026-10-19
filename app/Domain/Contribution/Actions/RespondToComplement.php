<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\Received;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Réponse du Relais à une demande de complément (COO-14, MOB-22) : précision écrite et/ou média ajouté
 * (par l'envoi de médias habituel), puis retour dans la file du coordonnateur avec un nouveau délai cible.
 */
class RespondToComplement
{
    public function __construct(
        private readonly TransitionRelaisItem $transition,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(RelaisItem $relais, ?string $message): RelaisItem
    {
        if (! $relais->state instanceof ComplementRequested) {
            throw RelaisNotEditable::make();
        }

        $message = $message === null ? null : (trim($message) ?: null);
        $newMedia = $relais->medias()->where('created_at', '>', $relais->statusHistory()->where('to', ComplementRequested::getMorphClass())->latest('id')->value('created_at') ?? $relais->created_at);

        if ($message === null && ! (clone $newMedia)->exists()) {
            throw new RelaisIncomplete(['message' => __('relais_items.errors.complement_empty')]);
        }

        if ((clone $newMedia)->where('status', MediaStatus::Pending->value)->exists()) {
            throw new RelaisIncomplete(['medias' => __('media.still_uploading')]);
        }

        DB::transaction(function () use ($relais, $message): void {
            $relais->forceFill([
                'sla_due_at' => now()->addMinutes($this->settings->integer($relais->urgency->slaSetting())),
                'escalation_level' => 0,
                'escalated_at' => null,
            ])->save();

            $this->transition->handle($relais, Received::class, $relais->user, $message);
            $this->audit->record('relais.complement_received', $relais, array_filter(['message' => $message]), actor: $relais->user, communeId: $relais->commune_id);
        });

        return $relais;
    }
}
