<?php

namespace App\Http\Controllers\Console;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export des données d'un compte pour répondre à une demande d'accès (§9.4), tracé dans l'audit.
 */
class PrivacyExportController extends Controller
{
    public function __invoke(Request $request, User $user, ExportPersonalData $export, AuditLogger $audit): Response
    {
        $audit->record('privacy.exported', $user, ['channel' => 'console']);

        return response()->json($export->handle($user), 200, [
            'Content-Disposition' => 'attachment; filename="donnees-'.$user->uuid.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
