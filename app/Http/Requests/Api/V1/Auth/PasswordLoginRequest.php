<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\DescribesDevice;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class PasswordLoginRequest extends FormRequest
{
    use DescribesDevice, ValidatesPhoneNumber;

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
            'password' => ['required', 'string', 'max:200'],
            ...$this->deviceRules(),
        ];
    }
}
