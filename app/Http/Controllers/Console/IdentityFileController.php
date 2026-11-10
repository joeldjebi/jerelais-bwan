<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Enums\Permission;
use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\IdentityFile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation d'un fichier de vérification d'identité par l'administration (§9.4) :
 * lien signé de quelques minutes, permission vérifiée, chaque consultation inscrite au journal d'audit.
 */
class IdentityFileController extends Controller
{
    public function __invoke(Request $request, IdentityFile $file, AuditLogger $audit): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasPermissionTo(Permission::UsersManage->value), 403);

        $audit->record('identity.file.viewed', $file->verification, ['kind' => $file->kind->value], actor: $user);

        return Storage::disk($file->disk)->response($file->path, null, [
            'Content-Type' => $file->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
