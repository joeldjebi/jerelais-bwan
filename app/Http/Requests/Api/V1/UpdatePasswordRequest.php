<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
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
        /** @var User $user */
        $user = $this->user();

        return [
            'current_password' => $user->password !== null ? ['required', 'string', 'current_password:sanctum'] : ['prohibited'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
