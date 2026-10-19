# Contrat d'API v0 — à valider avec l'équipe mobile (fin S1)

La référence vivante est le schéma OpenAPI généré par Scramble depuis le code :
`/docs/api` (local) ou `php artisan scramble:export` (artefact `openapi` de la CI).
Ce document fixe ce que les deux équipes s'engagent à respecter avant que les routes n'existent.

## Conventions

| Sujet | Règle |
|---|---|
| Base | `https://<domaine>/api/v1` — un changement cassant = `/api/v2` |
| Format | JSON, `snake_case`, dates ISO 8601 UTC, identifiants UUID |
| Succès | `{ "data": …, "meta": … }` ; listes paginées par curseur (`meta.next_cursor`) |
| Erreur | Code HTTP correct + `{ "message": "…", "errors": { "champ": ["…"] } }`, messages en français |
| Authentification | `Authorization: Bearer <jeton Sanctum>`, un jeton par appareil |
| Version de l'app | En-tête `X-App-Version` ; `426` si une mise à jour est obligatoire |
| Limitation | `429` avec en-tête `Retry-After` |
| Idempotence | `client_uuid` généré par l'app pour chaque relais ; renvoi = relais existant (200) |

## Codes HTTP

`200` OK · `201` créé · `204` sans contenu · `401` non authentifié · `403` interdit · `404` introuvable · `409` conflit (verrou) · `422` validation · `426` mise à jour requise · `429` trop de requêtes.

## Routes

✅ = disponible sur `develop` (voir `/docs/api` pour le détail des champs).

| Méthode | Route | Rôle | Étape | Exigences |
|---|---|---|---|---|
| POST ✅ | `/auth/otp` | Demander un code SMS | 1 | MOB-01 |
| POST ✅ | `/auth/verify` | Vérifier le code, obtenir un jeton | 1 | MOB-01 |
| POST ✅ | `/auth/login` · `/auth/logout` | Connexion mot de passe, révocation du jeton | 1 | MOB-01 |
| PUT ✅ | `/devices/current` | Jeton FCM et version de l'application de l'appareil courant | 1 | MOB-21 |
| GET ✅ | `/communes` · `/categories` · `/settings` | Référentiels | 1 | MOB-13, MOB-14 |
| GET ✅ / PUT ✅ | `/candidature` · `/candidature/steps/{identite\|territoire\|profil}` | Dossier en étapes, sauvegarde automatique (champs facultatifs, 409 si dossier verrouillé) | 2 | MOB-02, MOB-06 |
| POST ✅ | `/candidature/submit` | Dépôt (422 avec la liste des éléments manquants) | 2 | MOB-06 |
| GET ✅ | `/candidature/options` · `/communes/{uuid}/localites` | Listes du formulaire, quartiers d'une commune | 2 | MOB-02 |
| POST ✅ / DELETE ✅ | `/candidature/documents` · `/candidature/documents/{type}` | Pièces (multipart `type` + `file`) : photo de profil, recto, verso, selfie, autorisation parentale si mineur. JPEG/PNG/HEIC (PDF pour l'autorisation), 10 Mo, antivirus ; 503 si l'antivirus est indisponible | 2 | MOB-03 |
| GET ✅ | `/legal` | Textes en vigueur (charte, conditions, confidentialité), Markdown et HTML | 2 | MOB-04 |
| GET ✅ / POST ✅ | `/consents` | Mes acceptations ; accepter en envoyant les `uuid` de toutes les versions en vigueur (422 si une version a changé) | 2 | MOB-04 |
| POST | `/quiz/attempts` | Quiz | 2 | MOB-05 |
| POST | `/relais` | Créer un relais (`client_uuid`) | 3 | MOB-10 à MOB-15 |
| — | Téléversement tus ou multipart S3 signé | Médias reprenables | 3 | MOB-16, MOB-18 |
| GET | `/relais` · `/relais/{uuid}` | Mes relais et statuts | 3 | MOB-20 |
| POST | `/relais/{uuid}/complement` | Répondre à un complément | 3 | MOB-22 |
| GET | `/appels` · `/appels/{uuid}` | Appels à relais de ma zone | 4 | MOB-23 |
| GET/POST | `/conversations` · `/conversations/{uuid}/messages` | Messagerie | 4 | MOB-22 |
| GET ✅ / PUT ✅ | `/me` · `/me/password` | Profil, définition du mot de passe | 1 | MOB-01 |
| GET | `/me/stats` · `/me/badge` | Statistiques, badge | 2–4 | MOB-07, MOB-24 |
| GET/DELETE | `/me/export` · `/me` | Droits des personnes | 5 | §9.4 |
| GET | `/coordination/queue` | File du coordonnateur (mode mobile) | 3 | §4.5 |
| POST | `/relais/{uuid}/lock` · `/relais/{uuid}/decision` | Verrou, décision | 3 | COO-14 |

## Notifications push (FCM, Android et iOS)

```json
{ "type": "relais.status_changed", "uuid": "9b1d…", "status": "validated" }
```

Types : `relais.status_changed`, `appel.created`, `message.received`, `candidature.decided`.
Aucun contenu sensible dans le titre ou le texte affiché.

## Décision à prendre en S1

Protocole de téléversement : **tus** (reprise native, serveur PHP) ou **multipart S3 signé vers Wasabi** (le fichier ne transite pas par nos serveurs). Recommandation : multipart S3 signé, moins de charge serveur pour des vidéos jusqu'à 500 Mo.
