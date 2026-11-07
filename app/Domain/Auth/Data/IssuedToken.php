<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Models\Device;
use App\Models\User;

final readonly class IssuedToken
{
    public function __construct(
        public User $user,
        public Device $device,
        public string $plainTextToken,
        public bool $isNewUser = false,
    ) {}
}
