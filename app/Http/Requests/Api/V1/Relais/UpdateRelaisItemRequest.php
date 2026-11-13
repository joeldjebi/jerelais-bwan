<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Media\Actions\StoreDirectMedia;
use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * PUT /relais/{relais} : modifier un relais envoyé (MOB-19). Tout est facultatif ; seuls les éléments envoyés et non
 * vides changent. Fichiers ajoutés dans `files[]`, fichiers retirés dans `remove_medias[]` (uuid). Multipart ou JSON.
 */
class UpdateRelaisItemRequest extends FormRequest
{
    use RelaisItemRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeFlatFields();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->relaisRules(),
            'protections.credit_name' => ['nullable', 'string', 'max:80'],
            'files' => ['nullable', 'array', 'max:'.app(Settings::class)->integer('media.max_medias_per_relais')],
            'files.*' => $this->fileRules(),
            'remove_medias' => ['nullable', 'array'],
            'remove_medias.*' => ['uuid'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->referenceMessages(),
            'files.*.max' => __('media.too_large_direct', ['max' => app(StoreDirectMedia::class)->maxMegabytes()]),
            'files.*.uploaded' => __('media.too_large_direct', ['max' => app(StoreDirectMedia::class)->maxMegabytes()]),
        ];
    }

    /**
     * Requête vide : on le signale au lieu de répondre sans rien changer ; pseudonyme exigé pour une mention en pseudonyme.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->changes() === [] && $this->attachedFiles() === [] && $this->removedMedias() === [] && ! $this->filled('message')) {
                $validator->errors()->add('relais', __('relais_items.errors.nothing_to_update'));
            }

            $protections = $this->input('protections');

            if (is_array($protections) && ($protections['credit_mode'] ?? null) === CreditMode::Pseudonym->value && blank($protections['credit_name'] ?? null)) {
                $validator->errors()->add('protections.credit_name', __('validation.custom.protections.credit_name.required_if'));
            }
        }];
    }

    /**
     * Champs du relais envoyés et non vides (les autres sont conservés).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        foreach (array_keys($this->relaisRules()) as $field) {
            if (str_contains($field, '.') || in_array($field, ['location', 'protections'], true)) {
                continue;
            }

            if ($this->filled($field)) {
                $changes[$field] = $this->input($field);
            }
        }

        $location = $this->input('location');

        if (is_array($location) && isset($location['lat'], $location['lng'])) {
            $changes['location'] = $location;
            $changes['location_accuracy_m'] = $location['accuracy_m'] ?? null;
        }

        $protections = $this->input('protections');

        foreach (['has_minors', 'people_consent', 'credit_mode', 'credit_name'] as $field) {
            if (is_array($protections) && array_key_exists($field, $protections) && $protections[$field] !== null && $protections[$field] !== '') {
                $changes[$field] = $protections[$field];
            }
        }

        return $changes;
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachedFiles(): array
    {
        $files = $this->file('files');

        return array_values(is_array($files) ? $files : ($files === null ? [] : [$files]));
    }

    /**
     * @return list<string>
     */
    public function removedMedias(): array
    {
        $uuids = $this->input('remove_medias');
        $uuids = is_string($uuids) ? [$uuids] : (is_array($uuids) ? $uuids : []);

        return array_values(array_filter($uuids, fn (mixed $uuid): bool => is_string($uuid) && $uuid !== ''));
    }
}
