<?php

namespace App\Http\Controllers\Relais;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Téléchargement de mes données depuis le site (droit d'accès, §9.4).
 */
class DataDownloadController extends Controller
{
    public function __invoke(Request $request, ExportPersonalData $export, AuditLogger $audit): Response
    {
        /** @var User $user */
        $user = $request->user();
        $audit->record('privacy.exported', $user, ['channel' => 'web'], actor: $user);

        return response()->json($export->handle($user), 200, [
            'Content-Disposition' => 'attachment; filename="mes-donnees-je-relais.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
