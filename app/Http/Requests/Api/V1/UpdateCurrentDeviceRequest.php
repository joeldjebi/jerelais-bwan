<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrentDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fcm_token' => ['nullable', 'string', 'max:4096'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
