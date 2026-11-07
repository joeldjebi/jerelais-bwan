<?php

namespace App\Console\Commands;

use App\Domain\Auth\Actions\IssueDeviceToken;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('jerelais:load-test-accounts {count=50 : Nombre de Relais de test} {--commune= : Slug de la commune (première commune ouverte par défaut)}')]
#[Description('Crée des Relais de test et leurs jetons pour le test de charge k6 (jamais en production)')]
class LoadTestAccountsCommand extends Command
{
    public function handle(IssueDeviceToken $issueToken): int
    {
        if (app()->environment('production')) {
            $this->components->error('Commande interdite en production.');

            return self::FAILURE;
        }

        $commune = Commune::query()->active()
            ->when($this->option('commune'), fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->first();

        if ($commune === null) {
            $this->components->error('Aucune commune ouverte : ouvrez-en une depuis la console.');

            return self::FAILURE;
        }

        $tokens = [];

        foreach (RelaisProfile::factory()->count((int) $this->argument('count'))->create(['commune_id' => $commune->id]) as $profile) {
            $tokens[] = $issueToken->handle($profile->user, new DeviceInfo('k6', DevicePlatform::Android, '1.0.0'), 'otp')->plainTextToken;
        }

        Storage::disk('local')->put('k6/tokens.json', (string) json_encode(['commune' => $commune->uuid, 'tokens' => $tokens]));

        $this->components->info(count($tokens).' Relais de test créés à '.$commune->name.'. Jetons : '.Storage::disk('local')->path('k6/tokens.json'));

        return self::SUCCESS;
    }
}
