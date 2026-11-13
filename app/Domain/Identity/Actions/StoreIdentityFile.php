<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityFile;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Exceptions\InfectedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enregistre un fichier de la vérification d'identité (MOB-01) : type contrôlé sur le contenu, antivirus avant
 * stockage, empreinte SHA-256, nom aléatoire, disque privé « identity ». Un nouveau fichier remplace le précédent.
 */
class StoreIdentityFile
{
    public const string DISK = 'identity';

    public function __construct(
        private readonly VirusScanner $scanner,
        private readonly AuditLogger $audit,
        private readonly SubmitIdentityVerification $submit,
    ) {}

    /**
     * @throws IdentityLocked vérification envoyée ou déjà vérifiée
     * @throws InvalidArgumentException fichier non demandé pour cette pièce, ou format refusé
     * @throws InfectedFile menace détectée par l'antivirus
     */
    public function handle(IdentityVerification $verification, IdentityFileKind $kind, UploadedFile $file, bool $autoSubmit = true): IdentityFile
    {
        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        if ($kind->isDocumentSide() && ! in_array($kind, $verification->documentType?->sides() ?? [], true)) {
            throw new InvalidArgumentException(__('identity.errors.file_not_needed', ['kind' => $kind->label()]));
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $kind->mimeTypes(), true)) {
            throw new InvalidArgumentException(__('identity.errors.file_refused', [
                'kind' => $kind->label(),
                'formats' => $kind->isDocumentSide() ? 'JPEG, PNG, HEIC, PDF' : 'JPEG, PNG, HEIC',
            ]));
        }

        $localPath = (string) $file->getRealPath();
        $threat = $this->scanner->scan($localPath);

        if ($threat !== null) {
            $this->audit->record('identity.file.infected', $verification, ['kind' => $kind->value, 'threat' => $threat], actor: $verification->user);

            throw new InfectedFile($threat);
        }

        $sha256 = (string) hash_file('sha256', $localPath);
        $path = 'identite/'.$verification->uuid.'/'.Str::uuid7().'.'.$this->extensionFor($mime);
        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        try {
            $stored = DB::transaction(function () use ($verification, $kind, $file, $mime, $sha256, $path): IdentityFile {
                $previous = $verification->files()->where('kind', $kind->value)->lockForUpdate()->first();

                if ($previous !== null) {
                    Storage::disk($previous->disk)->delete($previous->path);
                    $previous->delete();
                }

                $stored = $verification->files()->create([
                    'kind' => $kind,
                    'disk' => self::DISK,
                    'path' => $path,
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                    'sha256' => $sha256,
                    'scanned_at' => now(),
                ]);

                $this->audit->record('identity.file.uploaded', $verification, [
                    'kind' => $kind->value,
                    'sha256' => $sha256,
                    'replaced' => $previous !== null,
                ], actor: $verification->user);

                return $stored;
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }

        // Dernier fichier reçu : le dossier part tout seul en vérification.
        if ($autoSubmit) {
            $this->submit->submitIfComplete($verification->refresh());
        }

        return $stored;
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/heic', 'image/heif' => 'heic',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
