<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * « Tout vider » (administration) : supprime tous les comptes du réseau — Relais et candidats, jamais l'équipe —
 * avec leurs relais, médias, conversations, candidatures, pièces, vérifications d'identité, profils et appareils.
 *
 * Les comptes sont anonymisés plutôt qu'effacés : le journal d'audit, protégé contre toute suppression (§9.5),
 * y fait référence. Identité, téléphone et mot de passe disparaissent : chaque numéro peut se réinscrire.
 * L'opération est inscrite au journal d'audit avec le nombre d'éléments supprimés.
 */
class PurgeNetworkAccounts
{
    public const string CONFIRMATION = 'SUPPRIMER';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{accounts: int, relais: int, medias: int, candidatures: int}
     */
    public function handle(User $actor, string $confirmation): array
    {
        if (! $actor->hasRole(Role::Admin->value)) {
            throw new InvalidArgumentException('Réservé aux administrateurs.');
        }

        if ($confirmation !== self::CONFIRMATION) {
            throw new InvalidArgumentException('Tapez '.self::CONFIRMATION.' pour confirmer.');
        }

        /** @var list<array{0: string, 1: string}> $files */
        $files = [];

        $counts = DB::transaction(function () use ($actor, &$files): array {
            $staffRoles = [];

            foreach (Role::cases() as $role) {
                if (! in_array($role, [Role::Relais, Role::Candidat], true)) {
                    $staffRoles[] = $role->value;
                }
            }

            $userIds = User::query()
                ->where('status', '!=', UserStatus::Deleted->value)
                ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', $staffRoles))
                ->pluck('id');

            $profileIds = DB::table('relais_profiles')->whereIn('user_id', $userIds)->pluck('id');
            $itemIds = DB::table('relais_items')->whereIn('user_id', $userIds)->orWhereIn('relais_profile_id', $profileIds)->pluck('id');
            $mediaIds = DB::table('medias')->whereIn('relais_item_id', $itemIds)->pluck('id');
            $candidatureIds = DB::table('candidatures')->whereIn('user_id', $userIds)->pluck('id');
            $verificationIds = DB::table('identity_verifications')->whereIn('user_id', $userIds)->pluck('id');

            foreach (DB::table('medias')->whereIn('id', $mediaIds)->get(['disk', 'path']) as $media) {
                $files[] = [(string) $media->disk, (string) $media->path];
            }

            foreach (DB::table('media_variants')->whereIn('media_id', $mediaIds)->get(['disk', 'path']) as $variant) {
                $files[] = [(string) $variant->disk, (string) $variant->path];
            }

            foreach (DB::table('candidature_documents')->whereIn('candidature_id', $candidatureIds)->get(['disk', 'path']) as $document) {
                $files[] = [(string) $document->disk, (string) $document->path];
            }

            foreach (DB::table('identity_files')->whereIn('identity_verification_id', $verificationIds)->get(['disk', 'path']) as $file) {
                $files[] = [(string) $file->disk, (string) $file->path];
            }

            // Conversations du réseau (et leurs messages, y compris ceux de l'équipe).
            $conversationIds = DB::table('conversations')->whereIn('relais_profile_id', $profileIds)->orWhereIn('relais_item_id', $itemIds)->pluck('id');
            DB::table('messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('conversations')->whereIn('id', $conversationIds)->delete();

            // Relais et médias (variantes, historique et collections suivent par cascade).
            DB::table('export_logs')->whereIn('relais_item_id', $itemIds)->orWhereIn('media_id', $mediaIds)->delete();
            DB::table('media_variants')->whereIn('media_id', $mediaIds)->delete();
            DB::table('medias')->whereIn('id', $mediaIds)->delete();
            DB::table('relais_items')->whereIn('id', $itemIds)->delete();

            // Profils, vérifications d'identité, candidatures.
            DB::table('relais_profiles')->whereIn('id', $profileIds)->delete();
            DB::table('identity_files')->whereIn('identity_verification_id', $verificationIds)->delete();
            DB::table('identity_verifications')->whereIn('id', $verificationIds)->delete();
            DB::table('candidature_documents')->whereIn('candidature_id', $candidatureIds)->delete();
            DB::table('candidatures')->whereIn('id', $candidatureIds)->delete();

            // Traces de compte : consentements, quiz, appareils, jetons, sessions, rôles.
            DB::table('consents')->whereIn('user_id', $userIds)->delete();
            DB::table('quiz_attempts')->whereIn('user_id', $userIds)->delete();
            DB::table('devices')->whereIn('user_id', $userIds)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $userIds)->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->delete();

            // Comptes anonymisés : le numéro redevient libre.
            DB::table('users')->whereIn('id', $userIds)->update([
                'name' => __('Compte supprimé'),
                'email' => null,
                'phone' => null,
                'phone_hash' => null,
                'password' => null,
                'remember_token' => null,
                'status' => UserStatus::Deleted->value,
                'pseudonymized_at' => now(),
                'updated_at' => now(),
            ]);

            $counts = [
                'accounts' => $userIds->count(),
                'relais' => $itemIds->count(),
                'medias' => $mediaIds->count(),
                'candidatures' => $candidatureIds->count(),
            ];

            $this->audit->record('network.purged', payload: $counts, actor: $actor);

            return $counts;
        });

        foreach ($files as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                Log::warning('[Purge] fichier non supprimé du stockage', ['disk' => $disk, 'error' => class_basename($exception)]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $counts;
    }
}
