<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\PasswordPolicy;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription, dernière étape : le numéro vérifié et le mot de passe (confirmé). Rien d'autre.
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
        return [
            'phone' => $this->phoneRules(),
            'password' => PasswordPolicy::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PasswordPolicy::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => __('mot de passe')];
    }
}
