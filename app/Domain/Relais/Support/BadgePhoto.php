<?php

namespace App\Domain\Relais\Support;

use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Models\IdentityFile;
use App\Domain\Relais\Models\RelaisProfile;

/**
 * Photo du badge (MOB-07) : photo de profil du dossier de candidature, sinon le selfie de la vérification d'identité
 * (Relais inscrits par l'application). Null s'il n'y en a aucune : le badge affiche alors l'initiale.
 */
final class BadgePhoto
{
    public static function for(RelaisProfile $profile): CandidatureDocument|IdentityFile|null
    {
        $document = $profile->candidature->documents()->where('type', DocumentType::ProfilePhoto->value)->first();

        if ($document !== null) {
            return $document;
        }

        return IdentityFile::query()
            ->whereHas('verification', fn ($query) => $query->where('user_id', $profile->user_id))
            ->whereIn('kind', [IdentityFileKind::Selfie->value, IdentityFileKind::ProfilePhoto->value])
            ->orderByRaw("case kind when 'selfie' then 0 else 1 end")
            ->latest('id')
            ->first();
    }
}
