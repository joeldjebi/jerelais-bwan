<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mot de passe oublié, étape 3 : nouveau mot de passe, avec le jeton obtenu par le code SMS (POST /auth/verify).
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PasswordPolicy::allowsResetWithoutCurrent($this->user()?->currentAccessToken());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Nouveau mot de passe, au moins 8 caractères, à confirmer dans `password_confirmation`.
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

    protected function failedAuthorization(): never
    {
        abort(403, __('auth.reset_requires_code'));
    }
}
