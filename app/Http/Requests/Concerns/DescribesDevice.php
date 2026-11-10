<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

/**
 * Description facultative de l'appareil qui se connecte (un jeton par appareil, MOB-31).
 * Absente, elle est déduite de l'en-tête User-Agent ; l'application peut la préciser ensuite avec PUT /devices/current.
 */
trait DescribesDevice
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function deviceRules(): array
    {
        return [
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', Rule::enum(DevicePlatform::class)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function deviceInfo(): DeviceInfo
    {
        $userAgent = (string) $this->userAgent();
        $guessedPlatform = preg_match('/iphone|ipad|ios|darwin/i', $userAgent) === 1 ? DevicePlatform::Ios : DevicePlatform::Android;

        return new DeviceInfo(
            name: $this->filled('device_name') ? $this->string('device_name')->toString() : __('Appareil mobile'),
            platform: $this->enum('platform', DevicePlatform::class) ?? $guessedPlatform,
            appVersion: $this->filled('app_version') ? $this->string('app_version')->toString() : null,
        );
    }
}
