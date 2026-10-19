<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Models\Media;
use App\Domain\Settings\Settings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Durées de conservation paramétrables (§9.4, ADM-23) appliquées chaque nuit. Un paramètre vide = aucune purge.
 *  - candidatures refusées et comptes inactifs (candidats sans activité) : comptes pseudonymisés ;
 *  - relais rejetés, validés non diffusés, diffusés : fichiers médias supprimés du stockage,
 *    empreintes et métadonnées conservées pour la traçabilité.
 */
class PurgeExpiredData
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PseudonymizeUser $pseudonymize,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        $counts = [
            'candidatures' => $this->purgeRejectedCandidatures(),
            'inactive_accounts' => $this->purgeInactiveAccounts(),
            'rejected_relais' => $this->purgeMedias('retention.rejected_relais_days', fn (Builder $query) => $query->where('state', 'rejected')->where('updated_at', '<', $this->cutoff('retention.rejected_relais_days'))),
            'validated_not_broadcast' => $this->purgeMedias('retention.validated_not_broadcast_days', fn (Builder $query) => $query->whereIn('state', ['validated', 'selected'])->where('updated_at', '<', $this->cutoff('retention.validated_not_broadcast_days'))),
            'broadcast' => $this->purgeMedias('retention.broadcast_relais_days', fn (Builder $query) => $query->where('state', 'broadcast')->where('broadcast_at', '<', $this->cutoff('retention.broadcast_relais_days'))),
        ];

        if (array_sum($counts) > 0) {
            $this->audit->record('privacy.purged', payload: $counts);
        }

        return $counts;
    }

    private function purgeRejectedCandidatures(): int
    {
        if ($this->settings->get('retention.rejected_candidatures_days') === null) {
            return 0;
        }

        $count = 0;

        Candidature::withoutCommuneRestriction()->with('user')
            ->where('status', CandidatureStatus::Rejected->value)
            ->where('decided_at', '<', $this->cutoff('retention.rejected_candidatures_days'))
            ->whereHas('user', fn (Builder $query) => $query->where('status', '!=', UserStatus::Deleted->value))
            ->each(function (Candidature $candidature) use (&$count): void {
                $this->pseudonymize->handle($candidature->user, null, __('Durée de conservation des candidatures refusées atteinte'));
                $count++;
            });

        return $count;
    }

    private function purgeInactiveAccounts(): int
    {
        if ($this->settings->get('retention.inactive_accounts_days') === null) {
            return 0;
        }

        $cutoff = $this->cutoff('retention.inactive_accounts_days');
        $count = 0;

        User::query()->role(Role::Candidat->value)
            ->where('status', '!=', UserStatus::Deleted->value)
            ->where(fn (Builder $query) => $query->where('last_login_at', '<', $cutoff)->orWhere(fn (Builder $inner) => $inner->whereNull('last_login_at')->where('created_at', '<', $cutoff)))
            ->whereDoesntHave('candidature', fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('status', ['submitted', 'under_review', 'on_hold']))
            ->each(function (User $user) use (&$count): void {
                $this->pseudonymize->handle($user, null, __('Compte inactif au-delà de la durée de conservation'));
                $count++;
            });

        return $count;
    }

    /**
     * @param  callable(Builder<RelaisItem>): Builder<RelaisItem>  $scope
     */
    private function purgeMedias(string $setting, callable $scope): int
    {
        if ($this->settings->get($setting) === null) {
            return 0;
        }

        $relaisIds = $scope(RelaisItem::withoutCommuneRestriction())->select('id');
        $count = 0;

        Media::query()->with('variants')->whereIn('relais_item_id', $relaisIds)->whereNull('purged_at')->each(function (Media $media) use (&$count): void {
            Storage::disk($media->disk)->delete($media->path);

            foreach ($media->variants as $variant) {
                Storage::disk($variant->disk)->delete($variant->path);
                $variant->delete();
            }

            $media->forceFill(['purged_at' => now()])->save();
            $count++;
        });

        return $count;
    }

    private function cutoff(string $setting): CarbonImmutable
    {
        return now()->toImmutable()->subDays($this->settings->integer($setting));
    }
}
