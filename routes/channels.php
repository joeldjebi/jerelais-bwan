<?php

use App\Domain\Contribution\Support\RelaisChannels;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

/*
 * Temps réel des relais (§8.2) : la console nationale, et le canal de chaque commune pour ses coordonnateurs.
 */
Broadcast::channel('console.relais', fn (User $user): bool => RelaisChannels::console($user));
Broadcast::channel('communes.{communeId}.relais', fn (User $user, int $communeId): bool => RelaisChannels::commune($user, $communeId));
