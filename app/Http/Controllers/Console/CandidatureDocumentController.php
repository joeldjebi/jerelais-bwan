<?php

namespace App\Http\Controllers\Console;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation d'une pièce par le coordonnateur ou un administrateur habilité (§9.4) :
 * lien signé de quelques minutes, autorisation vérifiée, chaque consultation inscrite au journal d'audit.
 */
class CandidatureDocumentController extends Controller
{
    public function __invoke(Request $request, CandidatureDocument $document, AuditLogger $audit): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $candidature = Candidature::query()->find($document->candidature_id);
        abort_if($candidature === null, 404);
        Gate::forUser($user)->authorize('viewIdentity', $candidature);

        $audit->record('candidature.document.viewed', $candidature, ['type' => $document->type->value], actor: $user, communeId: $candidature->commune_id);

        return Storage::disk($document->disk)->response($document->path, null, [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
