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
        $national = $this->ensureCanCreate($creator);
        [$target, $communeIds, $regionIds] = $this->zone($creator, $data);
        $needsApproval = ! $national && (bool) $this->settings->get('appels.local_requires_approval');

        $appel = DB::transaction(function () use ($creator, $data, $target, $communeIds, $regionIds, $national, $needsApproval): AppelRelais {
            $appel = new AppelRelais([
                ...$this->attributes($data, $target),
                'status' => $needsApproval ? AppelStatus::PendingApproval : AppelStatus::Open,
                'is_local' => ! $national,
            ]);
            $appel->creator()->associate($creator);
            $appel->save();
            $this->syncZone($appel, $target, $communeIds, $regionIds);

            $this->audit->record('appel.created', $appel, ['title' => $appel->title, 'target' => $target->value, 'pending' => $needsApproval], actor: $creator);

            return $appel;
        });

        if (! $needsApproval) {
            $this->open($appel);
        }

        return $appel;
    }

    /**
     * Modifie un appel encore ouvert ou en attente d'accord : sujet, consignes, date limite, zone, type de lieu.
     * Seuls les Relais nouvellement concernés sont prévenus ; ceux qui l'étaient déjà ne reçoivent pas de second SMS.
     *
     * @param  array{title: string, instructions: string, target: string, deadline_at: string, communes?: list<string>, regions?: list<string>, lat?: float|null, lng?: float|null, radius_km?: float|null, lieu_type_id?: int|null}  $data
     */
    public function update(AppelRelais $appel, User $editor, array $data): AppelRelais
    {
        if ($appel->status === AppelStatus::Closed) {
            throw new InvalidArgumentException(__('appels_relais.errors.closed'));
        }

        $this->ensureCanCreate($editor);
        $wasOpen = $appel->isOpen();
        $before = $wasOpen ? AppelAudience::profiles($appel)->pluck('relais_profiles.id')->all() : [];
        [$target, $communeIds, $regionIds] = $this->zone($editor, $appel->is_local ? [...$data, 'target' => AppelTarget::Communes->value] : $data);

        DB::transaction(function () use ($appel, $editor, $data, $target, $communeIds, $regionIds): void {
            $appel->fill($this->attributes($data, $target))->save();
            $this->syncZone($appel, $target, $communeIds, $regionIds);

            $this->audit->record('appel.updated', $appel, ['title' => $appel->title, 'target' => $target->value], actor: $editor);
        });

        $appel->refresh();

        if ($appel->status === AppelStatus::Open) {
            $after = AppelAudience::profiles($appel)->pluck('relais_profiles.id')->all();
            $appel->forceFill(['recipients_count' => count($after)])->save();
            $newcomers = array_values(array_diff($after, $before));

            if ($newcomers !== [] && $appel->isOpen()) {
                NotifyAppelRecipients::dispatch($appel->id, $newcomers);
            }
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

    /**
     * @return bool vrai pour un appel national (rédaction), faux pour un appel local (coordonnateur)
     */
    private function ensureCanCreate(User $user): bool
    {
        $national = $user->hasPermissionTo(Permission::AppelsCreate->value);

        if (! $national && ! $user->hasPermissionTo(Permission::AppelsCreateLocal->value)) {
            throw new InvalidArgumentException(__('appels_relais.errors.forbidden'));
        }

        return $national;
    }

    /**
     * Zone de l'appel. Un coordonnateur ne cible que ses communes.
     *
     * @param  array{target: string, communes?: list<string>, regions?: list<string>}  $data
     * @return array{0: AppelTarget, 1: list<int>, 2: list<int>}
     */
    private function zone(User $user, array $data): array
    {
        $target = AppelTarget::from($data['target']);
        $communeIds = array_map('intval', Commune::query()->whereIn('uuid', $data['communes'] ?? [])->pluck('id')->all());
        $regionIds = array_map('intval', Region::query()->whereIn('uuid', $data['regions'] ?? [])->pluck('id')->all());

        if (! $user->hasPermissionTo(Permission::AppelsCreate->value)) {
            $allowed = $this->access->communeIdsFor($user) ?? [];
            $target = AppelTarget::Communes;
            $communeIds = array_values(array_intersect($communeIds, $allowed)) ?: $allowed;
        }

        if ($target === AppelTarget::Communes && $communeIds === []) {
            throw new InvalidArgumentException(__('appels_relais.errors.no_commune'));
        }

        if ($target === AppelTarget::Regions && $regionIds === []) {
            throw new InvalidArgumentException(__('appels_relais.errors.no_region'));
        }

        return [$target, array_values($communeIds), array_values($regionIds)];
    }

    /**
     * @param  array{title: string, instructions: string, deadline_at: string, lat?: float|null, lng?: float|null, radius_km?: float|null, lieu_type_id?: int|null}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, AppelTarget $target): array
    {
        return [
            'title' => trim($data['title']),
            'instructions' => trim($data['instructions']),
            'target' => $target,
            'center' => $target === AppelTarget::Radius && isset($data['lat'], $data['lng']) ? Point::makeGeodetic((float) $data['lat'], (float) $data['lng']) : null,
            'radius_m' => $target === AppelTarget::Radius ? (int) round(((float) ($data['radius_km'] ?? 0)) * 1000) : null,
            'lieu_type_id' => $data['lieu_type_id'] ?? null,
            'deadline_at' => CarbonImmutable::parse($data['deadline_at'], 'Africa/Abidjan'),
        ];
    }

    /**
     * @param  list<int>  $communeIds
     * @param  list<int>  $regionIds
     */
    private function syncZone(AppelRelais $appel, AppelTarget $target, array $communeIds, array $regionIds): void
    {
        $appel->communes()->sync($target === AppelTarget::Communes ? $communeIds : []);
        $appel->regions()->sync($target === AppelTarget::Regions ? $regionIds : []);
    }
}
