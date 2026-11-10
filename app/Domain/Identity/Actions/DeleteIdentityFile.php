<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use Illuminate\Support\Facades\Storage;

/**
 * Retire un fichier de la vérification d'identité tant qu'elle est modifiable.
 */
class DeleteIdentityFile
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(IdentityVerification $verification, IdentityFileKind $kind): void
    {
        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        $file = $verification->files()->where('kind', $kind->value)->first();

        if ($file === null) {
            return;
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        $this->audit->record('identity.file.deleted', $verification, ['kind' => $kind->value], actor: $verification->user);
    }
}
