<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\PinPolicy;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Inscription, dernière étape (multipart) : numéro vérifié, identité (règles de l'étape « identité » de la candidature,
 * ici obligatoires), type de pièce et ses faces (recto et verso, ou une page ; image ou PDF), code PIN confirmé.
 */
class CompleteRegistrationRequest extends FormRequest
{
    use ValidatesPhoneNumber;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = app(StepRules::class)->for(CandidatureStep::Identite);
        $required = fn (string $field): array => ['required', ...array_values(array_filter($identity[$field], fn (mixed $rule): bool => $rule !== 'nullable'))];
        $sides = $this->documentType()?->sides() ?? [];
        $file = fn (IdentityFileKind $kind): array => [
            Rule::requiredIf(in_array($kind, $sides, true)),
            Rule::excludeIf(! in_array($kind, $sides, true)),
            'file',
            'max:'.(int) config('jerelais.candidature.document_max_kb'),
            'mimetypes:'.implode(',', $kind->mimeTypes()),
        ];

        return [
            'phone' => $this->phoneRules(),
            'last_name' => $required('last_name'),
            'first_names' => $required('first_names'),
            'gender' => $required('gender'),
            'birth_date' => $required('birth_date'),
            'document_type' => ['required', Rule::enum(IdentityDocumentType::class)],
            'recto' => $file(IdentityFileKind::Recto),
            'verso' => $file(IdentityFileKind::Verso),
            'page' => $file(IdentityFileKind::Page),
            'password' => ['required', 'string', 'digits:'.PinPolicy::length(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.digits' => __('auth.pin_format', ['length' => PinPolicy::length()]),
            'password.confirmed' => __('auth.pin_confirmation'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'last_name' => __('nom'),
            'first_names' => __('prénoms'),
            'gender' => __('sexe'),
            'birth_date' => __('date de naissance'),
            'document_type' => __('type de pièce'),
            'recto' => __('recto de la pièce'),
            'verso' => __('verso de la pièce'),
            'page' => __('page de la pièce'),
            'password' => __('code PIN'),
        ];
    }

    /**
     * @return array{last_name: string, first_names: string, gender: string, birth_date: string}
     */
    public function identity(): array
    {
        return [
            'last_name' => $this->string('last_name')->trim()->toString(),
            'first_names' => $this->string('first_names')->trim()->toString(),
            'gender' => $this->string('gender')->toString(),
            'birth_date' => $this->string('birth_date')->toString(),
        ];
    }

    public function documentType(): ?IdentityDocumentType
    {
        return IdentityDocumentType::tryFrom($this->string('document_type')->toString());
    }

    /**
     * Faces de la pièce envoyées, par type de fichier.
     *
     * @return array<string, UploadedFile>
     */
    public function documentFiles(): array
    {
        $files = [];

        foreach ($this->documentType()?->sides() ?? [] as $side) {
            $file = $this->file($side->value);

            if ($file instanceof UploadedFile) {
                $files[$side->value] = $file;
            }
        }

        return $files;
    }
}
