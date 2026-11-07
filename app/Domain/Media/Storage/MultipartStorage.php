<?php

namespace App\Domain\Media\Storage;

/**
 * Envoi fractionné et reprenable (MOB-16) : l'application dépose chaque morceau directement
 * sur le stockage par URL signée ; le serveur ne fait qu'ouvrir, lister et clore l'envoi.
 */
interface MultipartStorage
{
    public function initiate(string $path, string $mime): string;

    public function presignPart(string $path, string $uploadId, int $partNumber, int $minutes): string;

    /**
     * @return list<array{number: int, etag: string, size: int}>
     */
    public function listParts(string $path, string $uploadId): array;

    /**
     * @param  list<array{number: int, etag: string}>  $parts
     */
    public function complete(string $path, string $uploadId, array $parts): void;

    public function abort(string $path, string $uploadId): void;

    /**
     * Copie locale du fichier complet pour le contrôle (antivirus, empreintes, métadonnées).
     */
    public function download(string $path, string $localPath): void;
}
