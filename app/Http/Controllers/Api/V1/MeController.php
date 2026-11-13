<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Models\Device;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Relais\Support\RelaisActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCurrentDeviceRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\V1\BadgeResource;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Mon compte', 'Profil, mot de passe, tableau de bord, badge, appareil et données personnelles.', weight: 2)]
class MeController extends Controller
{
    /**
     * Mon profil.
     *
     * Prénom (`first_name`) et nom (`last_name`) séparés, genre, date de naissance, numéro masqué, e-mail, statut du compte,
     * rôles, présence d'un mot de passe, profil Relais (`relais` : numéro, nom affiché, statut, commune, localité) et
     * vérification d'identité (`identity_status`, `gains_eligible`). Même objet que `user` dans la réponse de connexion.
     */
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('communes'));
    }

    /**
     * Mon badge de Relais (MOB-07).
     *
     * Numéro de Relais, nom affiché (prénom et initiale), commune, statut et photo (lien signé valable 15 minutes).
     * `qr_payload` est l'adresse publique de vérification du badge : l'application la transforme en QR code.
     *
     * 404 tant que la candidature n'est pas acceptée.
     */
    public function badge(Request $request): BadgeResource
    {
        /** @var User $user */
        $user = $request->user();

        $profile = RelaisProfile::withoutCommuneRestriction()->with('commune')->where('user_id', $user->id)->first();
        abort_if($profile === null, 404);

        return new BadgeResource($profile);
    }

    /**
     * Mon tableau de bord (MOB-24).
     *
     * Relais envoyés, validés et diffusés ; taux de validation (`null` tant qu'aucune décision n'a été prise) ;
     * niveau actuel (`debutant`, `bronze`, `argent`, `or`) et nombre de relais validés manquants pour le suivant ;
     * badges obtenus ou à obtenir. Les seuils des niveaux sont dans `GET /settings` (`levels.*`).
     */
    public function stats(Request $request, RelaisActivity $activity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $activity->for($user)]);
    }

    /**
     * Mes données personnelles (droit d'accès, §9.4).
     *
     * Toutes les données du compte au format JSON : identité, candidature, consentements, relais, médias, appareils, messages.
     * Chaque export est tracé dans le journal d'audit. Limité à 5 par heure.
     */
    public function export(Request $request, ExportPersonalData $export, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $audit->record('privacy.exported', $user, ['channel' => 'app'], actor: $user);

        return response()->json(['data' => $export->handle($user)]);
    }

    /**
     * Supprimer mon compte (§9.4).
     *
     * Envoyer `confirm` = « SUPPRIMER ». Le compte est pseudonymisé : identité, pièces, positions et messages sont effacés.
     */
    public function destroy(Request $request, PseudonymizeUser $pseudonymize): Response
    {
        $request->validate(['confirm' => ['required', 'in:SUPPRIMER']]);

        /** @var User $user */
        $user = $request->user();
        abort_if($user->isStaff(), 403);
        $pseudonymize->handle($user, $user, __('Demande du titulaire du compte'));

        return response()->noContent();
    }

    /**
     * Définir ou changer mon mot de passe.
     *
     * Mot de passe d'au moins 8 caractères dans `password`, répété dans `password_confirmation`. Un compte sans mot de passe
     * (créé sur le site web) doit le définir : en attendant, les autres routes protégées répondent 403 avec `code: "password_required"`.
     * Pour le changer depuis les réglages, `current_password` (l'ancien) est obligatoire. Mot de passe oublié : utiliser
     * `POST /auth/password/reset` après le code SMS. `has_password` dans `GET /me` indique s'il en existe un.
     */
    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $audit): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->string('password')->toString()])->save();
        $audit->record('auth.password.changed', $user, actor: $user);

        return response()->noContent();
    }

    /**
     * Mettre à jour l'appareil courant.
     *
     * À appeler après chaque connexion, puis au démarrage si le jeton de notifications push (FCM) ou la version de l'application a changé.
     * Met aussi à jour la date de dernière activité de l'appareil, visible par la coordination.
     */
    public function updateDevice(UpdateCurrentDeviceRequest $request): DeviceResource
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        $device = Device::query()->where('personal_access_token_id', $token->getKey())->firstOrFail();
        $device->fill([
            ...$request->safe()->only(['fcm_token', 'app_version']),
            'last_seen_at' => now(),
        ])->save();

        return new DeviceResource($device);
    }
}
