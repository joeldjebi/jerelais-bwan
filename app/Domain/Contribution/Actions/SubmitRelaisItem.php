<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Actions\ResolveCommuneFromPoint;
use App\Domain\Territoire\Models\Commune;
use Illuminate\Support\Facades\DB;

/**
 * Envoi d'un relais (§3.2, §8.2) : contrôle des informations obligatoires et des questions de protection,
 * routage vers la commune déduite du GPS (sinon la commune déclarée), délai cible, passage à « Reçu ».
 */
class SubmitRelaisItem
{
    public function __construct(
        private readonly ResolveCommuneFromPoint $resolveCommune,
        private readonly TransitionRelaisItem $transition,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(RelaisItem $relais): RelaisItem
    {
        RelaisSender::ensureCanSend($relais->user);

        if (! $relais->state instanceof Uploading) {
            // Envoi déjà fait : l'application peut réessayer sans risque (idempotence).
            if ($relais->submitted_at !== null) {
                return $relais;
            }

            throw RelaisNotEditable::make();
        }

        $missing = $this->missing($relais);
        $gpsCommune = $relais->location === null ? null : $this->resolveCommune->handle($relais->location);
        $commune = $gpsCommune
            ?? ($relais->declared_commune_id === null ? null : Commune::query()->active()->find($relais->declared_commune_id));

        if ($commune === null) {
            $missing['declared_commune'] = __('relais_items.errors.no_commune');
        }

        if ($missing !== [] || $commune === null) {
            throw new RelaisIncomplete($missing);
        }

        DB::transaction(function () use ($relais, $commune, $gpsCommune): void {
            $now = now();

            $relais->forceFill([
                'commune_id' => $commune->id,
                'routed_by' => $gpsCommune !== null ? 'gps' : 'declared',
                'submitted_at' => $now,
                'received_at' => $now,
                'sla_due_at' => $now->copy()->addMinutes($this->settings->integer($relais->urgency->slaSetting())),
            ])->save();

            $this->transition->handle($relais, Received::class, $relais->user);

            $this->audit->record('relais.received', $relais, [
                'routed_by' => $relais->routed_by,
                'urgency' => $relais->urgency->value,
            ], actor: $relais->user, communeId: $relais->commune_id);
        });

        return $relais;
    }

    /**
     * @return array<string, string>
     */
    public function missing(RelaisItem $relais): array
    {
        $missing = [];
        $required = [
            'category' => $relais->category_id,
            'title' => $relais->title,
            'has_minors' => $relais->has_minors,
            'people_consent' => $relais->people_consent,
            'credit_mode' => $relais->credit_mode,
        ];

        foreach ($required as $field => $value) {
            if ($value === null || $value === '') {
                $missing[$field] = __('validation.required', ['attribute' => __("relais_items.attributes.{$field}")]);
            }
        }

        $medias = $relais->medias()->where('status', '!=', MediaStatus::Rejected->value)->pluck('status');

        if ($medias->isEmpty()) {
            $missing['medias'] = __('media.none');
        } elseif ($medias->contains(fn (mixed $status): bool => ($status instanceof MediaStatus ? $status : MediaStatus::from((string) $status)) === MediaStatus::Pending)) {
            $missing['medias'] = __('media.still_uploading');
        }

        if ($relais->credit_mode === CreditMode::Pseudonym && blank($relais->credit_name)) {
            $missing['credit_name'] = __('validation.required', ['attribute' => __('relais_items.attributes.credit_name')]);
        }

        return $missing;
    }
}
