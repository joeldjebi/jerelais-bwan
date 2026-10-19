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
     * Données à plat pour l'action SaveRelaisItem.
     *
     * @return array<string, mixed>
     */
    public function relaisData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $protections = $data['protections'] ?? [];
        unset($data['protections'], $data['client_uuid']);

        if (isset($data['location']) && is_array($data['location'])) {
            $data['location_accuracy_m'] = $data['location']['accuracy_m'] ?? null;
        }

        return [...$data, ...(is_array($protections) ? $protections : [])];
    }
}
