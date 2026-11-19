<?php

namespace App\Http\Requests\Api\V1\Gains;

use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Models\PayoutAccount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Coordonnées Mobile Money du Relais (MOB-26) : opérateur, numéro et nom du titulaire.
 */
class UpdatePayoutAccountRequest extends FormRequest
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
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PayoutAccount::methods()))],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (PhoneNumber::tryParse(is_string($value) ? $value : null) === null) {
                    $fail(__('gains.errors.phone_invalid'));
                }
            }],
            'holder_name' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['method.in' => __('gains.errors.method_unknown')];
    }

    public function phone(): PhoneNumber
    {
        return PhoneNumber::parse($this->string('phone')->toString());
    }
}
