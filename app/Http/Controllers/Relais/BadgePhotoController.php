<?php

namespace App\Http\Controllers\Relais;

use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Relais\Models\RelaisProfile;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo du badge, par lien signé de courte durée délivré au seul Relais concerné (application et site).
 */
class BadgePhotoController extends Controller
{
    public function __invoke(RelaisProfile $profile): StreamedResponse
    {
        $profile = RelaisProfile::withoutCommuneRestriction()->whereKey($profile->getKey())->firstOrFail();
        $photo = $profile->candidature->documents()->where('type', DocumentType::ProfilePhoto->value)->firstOrFail();

        return Storage::disk($photo->disk)->response($photo->path, null, [
            'Content-Type' => $photo->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
