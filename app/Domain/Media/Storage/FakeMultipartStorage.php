<?php

namespace App\Domain\Media\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Envoi multipart simulé pour les tests : les morceaux sont déposés en mémoire puis assemblés sur le disque « medias ».
 */
class FakeMultipartStorage implements MultipartStorage
{
    /** @var array<string, array<int, string>> uploadId => (numéro => contenu) */
    public array $parts = [];

    public function initiate(string $path, string $mime): string
    {
        $uploadId = 'fake-'.Str::random(12);
        $this->parts[$uploadId] = [];

        return $uploadId;
    }

    public function presignPart(string $path, string $uploadId, int $partNumber, int $minutes): string
    {
        return "https://stockage.test/{$path}?uploadId={$uploadId}&partNumber={$partNumber}&expires={$minutes}";
    }

    /**
     * Simule le dépôt d'un morceau par l'application.
     */
    public function putPart(string $uploadId, int $number, string $content): string
    {
        $this->parts[$uploadId][$number] = $content;

        return hash('sha256', $content);
    }

    public function listParts(string $path, string $uploadId): array
    {
        $parts = [];

        foreach ($this->parts[$uploadId] ?? [] as $number => $content) {
            $parts[] = ['number' => $number, 'etag' => hash('sha256', $content), 'size' => strlen($content)];
        }

        return $parts;
    }

    public function complete(string $path, string $uploadId, array $parts): void
    {
        $stored = $this->parts[$uploadId] ?? [];
        ksort($stored);

        foreach ($parts as $part) {
            if (! isset($stored[$part['number']]) || hash('sha256', $stored[$part['number']]) !== trim($part['etag'], '"')) {
                throw new \RuntimeException("Morceau {$part['number']} invalide.");
            }
        }

        Storage::disk('medias')->put($path, implode('', $stored));
        unset($this->parts[$uploadId]);
    }

    public function abort(string $path, string $uploadId): void
    {
        unset($this->parts[$uploadId]);
    }

    public function download(string $path, string $localPath): void
    {
        file_put_contents($localPath, Storage::disk('medias')->get($path));
    }
}
