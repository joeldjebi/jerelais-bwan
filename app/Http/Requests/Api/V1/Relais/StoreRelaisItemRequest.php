<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Envoi d'un relais en un seul appel par l'application (MOB-10 à MOB-19), identifié par son « client_uuid » :
 * contenu complet (catégorie, titre, lieu, protections) et liste des fichiers à envoyer. Pas de brouillon.
 */
class StoreRelaisItemRequest extends FormRequest
{
    use RelaisItemRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && RelaisSender::canSend($user);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->relaisRules();
        $required = fn (string $field): array => ['required', ...array_values(array_filter($rules[$field], fn (mixed $rule): bool => $rule !== 'nullable'))];

        return [
            'client_uuid' => ['required', 'uuid'],
            ...$rules,
            'category' => $required('category'),
            'title' => $required('title'),
            'location' => ['required_without:declared_commune', ...array_values(array_filter($rules['location'], fn (mixed $rule): bool => $rule !== 'nullable')), 'nullable'],
            'protections' => ['required', 'array:has_minors,people_consent,credit_mode,credit_name'],
            'protections.has_minors' => ['required', 'boolean'],
            'protections.people_consent' => $required('protections.people_consent'),
            'protections.credit_mode' => $required('protections.credit_mode'),
            'protections.credit_name' => ['nullable', 'required_if:protections.credit_mode,'.CreditMode::Pseudonym->value, 'string', 'max:80'],
            'medias' => ['required', 'array', 'min:1', 'max:'.app(Settings::class)->integer('media.max_medias_per_relais')],
            'medias.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'medias.*.kind' => ['required', Rule::enum(MediaKind::class)],
            'medias.*.mime' => ['required', 'string', 'max:100'],
            'medias.*.size' => ['required', 'integer', 'min:1'],
            'medias.*.sha256' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'medias.*.filename' => ['nullable', 'string', 'max:255'],
            'medias.*.is_imported' => ['nullable', 'boolean'],
            'medias.*.device_captured_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
        ];
    }

    /**
     * @return list<array{client_uuid: string, kind: string, mime: string, size: int, sha256: string, filename?: string|null, is_imported?: bool, device_captured_at?: string|null}>
     */
    public function medias(): array
    {
        /** @var list<array{client_uuid: string, kind: string, mime: string, size: int, sha256: string, filename?: string|null, is_imported?: bool, device_captured_at?: string|null}> $medias */
        $medias = array_values($this->validated('medias', []));

        return $medias;
    }

    protected function failedAuthorization(): never
    {
        abort(403, __('relais_items.errors.not_relais'));
    }
}
