<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Auth\Support\PasswordPolicy;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Définition ou changement du mot de passe de l'application (MOB-01).
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $hasPassword = fn (): bool => $this->user()?->password !== null;
        $resetAllowed = fn (): bool => PasswordPolicy::allowsResetWithoutCurrent($this->user()?->currentAccessToken());

        return [
            // Mot de passe actuel : obligatoire pour en changer, sauf juste après une connexion par code SMS (oubli) ; interdit pour le premier.
            'current_password' => ['nullable', 'string', Rule::excludeIf($resetAllowed), Rule::requiredIf($hasPassword), Rule::prohibitedIf(fn (): bool => ! $hasPassword()), 'current_password:sanctum'],
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
}
