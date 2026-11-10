<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Auth\Support\OtpPolicy;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits_between:'.implode(',', OtpPolicy::acceptedLengths())],
        ];
    }
}
