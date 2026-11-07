<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Support\RelaisSender;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un relais par l'application (MOB-10 à MOB-15), identifié par son « client_uuid ».
 */
class StoreRelaisItemRequest extends FormRequest
{
    use RelaisItemRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && RelaisSender::canSend($user);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['client_uuid' => ['required', 'uuid'], ...$this->relaisRules()];
    }

    protected function failedAuthorization(): never
    {
        abort(403, __('relais_items.errors.not_relais'));
    }
}
