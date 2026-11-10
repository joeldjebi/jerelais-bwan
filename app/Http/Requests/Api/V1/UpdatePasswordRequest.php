<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Auth\Support\PinPolicy;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Définition ou changement du code PIN de l'application (MOB-01).
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
        $hasPin = fn (): bool => $this->user()?->password !== null;
        $resetAllowed = fn (): bool => PinPolicy::allowsResetWithoutCurrent($this->user()?->currentAccessToken());

        return [
            // PIN actuel : obligatoire pour changer un PIN existant, sauf juste après une connexion par code SMS (PIN oublié) ; interdit pour le premier.
            'current_password' => ['nullable', 'string', Rule::excludeIf($resetAllowed), Rule::requiredIf($hasPin), Rule::prohibitedIf(fn (): bool => ! $hasPin()), 'current_password:sanctum'],
            // Nouveau code PIN de 4 chiffres, à confirmer dans `password_confirmation`.
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
}
