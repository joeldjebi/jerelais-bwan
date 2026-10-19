<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    use ValidatesPhoneNumber;

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
            'phone' => $this->phoneRules(),
        ];
    }
}
