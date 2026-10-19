<?php

namespace App\Http\Requests\Api\V1\Relais;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaKind;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Annonce d'un média avant son envoi en morceaux (MOB-11, MOB-12, MOB-16).
 */
class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $relais = $this->route('relais');

        return $this->user() instanceof User && $relais instanceof RelaisItem && $relais->user_id === $this->user()->id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(MediaKind::class)],
            'mime' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1'],
            'sha256' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'filename' => ['nullable', 'string', 'max:255'],
            'is_imported' => ['nullable', 'boolean'],
            'device_captured_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
        ];
    }
}
