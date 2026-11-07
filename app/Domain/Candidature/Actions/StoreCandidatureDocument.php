<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Exceptions\InfectedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enregistre une pièce du dossier (MOB-03) :
 * type contrôlé sur le contenu, antivirus avant stockage, empreinte SHA-256, nom aléatoire,
 * disque privé « identity ». Une nouvelle pièce remplace la précédente du même type.
 */
class StoreCandidatureDocument
{
    public const DISK = 'identity';

    public function __construct(
        private readonly VirusScanner $scanner,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Candidature $candidature, DocumentType $type, UploadedFile $file): CandidatureDocument
    {
        if (! $candidature->status->isEditable()) {
            throw CandidatureLocked::make();
        }

        $mime = (string) $file->getMimeType();

        if (! $type->appliesTo($candidature) || ! in_array($mime, $type->mimeTypes(), true)) {
            throw new InvalidArgumentException("Pièce refusée : {$type->value} ({$mime}).");
        }

        $localPath = (string) $file->getRealPath();
        $threat = $this->scanner->scan($localPath);

        if ($threat !== null) {
            $this->audit->record('candidature.document.infected', $candidature, ['type' => $type->value, 'threat' => $threat], communeId: $candidature->commune_id);

            throw new InfectedFile($threat);
        }

        $sha256 = (string) hash_file('sha256', $localPath);
        $path = 'candidatures/'.$candidature->uuid.'/'.Str::uuid7().'.'.$this->extensionFor($mime);
        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        try {
            return DB::transaction(function () use ($candidature, $type, $file, $mime, $sha256, $path): CandidatureDocument {
                $previous = $candidature->documents()->where('type', $type->value)->lockForUpdate()->first();

                if ($previous !== null) {
                    Storage::disk($previous->disk)->delete($previous->path);
                    $previous->delete();
                }

                $document = $candidature->documents()->create([
                    'type' => $type,
                    'disk' => self::DISK,
                    'path' => $path,
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                    'sha256' => $sha256,
                    'scanned_at' => now(),
                ]);

                $this->audit->record('candidature.document.uploaded', $candidature, [
                    'type' => $type->value,
                    'sha256' => $sha256,
                    'replaced' => $previous !== null,
                ], communeId: $candidature->commune_id);

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
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
