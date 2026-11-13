<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\Urgency;
use Illuminate\Validation\Rule;

/**
 * Règles communes à la création et à la modification d'un relais (MOB-13 à MOB-15).
 * Tous les champs sont facultatifs avant l'envoi : la complétude est contrôlée à l'envoi.
 */
trait RelaisItemRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function relaisRules(): array
    {
        return [
            'appel' => ['bail', 'nullable', 'uuid', Rule::exists('appels_relais', 'uuid')],
            'category' => ['bail', 'nullable', 'uuid', Rule::exists('categories', 'uuid')->where('is_active', true)],
            'urgency' => ['nullable', Rule::enum(Urgency::class)],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'array:lat,lng,accuracy_m'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'declared_commune' => ['bail', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
            'declared_place' => ['nullable', 'string', 'max:200'],
            'device_captured_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'protections' => ['nullable', 'array:has_minors,people_consent,credit_mode,credit_name'],
            'protections.has_minors' => ['nullable', 'boolean'],
            'protections.people_consent' => ['nullable', Rule::enum(PeopleConsent::class)],
            'protections.credit_mode' => ['nullable', Rule::enum(CreditMode::class)],
            'protections.credit_name' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Envoi multipart (champs à plat) : « lat », « lng », « accuracy_m » deviennent « location », et « has_minors »,
     * « people_consent », « credit_mode », « credit_name » deviennent « protections ». Les booléens envoyés en texte
     * (« true », « false », « oui », « non ») sont convertis. Les champs vides sont ignorés.
     */
    protected function normalizeFlatFields(): void
    {
        // Emplacements de fichiers ou d'uuid laissés vides (« files[2] » vide) : ignorés.
        foreach (['files', 'remove_medias'] as $list) {
            $raw = $this->request->all()[$list] ?? null;

            if (is_array($raw)) {
                $kept = array_filter($raw, fn (mixed $value): bool => $value !== null && $value !== '');
                $kept === [] ? $this->request->remove($list) : $this->request->set($list, array_values($kept));
            } elseif ($raw === '' || $raw === null) {
                $this->request->remove($list);
            }
        }

        $location = [];

        foreach (['lat' => 'lat', 'lng' => 'lng', 'accuracy_m' => 'accuracy_m'] as $flat => $key) {
            if ($this->filled($flat)) {
                $location[$key] = $this->input($flat);
            }
        }

        if ($location !== [] && ! $this->filled('location')) {
            $this->merge(['location' => $location]);
        }

        $protections = is_array($this->input('protections')) ? $this->input('protections') : [];

        foreach (['has_minors', 'people_consent', 'credit_mode', 'credit_name'] as $field) {
            if ($this->filled($field) && ! array_key_exists($field, $protections)) {
                $protections[$field] = $this->input($field);
            }
        }

        if (array_key_exists('has_minors', $protections) && is_string($protections['has_minors'])) {
            $protections['has_minors'] = match (strtolower(trim($protections['has_minors']))) {
                'true', 'oui', 'yes', '1' => true,
                'false', 'non', 'no', '0' => false,
                default => $protections['has_minors'],
            };
        }

        if ($protections !== []) {
            $this->merge(['protections' => $protections]);
        }
    }

    /**
     * Données à plat pour l'action SaveRelaisItem.
     *
     * @return array<string, mixed>
     */
    public function relaisData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $protections = $data['protections'] ?? [];
        unset($data['protections'], $data['client_uuid'], $data['medias'], $data['files'], $data['remove_medias'], $data['message']);

        if (isset($data['location']) && is_array($data['location'])) {
            $data['location_accuracy_m'] = $data['location']['accuracy_m'] ?? null;
        }

        // Champ envoyé vide : non renseigné (la valeur par défaut s'applique, ex. urgence « normal »).
        return array_filter([...$data, ...(is_array($protections) ? $protections : [])], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
