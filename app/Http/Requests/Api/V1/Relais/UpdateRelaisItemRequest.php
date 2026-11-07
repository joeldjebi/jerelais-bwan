<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Models\RelaisItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'un relais pas encore envoyé (brouillon, MOB-19).
 */
class UpdateRelaisItemRequest extends FormRequest
{
    use RelaisItemRules;

    public function authorize(): bool
    {
        $relais = $this->route('relais');

        return $this->user() instanceof User && $relais instanceof RelaisItem && $this->user()->can('view', $relais);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->relaisRules();
    }
}
