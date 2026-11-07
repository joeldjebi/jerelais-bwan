<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Enums\DevicePlatform;

final readonly class DeviceInfo
{
    public function __construct(
        public string $name,
        public DevicePlatform $platform,
        public ?string $appVersion = null,
    ) {}
}
