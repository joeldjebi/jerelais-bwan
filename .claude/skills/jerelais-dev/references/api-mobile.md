# Contrat d'API avec l'équipe mobile

## Conventions
- Préfixe `/api/v1` ; changement cassant = `/api/v2`.
- JSON `snake_case`, dates ISO 8601 UTC, identifiants UUID.
- Succès : `{ "data": …, "meta": … }` ; listes paginées par curseur (`cursorPaginate`).
- Erreurs : statut HTTP correct + `{ "message": "…", "errors": { "champ": ["…"] } }` en français.
- `X-App-Version` envoyé par l'app ; `426 Upgrade Required` si mise à jour obligatoire.
- `Authorization: Bearer <jeton Sanctum>`, un jeton par appareil.
- Documentation générée par Scramble ; toute PR qui change le contrat porte l'étiquette `api-change`.

## Groupes de routes

| Groupe | Routes | Exigences |
|---|---|---|
| Auth | `POST /auth/otp`, `POST /auth/verify`, `POST /auth/login`, `POST /auth/logout`, `POST /devices` | MOB-01 |
| Référentiels | `GET /communes`, `GET /categories`, `GET /settings` | MOB-13, MOB-14 |
| Candidature | `PUT /candidature/steps/{step}`, `POST /candidature/documents`, `POST /consents`, `POST /quiz/attempts`, `GET /candidature` | MOB-02 à 06 |
| Relais | `POST /relais`, `GET /relais`, `GET /relais/{uuid}`, `POST /relais/{uuid}/complement` | MOB-10 à 20 |
| Téléversement | tus ou multipart S3 signé, rattaché à un relais | MOB-16, MOB-18 |
| Appels | `GET /appels`, `GET /appels/{uuid}` | MOB-23 |
| Messagerie | `GET /conversations`, `GET /conversations/{uuid}/messages`, `POST /conversations/{uuid}/messages` | MOB-22 |
| Profil | `GET /me`, `GET /me/stats`, `GET /me/badge`, `GET /me/export`, `DELETE /me` | MOB-07, MOB-24, §9.4 |
| Coordonnateur | `GET /coordination/queue`, `POST /relais/{uuid}/lock`, `POST /relais/{uuid}/decision` | §4.5 |

## Hors ligne et idempotence
- L'app génère un `client_uuid` par relais ; renvoi avec le même `client_uuid` = renvoie l'existant (200), pas de doublon.
- Conserver `device_captured_at` (heure appareil) et `received_at` (heure serveur) — MOB-13.

## Push (FCM Android + iOS)
- Payload : `type` (`relais.status_changed`, `appel.created`, `message.received`, `candidature.decided`) + `uuid` de l'objet.
- Aucun contenu sensible dans le titre/texte de la notification.
