<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Actions\StoreDirectMedia;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Envoi d'un relais par l'application (MOB-10 à MOB-19), sans brouillon. Deux façons :
 *
 * - **en une seule requête** (multipart/form-data) : contenu et fichiers (`files[]`, 100 Mo maximum chacun) ;
 * - **en morceaux** (JSON) : contenu et liste des fichiers (`medias`), puis envoi reprenable de chaque fichier
 *   (vidéos lourdes, réseau instable).
 */
class StoreRelaisItemRequest extends FormRequest
{
    use RelaisItemRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && RelaisSender::canSend($user);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeFlatFields();
    }

    /**
     * Envoi en une seule requête : des fichiers sont joints.
     */
    public function hasDirectFiles(): bool
    {
        return $this->attachedFiles() !== [];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->relaisRules();
        $required = fn (string $field): array => ['required', ...array_values(array_filter($rules[$field], fn (mixed $rule): bool => $rule !== 'nullable'))];
        $maxMedias = app(Settings::class)->integer('media.max_medias_per_relais');

        $content = [
            ...$rules,
            'category' => $required('category'),
            'title' => $required('title'),
            'location' => ['required_without:declared_commune', ...array_values(array_filter($rules['location'], fn (mixed $rule): bool => $rule !== 'nullable')), 'nullable'],
            'protections' => ['required', 'array:has_minors,people_consent,credit_mode,credit_name'],
            'protections.has_minors' => ['required', 'boolean'],
            'protections.people_consent' => $required('protections.people_consent'),
            'protections.credit_mode' => $required('protections.credit_mode'),
            'protections.credit_name' => ['nullable', 'required_if:protections.credit_mode,'.CreditMode::Pseudonym->value, 'string', 'max:80'],
        ];

        if ($this->hasDirectFiles() || ! $this->has('medias')) {
            return [
                'client_uuid' => ['nullable', 'uuid'],
                ...$content,
                'files' => ['required', 'array', 'min:1', 'max:'.$maxMedias],
                'files.*' => ['file', 'max:'.(app(StoreDirectMedia::class)->maxMegabytes() * 1024), 'mimetypes:'.implode(',', MediaKind::allMimeTypes())],
            ];
        }

        return [
            'client_uuid' => ['required', 'uuid'],
            ...$content,
            'medias' => ['required', 'array', 'min:1', 'max:'.$maxMedias],
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->referenceMessages(),
            'files.required' => __('media.none'),
            'files.*.max' => __('media.too_large_direct', ['max' => app(StoreDirectMedia::class)->maxMegabytes()]),
            'files.*.mimetypes' => __('media.unsupported_type'),
            'files.*.uploaded' => __('media.too_large_direct', ['max' => app(StoreDirectMedia::class)->maxMegabytes()]),
        ];
    }

    /**
     * Identifiant d'idempotence : celui de l'application, sinon un nouveau.
     */
    public function clientUuid(): string
    {
        return $this->filled('client_uuid') ? $this->string('client_uuid')->toString() : (string) Str::uuid7();
    }

    /**
     * Fichiers joints (envoi en une seule requête).
     *
     * @return list<UploadedFile>
     */
    public function attachedFiles(): array
    {
        $files = $this->file('files');

        return array_values(is_array($files) ? $files : ($files === null ? [] : [$files]));
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
