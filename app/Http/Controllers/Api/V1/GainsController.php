<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Models\Commission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CommissionResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Mon compte', 'Profil, mot de passe, tableau de bord, badge, appareil et données personnelles.', weight: 2)]
class GainsController extends Controller
{
    /**
     * Mes gains (MOB-26).
     *
     * Commissions gagnées pour mes relais validés et diffusés, la plus récente d'abord (20 par page).
     * `meta.totals` : `due` (à recevoir) et `paid` (déjà reçu), en FCFA. `meta.gains_eligible` : identité vérifiée,
     * condition pour être payé (sinon, inviter à compléter la vérification d'identité).
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $commissions = Commission::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->with(['relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('id')
            ->paginate(20);

        $totals = Commission::query()->where('user_id', $user->id)
            ->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');

        return CommissionResource::collection($commissions)->additional(['meta' => [
            'totals' => [
                'due' => (int) ($totals[CommissionStatus::Due->value] ?? 0),
                'paid' => (int) ($totals[CommissionStatus::Paid->value] ?? 0),
                'currency' => 'XOF',
            ],
            'gains_eligible' => $user->isEligibleForGains(),
        ]]);
    }
}
