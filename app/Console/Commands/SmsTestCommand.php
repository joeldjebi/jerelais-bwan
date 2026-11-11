<?php

namespace App\Console\Commands;

use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Domain\Auth\Sms\MtargetSmsGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:test {msisdn : Numéro au format international, ex. +2250700000000} {message? : Texte du SMS}')]
#[Description('Envoie un SMS de test par MTarget et affiche la réponse brute du fournisseur (identifiants jamais affichés)')]
class SmsTestCommand extends Command
{
    public function handle(): int
    {
        $msisdn = is_string($this->argument('msisdn')) ? $this->argument('msisdn') : '';
        $message = is_string($this->argument('message')) ? $this->argument('message') : 'JE RELAIS : SMS de test.';

        try {
            $result = MtargetSmsGateway::fromConfig()->sendDetailed($message, $msisdn);
        } catch (SmsNotSent $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $first = $result['results'][0] ?? [];
        $accepted = $result['http_status'] === 200 && (string) ($first['code'] ?? '') === '0' && strtoupper((string) ($first['reason'] ?? '')) === 'ACCEPTED';

        $this->components->twoColumnDetail('Numéro', $result['msisdn']);
        $this->components->twoColumnDetail('Expéditeur', $result['sender']);
        $this->components->twoColumnDetail('Statut HTTP', (string) $result['http_status']);
        $this->components->twoColumnDetail('Réponse', $result['raw_body']);

        if (! $accepted) {
            $this->components->error('MTarget n’a pas accepté le SMS.');

            return self::FAILURE;
        }

        $this->components->info('SMS accepté par MTarget (la livraison se confirme par les accusés de réception).');

        return self::SUCCESS;
    }
}
