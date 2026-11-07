<?php

namespace App\Domain\Legal\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Legal\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publie une nouvelle version d'un texte (ADM-23). Les versions précédentes restent consultables
 * et les acceptations déjà données restent liées à la version acceptée.
 */
class PublishLegalDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(LegalDocumentType $type, string $title, string $body, ?User $actor = null): LegalDocument
    {
        return DB::transaction(function () use ($type, $title, $body, $actor): LegalDocument {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('legal:'.$type->value)]);

            $version = (int) LegalDocument::query()->ofType($type)->max('version') + 1;

            $document = LegalDocument::query()->create([
                'type' => $type,
                'version' => $version,
                'title' => trim($title),
                'body' => trim($body),
                'published_at' => now(),
                'published_by' => $actor?->id,
            ]);

            $this->audit->record('legal.published', $document, ['type' => $type->value, 'version' => $version], actor: $actor);

            return $document;
        });
    }
}
