<?php

namespace App\Domain\Auth\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $phone_hash
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 */
#[Fillable(['phone_hash', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip_address'])]
class OtpCode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<OtpCode>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public static function hashCode(string $phoneHash, string $code): string
    {
        return hash_hmac('sha256', $phoneHash.'|'.$code, (string) config('app.key'));
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, self::hashCode($this->phone_hash, $code));
    }
}
