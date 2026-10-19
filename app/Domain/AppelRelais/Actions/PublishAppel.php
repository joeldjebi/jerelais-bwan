<?php

namespace App\Domain\AppelRelais\Actions;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Jobs\NotifyAppelRecipients;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crée un appel à relais (ADM-14, COO-21). La rédaction cible librement ; un coordonnateur ne cible que ses communes
 * et son appel local attend l'accord de l'administrateur si le paramètre l'exige. Publication : les Relais sont prévenus.
 */
class PublishAppel
{
    public function __construct(
        private readonly CommuneAccess $access,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{title: string, instructions: string, target: string, deadline_at: string, communes?: list<string>, regions?: list<string>, lat?: float|null, lng?: float|null, radius_km?: float|null, lieu_type_id?: int|null}  $data
     */
    public function handle(User $creator, array $data): AppelRelais
    {
        $national = $creator->hasPermissionTo(Permission::AppelsCreate->value);

        if (! $national && ! $creator->hasPermissionTo(Permission::AppelsCreateLocal->value)) {
            throw new InvalidArgumentException(__('appels_relais.errors.forbidden'));
        }

        $target = AppelTarget::from($data['target']);
        $communeIds = Commune::query()->whereIn('uuid', $data['communes'] ?? [])->pluck('id')->all();

        if (! $national) {
            // Appel local : uniquement les communes du coordonnateur.
            $allowed = $this->access->communeIdsFor($creator) ?? [];
            $target = AppelTarget::Communes;
            $communeIds = array_values(array_intersect($communeIds, $allowed)) ?: $allowed;
        }

        if ($target === AppelTarget::Communes && $communeIds === []) {
            throw new InvalidArgumentException(__('appels_relais.errors.no_commune'));
        }

        $needsApproval = ! $national && (bool) $this->settings->get('appels.local_requires_approval');

        $appel = DB::transaction(function () use ($creator, $data, $target, $communeIds, $national, $needsApproval): AppelRelais {
            $appel = new AppelRelais([
                'title' => trim($data['title']),
                'instructions' => trim($data['instructions']),
                'target' => $target,
                'center' => $target === AppelTarget::Radius && isset($data['lat'], $data['lng']) ? Point::makeGeodetic((float) $data['lat'], (float) $data['lng']) : null,
                'radius_m' => $target === AppelTarget::Radius ? (int) round(((float) ($data['radius_km'] ?? 0)) * 1000) : null,
                'lieu_type_id' => $data['lieu_type_id'] ?? null,
                'status' => $needsApproval ? AppelStatus::PendingApproval : AppelStatus::Open,
                'is_local' => ! $national,
                'deadline_at' => CarbonImmutable::parse($data['deadline_at'], 'Africa/Abidjan'),
            ]);
            $appel->creator()->associate($creator);
            $appel->save();

            if ($target === AppelTarget::Communes) {
                $appel->communes()->sync($communeIds);
            }

            if ($target === AppelTarget::Regions) {
                $appel->regions()->sync(Region::query()->whereIn('uuid', $data['regions'] ?? [])->pluck('id'));
            }

            $this->audit->record('appel.created', $appel, ['title' => $appel->title, 'target' => $target->value, 'pending' => $needsApproval], actor: $creator);

            return $appel;
        });

        if (! $needsApproval) {
            $this->open($appel);
        }

        return $appel;
    }

    /**
     * Accord de l'administrateur sur un appel local (COO-21).
     */
    public function approve(AppelRelais $appel, User $admin): AppelRelais
    {
        if ($appel->status !== AppelStatus::PendingApproval) {
            return $appel;
        }

        $appel->forceFill(['approved_by' => $admin->id])->save();
        $this->audit->record('appel.approved', $appel, actor: $admin);

        return $this->open($appel);
    }

    public function close(AppelRelais $appel, ?User $by = null): void
    {
        $appel->forceFill(['status' => AppelStatus::Closed])->save();
        $this->audit->record('appel.closed', $appel, actor: $by);
    }

    private function open(AppelRelais $appel): AppelRelais
    {
        $appel->forceFill([
            'status' => AppelStatus::Open,
            'published_at' => now(),
            'recipients_count' => AppelAudience::profiles($appel)->count(),
        ])->save();

        NotifyAppelRecipients::dispatch($appel->id);

        return $appel;
    }
}
