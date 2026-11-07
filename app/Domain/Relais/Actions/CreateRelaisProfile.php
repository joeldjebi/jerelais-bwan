<?php

namespace App\Domain\Relais\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Support\Str;

/**
 * Crée le profil du Relais à l'acceptation de sa candidature : numéro unique, badge vérifiable.
 * Sans effet si le profil existe déjà.
 */
class CreateRelaisProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Candidature $candidature): RelaisProfile
    {
        $existing = RelaisProfile::withoutCommuneRestriction()->where('user_id', $candidature->user_id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $profile = new RelaisProfile([
            'commune_id' => $candidature->commune_id,
            'localite_id' => $candidature->localite_id,
            'display_name' => $this->displayName($candidature),
            'status' => RelaisStatus::Active,
            'activated_at' => now(),
        ]);
        $profile->user()->associate($candidature->user_id);
        $profile->candidature()->associate($candidature);
        $profile->badge_token = Str::random(40);
        $profile->save();

        $profile->forceFill(['number' => 'JR-'.str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT)])->save();

        $this->audit->record('relais.created', $profile, ['number' => $profile->number], communeId: $profile->commune_id);

        return $profile;
    }

    /**
     * Prénom et initiale du nom : suffisant pour reconnaître le Relais sans exposer son identité complète.
     */
    private function displayName(Candidature $candidature): string
    {
        $firstName = Str::of((string) $candidature->first_names)->trim()->explode(' ')->first() ?: '';
        $initial = Str::upper(Str::substr(trim((string) $candidature->last_name), 0, 1));

        return trim($firstName.($initial !== '' ? ' '.$initial.'.' : '')) ?: __('Relais');
    }
}
