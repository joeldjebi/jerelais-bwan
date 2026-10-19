<?php

namespace App\Http\Controllers\Relais;

use App\Domain\Candidature\Models\CandidatureDocument;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aperçu d'une pièce par son propriétaire (le candidat), le temps de compléter son dossier.
 * La consultation par le coordonnateur passe par une URL signée auditée (back-office).
 */
class DocumentPreviewController extends Controller
{
    public function __invoke(Request $request, CandidatureDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $document->candidature->user_id === $user->id, 404);

        return Storage::disk($document->disk)->response($document->path, null, [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
