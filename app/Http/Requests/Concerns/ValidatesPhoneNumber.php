<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Auth\Data\PhoneNumber;
use Closure;

trait ValidatesPhoneNumber
{
    /**
     * @return list<mixed>
     */
    protected function phoneRules(): array
    {
        return [
            'required',
            'string',
            'max:25',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || PhoneNumber::tryParse($value) === null) {
                    $fail(__('validation.phone'));
                }
            },
        ];
    }

    public function phoneNumber(): PhoneNumber
    {
        return PhoneNumber::parse($this->string('phone')->toString());
    }
}
