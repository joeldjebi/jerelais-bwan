<?php

namespace App\Console\Commands;

use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Models\Device;
use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use App\Domain\Notification\Push\FcmPushGateway;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('push:test {phone : Numéro du compte, ex. +2250700000000} {message? : Texte de la notification}')]
#[Description('Envoie une notification push de test sur les appareils connectés d’un compte et affiche la réponse de Firebase (jetons jamais affichés)')]
class PushTestCommand extends Command
{
    public function handle(PushGateway $gateway): int
    {
        $phone = PhoneNumber::tryParse(is_string($this->argument('phone')) ? $this->argument('phone') : '');
        $user = $phone === null ? null : User::findByPhone($phone);

        if ($user === null) {
            $this->components->error('Aucun compte avec ce numéro.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Passerelle', (string) config('jerelais.push.driver'));

        if ($gateway instanceof FcmPushGateway) {
            $this->components->twoColumnDetail('Projet Firebase', $gateway->projectId() ?? '— clé absente ou invalide');
        }

        $devices = $user->devices()->whereNull('revoked_at')->whereNotNull('fcm_token')->get();

        if ($devices->isEmpty()) {
            $this->components->error('Ce compte n’a aucun appareil connecté avec un jeton de notifications (PUT /api/v1/devices/current, champ fcm_token).');

            return self::FAILURE;
        }

        $body = is_string($this->argument('message')) ? $this->argument('message') : __('notifications.test.body');
        $message = new PushMessage(__('notifications.test.title'), $body, ['type' => 'test']);
        $delivered = 0;

        foreach ($devices as $device) {
            /** @var Device $device */
            $detail = $gateway instanceof FcmPushGateway
                ? $gateway->sendDetailed((string) $device->fcm_token, $message)
                : ['result' => $gateway->send((string) $device->fcm_token, $message), 'http_status' => null, 'error' => null];

            if ($detail['result'] === PushResult::InvalidToken) {
                $device->forceFill(['fcm_token' => null])->save();
            }

            $delivered += $detail['result'] === PushResult::Sent ? 1 : 0;
            $this->components->twoColumnDetail(
                $device->name.' ('.$device->platform->value.')',
                $detail['result']->value.($detail['http_status'] !== null ? ' · HTTP '.$detail['http_status'] : '').($detail['error'] !== null ? ' · '.$detail['error'] : ''),
            );
        }

        if ($delivered === 0) {
            $this->components->error('Aucune notification envoyée.');

            return self::FAILURE;
        }

        $this->components->info($delivered.' notification(s) acceptée(s) par le service de notifications.');

        return self::SUCCESS;
    }
}
