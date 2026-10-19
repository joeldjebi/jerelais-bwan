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
| GET ✅ | `/formation` | Fiches de formation (Markdown et HTML) | 2 | MOB-05 |
| GET ✅ / POST ✅ | `/quiz` · `/quiz/attempts` | Questions sans les bonnes réponses ; correction avec explications ; score minimal paramétrable ; 10 tentatives par heure | 2 | MOB-05 |
| POST ✅ / PUT ✅ | `/relais` · `/relais/{uuid}` | Créer un relais (idempotent sur `client_uuid` : 201 puis 200 ; 409 si l'identifiant appartient à un autre compte) et compléter le brouillon. Champs : `category`, `urgency`, `title`, `description`, `location{lat,lng,accuracy_m}`, `declared_commune`, `declared_place`, `device_captured_at`, `device_model`, `protections{has_minors,people_consent,credit_mode,credit_name}` | 3 | MOB-10 à MOB-15, MOB-19 |
| POST ✅ | `/relais/{uuid}/submit` | Envoi : 422 avec les champs manquants ; routage GPS puis commune déclarée ; idempotent | 3 | MOB-15, §8.2 |
| POST ✅ | `/relais/{uuid}/medias` | Annoncer un média (`client_uuid`, `kind` photo/video/audio, `mime`, `size`, `sha256`, `filename`, `is_imported`, `device_captured_at`) ; renvoie `part_size` et les URL signées (30 min) des morceaux | 3 | MOB-11, MOB-12, MOB-16 |
| PUT | *URL signée Wasabi* | Déposer un morceau (5 Mo minimum sauf le dernier) ; noter l'en-tête `ETag` de la réponse | 3 | MOB-16 |
| GET ✅ / POST ✅ | `/medias/{uuid}` · `/medias/{uuid}/complete` | Reprise (morceaux reçus et URL des manquants) ; clôture avec `parts[{number, etag}]` ; statut `pending → uploaded → processing → ready` ou `rejected` (motif) | 3 | MOB-16 |
| GET ✅ | `/relais` · `/relais/{uuid}` | Mes relais et statut public (`uploading`, `received`, `complement_requested`, `validated`, `rejected`, `selected`, `broadcast`, `archived`) | 3 | MOB-20 |
| POST | `/relais/{uuid}/complement` | Répondre à un complément | 3 | MOB-22 |
| GET | `/appels` · `/appels/{uuid}` | Appels à relais de ma zone | 4 | MOB-23 |
| GET/POST | `/conversations` · `/conversations/{uuid}/messages` | Messagerie | 4 | MOB-22 |
| GET ✅ / PUT ✅ | `/me` · `/me/password` | Profil, définition du mot de passe | 1 | MOB-01 |
| GET ✅ | `/me/badge` | Badge : numéro, prénom + initiale, commune, statut, photo (lien signé 15 min), `qr_payload` à encoder en QR ; 404 avant acceptation | 2 | MOB-07 |
| GET | `/me/stats` | Statistiques | 4 | MOB-24 |
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
