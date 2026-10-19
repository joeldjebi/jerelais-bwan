<?php

namespace App\Domain\Legal\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Legal\Models\Consent;
use App\Domain\Legal\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enregistre l'acceptation des textes en vigueur (MOB-04) : version, date, canal, adresse IP.
 * Seules les versions en vigueur peuvent être acceptées ; une version déjà acceptée n'est pas dupliquée.
 */
class AcceptLegalDocuments
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $documentUuids
     * @return list<Consent>
     */
    public function handle(User $user, array $documentUuids, string $channel, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $current = LegalDocument::currentSet();
        $currentUuids = array_map(fn (LegalDocument $document): string => $document->uuid, array_values($current));
        sort($currentUuids);

        $given = array_values(array_unique($documentUuids));
        sort($given);

        if ($current === [] || $given !== $currentUuids) {
            throw new InvalidArgumentException(__('legal.outdated'));
        }

        return DB::transaction(function () use ($user, $current, $channel, $ipAddress, $userAgent): array {
            $consents = [];

            foreach ($current as $document) {
                $consents[] = $user->consents()->firstOrCreate(
                    ['legal_document_id' => $document->id],
                    [
                        'accepted_at' => now(),
                        'channel' => $channel,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 250, ''),
                    ],
                );
            }

            $this->audit->record('legal.accepted', $user, [
                'versions' => array_map(fn (LegalDocument $document): string => $document->type->value.' v'.$document->version, array_values($current)),
                'channel' => $channel,
            ], actor: $user);

            return $consents;
        });
    }

    /**
     * L'utilisateur a accepté la version en vigueur de chaque texte.
     */
    public static function isUpToDate(User $user): bool
    {
        $current = LegalDocument::currentSet();

        if ($current === []) {
            return false;
        }

        $accepted = Consent::query()
            ->where('user_id', $user->id)
            ->whereIn('legal_document_id', array_map(fn (LegalDocument $document): int => $document->id, array_values($current)))
            ->count();

        return $accepted === count($current);
    }
}
