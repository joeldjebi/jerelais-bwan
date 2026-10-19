<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Arr;

/**
 * Crée ou complète un relais avant son envoi (brouillon, MOB-19).
 * Idempotent : un même « client_uuid » renvoie toujours le même relais, même si l'application réessaie.
 */
class SaveRelaisItem
{
    /**
     * @param  array<string, mixed>  $data  données validées (StoreRelaisItemRequest)
     */
    public function create(User $user, string $clientUuid, array $data): RelaisItem
    {
        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        $relais = new RelaisItem;
        $relais->client_uuid = $clientUuid;
        $relais->user()->associate($user);
        $relais->relaisProfile()->associate(RelaisSender::profileOf($user));
        $this->fill($relais, $data);
        $relais->save();

        return $relais;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RelaisItem $relais, array $data): RelaisItem
    {
        if (! $relais->state instanceof Uploading) {
            throw RelaisNotEditable::make();
        }

        $this->fill($relais, $data);
        $relais->save();

        return $relais;
    }

    /**
     * Réponse à un appel (MOB-23) : l'appel doit être ouvert et concerner ce Relais.
     */
    private function appelFor(RelaisItem $relais, mixed $uuid): ?int
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $appel = AppelRelais::query()->where('uuid', $uuid)->first();
        $profile = $relais->relaisProfile ?? RelaisSender::profileOf($relais->user);

        if ($appel === null || ! $appel->isOpen() || $profile === null || ! AppelAudience::targets($appel, $profile)) {
            throw new MediaRefused('appel', __('appels.errors.not_targeted'));
        }

        return $appel->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(RelaisItem $relais, array $data): void
    {
        $relais->fill(Arr::only($data, [
            'urgency', 'title', 'description', 'location_accuracy_m', 'declared_place',
            'device_captured_at', 'device_model', 'app_version',
            'has_minors', 'people_consent', 'credit_mode', 'credit_name',
        ]));

        if (array_key_exists('category', $data)) {
            $relais->category_id = is_string($data['category'])
                ? Category::query()->where('uuid', $data['category'])->value('id')
                : null;
        }

        if (array_key_exists('appel', $data)) {
            $relais->appel_relais_id = $this->appelFor($relais, $data['appel']);
        }

        if (array_key_exists('declared_commune', $data)) {
            $relais->declared_commune_id = is_string($data['declared_commune'])
                ? Commune::query()->where('uuid', $data['declared_commune'])->value('id')
                : null;
        }

        if (array_key_exists('location', $data)) {
            /** @var array{lat?: float|int, lng?: float|int}|null $location */
            $location = $data['location'];
            $relais->location = isset($location['lat'], $location['lng'])
                ? Point::makeGeodetic((float) $location['lat'], (float) $location['lng'])
                : null;
        }
    }
}
