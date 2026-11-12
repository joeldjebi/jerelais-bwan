<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\PasswordPolicy;
use App\Http\Requests\Concerns\DescribesIdentity;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription, dernière étape : le numéro vérifié et le mot de passe (confirmé), plus, si l'application les demande,
 * le nom, les prénoms, le genre et la date de naissance (JJ/MM/AAAA). Ces quatre champs sont facultatifs.
 */
class CompleteRegistrationRequest extends FormRequest
{
    use DescribesIdentity, ValidatesPhoneNumber;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBirthDate();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'password' => PasswordPolicy::rules(),
            ...$this->identityRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...PasswordPolicy::messages(),
            'birth_date.date_format' => __('La date de naissance doit être au format JJ/MM/AAAA, par exemple 26/04/2000.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => __('mot de passe'), ...$this->identityAttributes()];
    }
}
