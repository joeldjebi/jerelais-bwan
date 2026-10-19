<?php

namespace App\Http\Requests\Api\V1\Formation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réponses au quiz : { "answers": { "<uuid question>": "<uuid réponse>" } }.
 */
class StoreQuizAttemptRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1', 'max:100'],
            'answers.*' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function answers(): array
    {
        /** @var array<string, string> $answers */
        $answers = $this->validated('answers');

        return $answers;
    }
}
