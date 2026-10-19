<?php

namespace App\Domain\Media\Storage;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

/**
 * Envoi multipart S3 sur Wasabi (disque « medias »).
 */
class S3MultipartStorage implements MultipartStorage
{
    public function __construct(private readonly string $disk = 'medias') {}

    public function initiate(string $path, string $mime): string
    {
        $result = $this->client()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $this->key($path),
            'ContentType' => $mime,
        ]);

        return (string) $result['UploadId'];
    }

    public function presignPart(string $path, string $uploadId, int $partNumber, int $minutes): string
    {
        $command = $this->client()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $this->key($path),
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return (string) $this->client()->createPresignedRequest($command, "+{$minutes} minutes")->getUri();
    }

    public function listParts(string $path, string $uploadId): array
    {
        $parts = [];
        $marker = 0;

        do {
            $result = $this->client()->listParts([
                'Bucket' => $this->bucket(),
                'Key' => $this->key($path),
                'UploadId' => $uploadId,
                'PartNumberMarker' => $marker,
            ]);

            /** @var list<array{PartNumber: int, ETag: string, Size: int}> $page */
            $page = $result['Parts'] ?? [];

            foreach ($page as $part) {
                $parts[] = ['number' => (int) $part['PartNumber'], 'etag' => trim((string) $part['ETag'], '"'), 'size' => (int) $part['Size']];
            }

            $marker = (int) ($result['NextPartNumberMarker'] ?? 0);
        } while ((bool) ($result['IsTruncated'] ?? false));

        return $parts;
    }

    public function complete(string $path, string $uploadId, array $parts): void
    {
        usort($parts, fn (array $a, array $b): int => $a['number'] <=> $b['number']);

        $this->client()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $this->key($path),
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => array_map(fn (array $part): array => ['PartNumber' => $part['number'], 'ETag' => '"'.trim($part['etag'], '"').'"'], $parts)],
        ]);
    }

    public function abort(string $path, string $uploadId): void
    {
        $this->client()->abortMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $this->key($path), 'UploadId' => $uploadId]);
    }

    public function download(string $path, string $localPath): void
    {
        $this->client()->getObject(['Bucket' => $this->bucket(), 'Key' => $this->key($path), 'SaveAs' => $localPath]);
    }

    private function client(): S3Client
    {
        /** @var AwsS3V3Adapter $adapter */
        $adapter = Storage::disk($this->disk);

        return $adapter->getClient();
    }

    private function bucket(): string
    {
        return (string) config("filesystems.disks.{$this->disk}.bucket");
    }

    private function key(string $path): string
    {
        $root = trim((string) config("filesystems.disks.{$this->disk}.root"), '/');

        return $root === '' ? $path : $root.'/'.$path;
    }
}
