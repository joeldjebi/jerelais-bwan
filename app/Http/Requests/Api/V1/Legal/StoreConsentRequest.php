<?php

namespace App\Http\Requests\Api\V1\Legal;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Acceptation des textes en vigueur (MOB-04) : la liste des versions lues est envoyée explicitement.
 */
class StoreConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function documentUuids(): array
    {
        /** @var list<string> $uuids */
        $uuids = array_values($this->validated('documents'));

        return $uuids;
    }
}
