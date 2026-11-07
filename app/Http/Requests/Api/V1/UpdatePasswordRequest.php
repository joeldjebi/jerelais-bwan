<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Définition ou changement du mot de passe de connexion de l'application (MOB-01).
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

        return [
            // Mot de passe actuel : obligatoire pour changer un mot de passe existant, interdit pour le premier.
            'current_password' => ['nullable', 'string', Rule::requiredIf($hasPassword), Rule::prohibitedIf(fn (): bool => ! $hasPassword()), 'current_password:sanctum'],
            // Nouveau mot de passe, 10 caractères minimum, à confirmer dans `password_confirmation`.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
