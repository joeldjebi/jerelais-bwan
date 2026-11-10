<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\PinPolicy;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Support\StepRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription, étape 2 : identité (règles de l'étape « identité » de la candidature, ici obligatoires) et code PIN.
 */
class CompleteRegistrationRequest extends FormRequest
{
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

        return [
            'registration_token' => ['required', 'string', 'size:64'],
            'last_name' => $required('last_name'),
            'first_names' => $required('first_names'),
            'gender' => $required('gender'),
            'birth_date' => $required('birth_date'),
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
}
