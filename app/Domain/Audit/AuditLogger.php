<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Support\PayloadSanitizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inscrit une action sensible dans le journal d'audit (ADM-25).
 * Les écritures sont sérialisées (verrou consultatif) pour garantir une chaîne d'empreintes continue.
 */
class AuditLogger
{
    private const CHAIN_LOCK = 734_201;

    public function __construct(private readonly PayloadSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $payload = [],
        ?User $actor = null,
        ?int $communeId = null,
    ): AuditLog {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return DB::transaction(function () use ($action, $subject, $payload, $actor, $communeId, $request): AuditLog {
            DB::select('select pg_advisory_xact_lock(?)', [self::CHAIN_LOCK]);

            $previousHash = AuditLog::query()->orderByDesc('id')->value('hash');

            $entry = new AuditLog([
                'occurred_at' => now()->startOfSecond(),
                'actor_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
                'subject_id' => $subject ? (string) ($subject->getAttribute('uuid') ?? $subject->getKey()) : null,
                'commune_id' => $communeId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'payload' => $payload === [] ? null : $this->sanitizer->clean($payload),
                'prev_hash' => $previousHash,
            ]);

            $entry->uuid = (string) Str::uuid7();
            $entry->hash = AuditLog::computeHash($previousHash, $entry);
            $entry->save();

            return $entry;
        });
    }
}
