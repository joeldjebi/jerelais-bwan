<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

trait DescribesDevice
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function deviceRules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:100'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function deviceInfo(): DeviceInfo
    {
        return new DeviceInfo(
            name: $this->string('device_name')->toString(),
            platform: $this->enum('platform', DevicePlatform::class) ?? DevicePlatform::Android,
            appVersion: $this->filled('app_version') ? $this->string('app_version')->toString() : null,
        );
    }
}
