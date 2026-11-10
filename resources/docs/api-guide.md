API REST de **JE RELAIS**, la plateforme des observateurs citoyens de REFLET TV. Elle est consommée par l’application mobile Android et iOS. Toutes les règles métier vivent côté serveur : l’application affiche et envoie, le serveur contrôle.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | `https://<domaine>/api/v1`. Un changement incompatible donnera `/api/v2` ; la v1 reste servie pendant la transition. |
| Format | JSON, champs en `snake_case`, dates ISO 8601 en UTC (`2026-10-21T07:40:00Z`), identifiants UUID. |
| En-têtes | `Accept: application/json` sur **toutes** les requêtes (sinon les erreurs reviennent en HTML). `Content-Type: application/json`, sauf l’envoi des pièces de candidature (`multipart/form-data`). |
| Langue | Messages d’erreur et libellés en français, prêts à afficher. |
| Enveloppe | Les ressources sont dans `data`, les informations annexes dans `meta`. |
| Pagination | `GET /relais` et `GET /diffusions` renvoient 20 éléments par page : `data`, `links` (`first`, `last`, `prev`, `next`) et `meta` (`current_page`, `last_page`, `per_page`, `total`…). Page suivante : `?page=2`. |

## Authentification

Un compte est identifié par son **numéro de téléphone** et protégé par un **code PIN de 4 chiffres**. Chaque appareil reçoit **son propre jeton** (Sanctum), révocable à distance par la coordination si le téléphone est perdu. Les codes SMS font **4 chiffres** et sont valables 5 minutes. **Phase de lancement :** tant qu’aucun fournisseur de SMS n’est branché, aucun SMS ne part et le code est toujours `1234`.

**Inscription** (numéro seul) :

1. `POST /auth/register` avec le numéro : envoie le code. Un numéro qui a déjà un compte est refusé (**422** sur `phone`) : proposer la connexion.
2. `POST /auth/register/verify` avec le numéro, le code et la description de l’appareil : crée le compte **candidat** et renvoie le jeton, avec `is_new_user: true` et `pin_required: true`.
3. `PUT /me/password` avec `password` et `password_confirmation` (4 chiffres) : définit le code PIN. **Étape obligatoire** : tant qu’elle n’est pas faite, les autres routes protégées répondent **403** avec `{"code": "pin_required"}`.

**Connexion** : `POST /auth/login` avec le numéro et le code PIN dans `password`. Après 5 PIN erronés sur un même numéro, il est bloqué 15 minutes (**429**).

**Code PIN oublié** : `POST /auth/otp`, puis `POST /auth/verify` (numéro, code, appareil) renvoie un jeton ; pendant 15 minutes, ce jeton peut redéfinir le PIN avec `PUT /me/password` **sans** `current_password`. Un numéro sans compte est refusé : proposer l’inscription.

Ensuite :

- Envoyer `Authorization: Bearer <jeton>` sur chaque requête protégée (bouton **Authorize** ci-dessus pour essayer).
- `PUT /devices/current` après chaque connexion ou mise à jour de l’application : jeton de notifications push (FCM) et version installée.
- `POST /auth/logout` détruit le jeton de l’appareil.

Le jeton est valable **60 jours** (date dans `expires_at`), puis une nouvelle connexion par code PIN est nécessaire. Il devient invalide plus tôt (réponse **401**) après une déconnexion, une révocation à distance, une radiation du Relais ou la suppression du compte : l’application doit alors revenir à l’écran de connexion.

Les comptes de l’équipe (coordonnateurs, rédaction, administration) n’ont pas accès à l’API : ils passent par la console web, avec double authentification.

Numéros acceptés : numéro ivoirien à 10 chiffres (`0700000001`, `07 00 00 00 01`) ou format international (`+2250700000001`).

## Parcours d’un candidat

1. Inscription (`POST /auth/register`, puis `POST /auth/register/verify`) : le compte est créé avec le rôle candidat ; puis code PIN obligatoire (`PUT /me/password`).
2. Lecture des listes du formulaire : `GET /candidature/options`, `GET /communes`, `GET /communes/{commune}/localites`.
3. Remplissage en trois étapes, enregistrées à chaque modification : `PUT /candidature/steps/identite`, `…/territoire`, `…/profil`.
4. Pièces : `POST /candidature/documents` (photo de profil, recto, verso, selfie ; autorisation parentale si mineur admis).
5. Textes juridiques : `GET /legal`, puis `POST /consents` avec les `uuid` des versions en vigueur.
6. Formation et quiz : `GET /formation`, `GET /quiz`, `POST /quiz/attempts`.
7. Dépôt : `POST /candidature/submit`. Le serveur renvoie **422** avec la liste des éléments manquants tant que le dossier est incomplet.
8. Suivi : `GET /candidature` (statut, motif de refus, complément demandé). Une fois accepté, le compte devient **Relais** et `GET /me/badge` répond.

Statuts d’une candidature : `draft` (à compléter) · `submitted` (reçue) · `under_review` (en examen) · `on_hold` (en attente) · `complement_requested` (complément demandé, dossier de nouveau modifiable) · `accepted` · `rejected` (avec motif).

## Envoyer un relais

1. `POST /relais` crée le brouillon. Le champ `client_uuid` est généré par l’application : renvoyer la même requête (réseau coupé, nouvel essai) rend le **même** relais (200) au lieu d’en créer un second (201).
2. Pour chaque fichier, l’envoi se fait **directement vers le stockage**, en morceaux :
   1. `POST /relais/{relais}/medias` annonce le fichier (`client_uuid`, `kind`, `mime`, `size` en octets, `sha256`). La réponse donne `upload.part_size` et, dans `upload.missing_parts`, une URL signée par morceau (valable 30 minutes).
   2. Découper le fichier en morceaux de `part_size` octets (le dernier est plus petit) et envoyer chacun par `PUT` brut sur son URL, **sans** en-tête `Authorization`. Conserver l’en-tête `ETag` de chaque réponse.
   3. Après une coupure : `GET /medias/{media}` renvoie les morceaux déjà reçus (`uploaded_parts`) et de nouvelles URL pour les manquants. Seuls ceux-là sont à renvoyer.
   4. `POST /medias/{media}/complete` avec `parts: [{number, etag}]`. La taille totale reçue doit être exactement celle annoncée.
   5. Le serveur contrôle ensuite le fichier (type réel, antivirus, empreinte, métadonnées, vignette) : `status` passe de `uploaded` à `processing`, puis `ready` ou `rejected` (avec la raison dans `rejection`). Interroger `GET /medias/{media}` toutes les quelques secondes jusqu’à `ready`.
3. `POST /relais/{relais}/submit` envoie le relais. Il faut : au moins un média entièrement envoyé (clôturé, non rejeté ; son contrôle peut encore être en cours), la catégorie, le titre, une position GPS ou une commune déclarée, et les trois réponses de protection (`has_minors`, `people_consent`, `credit_mode`, plus `credit_name` si le Relais choisit un pseudonyme). Sinon **422** avec la liste des champs manquants. L’appel est idempotent.
4. Le relais apparaît dans `GET /relais` avec son statut, mis à jour à chaque décision.

Statuts d’un média : `pending` (en attente des morceaux) · `uploaded` (reçu, à contrôler) · `processing` · `ready` · `rejected`.

Raisons de rejet d’un média : format non accepté, antivirus, fichier incomplet ou altéré, durée dépassée, taille dépassée, fichier illisible, envoi jamais terminé, taille différente de celle annoncée. Le texte affichable est dans `rejection`.

Statuts d’un relais vus par le Relais : `uploading` (brouillon, envoi en cours) · `received` (reçu, en vérification) · `complement_requested` (le coordonnateur pose une question : `complement_request`, réponse par `POST /relais/{relais}/complement`) · `validated` · `rejected` (motif dans `rejection`) · `selected` (choisi par la rédaction) · `broadcast` (diffusé) · `archived`.

Limites par défaut, modifiables par l’administration et lisibles dans `GET /settings` : vidéo de 3 minutes, audio de 10 minutes, 10 médias par relais, 500 Mo par fichier.

## Erreurs

| Code | Signification | Que faire |
|---|---|---|
| 401 | Jeton absent, invalide ou révoqué. Corps : `{"message": "Unauthenticated."}` | Revenir à l’écran de connexion. |
| 403 | Action non permise pour ce compte : candidat sur une route de Relais, Relais suspendu ou radié qui tente d’envoyer… Avec `"code": "pin_required"` : code PIN pas encore défini. | Afficher le message, ne pas réessayer. Si `pin_required` : ouvrir l’écran « Choisissez votre code PIN ». |
| 404 | Ressource inexistante **ou appartenant à quelqu’un d’autre** (le serveur ne distingue pas les deux). | — |
| 409 | Conflit d’état : dossier déjà déposé, relais déjà envoyé, `client_uuid` déjà utilisé par un autre compte. | Recharger la ressource. |
| 422 | Données invalides. Corps : `{"message": "…", "errors": {"champ": ["…"]}}` | Afficher chaque message sous son champ. |
| 429 | Trop de requêtes. En-tête `Retry-After` (secondes). | Attendre, puis réessayer. |
| 503 | Antivirus momentanément indisponible (pièces de candidature). En-tête `Retry-After`. | Réessayer plus tard. |

## Limites d’appels

- 120 appels par minute et par compte connecté ; 1 200 par minute et par adresse IP sans connexion (les opérateurs partagent souvent une même adresse publique).
- Codes SMS : 3 par numéro et par 15 minutes (quota commun au site et à l’application), 10 par adresse IP.
- Vérification d’un code : 10 par minute et par adresse IP ; 5 essais par code.
- Connexion par code PIN : 5 essais par minute ; après 5 PIN erronés, le numéro est bloqué 15 minutes, toutes adresses IP confondues.
- Création de relais : 60 par minute. Annonce de médias : 120 par minute. Messages et pièces de candidature : 30 par minute. Quiz : 10 tentatives par heure. Export des données : 5 par heure.

## Cache

Les référentiels publics (`/communes`, `/categories`, `/settings`, `/legal`, `/formation`, `/candidature/options`, `/communes/{commune}/localites`, `/diffusions`) sont envoyés avec `Cache-Control: public, max-age=300` et un `ETag`. L’application peut les garder 5 minutes, puis les revalider avec `If-None-Match` (réponse **304** si rien n’a changé).

## Notifications

À chaque décision (relais validé, rejeté, complément demandé, sélectionné, diffusé ; candidature décidée), à chaque appel à relais de sa zone et à chaque message du coordonnateur, le Relais est prévenu par **SMS**. Les notifications push (FCM) utiliseront le jeton déclaré par `PUT /devices/current`, avec un contenu de la forme `{"type": "relais.status_changed", "uuid": "…", "status": "validated"}` (types : `relais.status_changed`, `appel.created`, `message.received`, `candidature.decided`) ; leur envoi est en cours de branchement avec l’équipe mobile.

## Données personnelles

`GET /me/export` renvoie toutes les données du compte (droit d’accès). `DELETE /me` supprime le compte avec `confirm: "SUPPRIMER"` : identité, pièces, positions et messages sont effacés ; les relais déjà diffusés restent anonymes.
