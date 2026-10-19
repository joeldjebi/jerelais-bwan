<?php

namespace App\Http\Controllers\Api\V1\Appels;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Relais\Models\RelaisProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appels à relais de ma zone (MOB-23). Pour y répondre : `POST /relais` avec `appel` = uuid de l'appel.
 */
class AppelController extends Controller
{
    /**
     * Appels ouverts qui me concernent.
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        return response()->json(['data' => $profile === null ? [] : AppelAudience::openFor($profile)->map(fn (AppelRelais $appel): array => $this->present($appel))]);
    }

    /**
     * Un appel.
     */
    public function show(Request $request, AppelRelais $appel): JsonResponse
    {
        $profile = $this->profile($request);
        abort_unless($profile !== null && AppelAudience::targets($appel, $profile), 404);

        return response()->json(['data' => $this->present($appel)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AppelRelais $appel): array
    {
        return [
            'uuid' => $appel->uuid,
            'title' => $appel->title,
            'instructions' => $appel->instructions,
            'target' => $appel->target->value,
            'target_label' => $appel->target->label(),
            'zone' => $appel->communes->pluck('name')->merge($appel->regions->pluck('name'))->values(),
            'lieu_type' => $appel->lieuType?->name,
            'is_open' => $appel->isOpen(),
            'deadline_at' => $appel->deadline_at->toIso8601ZuluString(),
            'published_at' => $appel->published_at?->toIso8601ZuluString(),
        ];
    }

    private function profile(Request $request): ?RelaisProfile
    {
        /** @var User $user */
        $user = $request->user();

        return RelaisSender::profileOf($user);
    }
}
