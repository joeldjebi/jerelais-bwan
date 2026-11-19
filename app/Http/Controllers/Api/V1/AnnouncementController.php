<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Models\Announcement;
use App\Domain\Notification\Models\AnnouncementRecipient;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AnnouncementResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Notifications', 'Jeton de notifications push de l’appareil et annonces de l’administration (MOB-21).', weight: 2)]
class AnnouncementController extends Controller
{
    /**
     * Mes annonces.
     *
     * Annonces envoyées par l'administration à ce Relais, la plus récente d'abord (20 par page) : boîte de réception
     * de l'application, même si la notification push n'est pas arrivée. `screen` : écran à ouvrir au toucher.
     * `meta.unread_count` : nombre d'annonces non lues (pastille).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $received = AnnouncementRecipient::query()
            ->where('user_id', $user->id)
            ->with('announcement')
            ->latest('id')
            ->paginate(20);

        return AnnouncementResource::collection($received)->additional(['meta' => [
            'unread_count' => AnnouncementRecipient::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
        ]]);
    }

    /**
     * Marquer une annonce comme lue.
     *
     * À appeler quand le Relais ouvre l'annonce (ou touche sa notification, `data.uuid`). 404 si elle ne lui était
     * pas destinée. Sans effet si elle est déjà lue.
     */
    public function read(Request $request, Announcement $announcement): Response
    {
        /** @var User $user */
        $user = $request->user();

        $recipient = AnnouncementRecipient::query()
            ->where('announcement_id', $announcement->id)->where('user_id', $user->id)
            ->firstOrFail();

        if ($recipient->read_at === null) {
            $recipient->forceFill(['read_at' => now()])->save();
        }

        return response()->noContent();
    }

    /**
     * Tout marquer comme lu.
     *
     * Marque toutes les annonces du Relais comme lues (bouton « Tout lire » de la boîte de réception) :
     * `meta.unread_count` de `GET /announcements` revient à 0.
     */
    public function readAll(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        AnnouncementRecipient::query()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }
}
