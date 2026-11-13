# JE RELAIS — Guide d’intégration de l’application mobile

Inscription, connexion, mot de passe oublié et profil (vérification d’identité pour les commissions).
Référence complète de toutes les routes : Swagger en ligne `https://jerelais.com/docs/swagger` (accès fourni par l’équipe JE RELAIS) ou fichier autonome `docs/api/swagger.html`.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | `https://jerelais.com/api/v1` |
| En-têtes | `Accept: application/json` sur **toutes** les requêtes ; `Content-Type: application/json`, sauf `identity/files` (multipart) |
| Numéro | 10 chiffres ivoiriens (`0700000001`, espaces acceptés) ou `+2250700000001`. Seuls les numéros de Côte d’Ivoire reçoivent un code (422 « Seuls les numéros de Côte d’Ivoire (+225) peuvent recevoir un code. ») |
| Codes SMS | 4 chiffres, valables 5 minutes, 5 essais par code, 3 envois par numéro et par 15 minutes. **En production, ils arrivent par SMS.** Sans fournisseur de SMS (développement, recette), le code est toujours `1234` |
| Mot de passe | Au moins 8 caractères, à saisir deux fois (`password` + `password_confirmation`) |
| Jeton | `Authorization: Bearer <jeton>` sur les routes protégées ; valable 60 jours |
| Erreurs de saisie | 422 : `{"message": "…", "errors": {"champ": ["Message à afficher sous le champ."]}}` |

```bash
BASE=https://jerelais.com/api/v1
```

## Parcours

```
Inscription :  register (SMS) → register/verify → register/complete (mot de passe) → écran de connexion
Connexion   :  login (numéro + mot de passe) → jeton → envoi de relais possible tout de suite
Oubli       :  auth/otp (SMS) → auth/verify → jeton → me/password (sans l’ancien, 15 min)
Relais      :  relais (contenu + liste des fichiers) → envoi des fichiers → soumis automatiquement
Commissions :  plus tard, dans le profil : identity (identité, commune, pièce, selfie, photo) → identity/submit → vérifiée par l’équipe
```

---

## 1. Inscription (3 appels)

L’inscription demande le numéro, le code SMS et le mot de passe ; le nom, les prénoms, le genre et la date de naissance peuvent être saisis au même moment (facultatifs). La pièce, la commune, le selfie et la photo se complètent plus tard dans le profil (5).

### 1.1 Numéro : envoi du code par SMS

```bash
curl -X POST "$BASE/auth/register" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001"}'
```

| Réponse | Corps | Écran |
|---|---|---|
| **202** | `{"message": "Un code de vérification vient d’être envoyé par SMS à ce numéro.", "meta": {"expires_in_seconds": 300}}` | Saisie du code |
| **422** `phone` | « Ce numéro a déjà un compte. Connectez-vous avec votre mot de passe. » | Proposer la connexion |
| **422** `phone` | « Le numéro de téléphone n’est pas valide. » | Corriger le numéro |
| **429** | En-tête `Retry-After` | Plus de 3 codes en 15 minutes : patienter |
| **503** | « L’envoi du SMS a échoué… » ou « Le service d’envoi des codes est très sollicité… » : afficher `message` | Réessayer après `Retry-After` |

### 1.2 Confirmation du code

```bash
curl -X POST "$BASE/auth/register/verify" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001","code":"4821"}'
```

| Réponse | Corps |
|---|---|
| **200** | `{"message": "Numéro vérifié. Complétez votre inscription.", "meta": {"expires_in_seconds": 1800}}` |
| **422** `code` | « Code invalide ou expiré. » (après 5 essais, redemander un code) |
| **422** `phone` | Numéro déjà inscrit |

Le numéro reste vérifié **30 minutes** : le temps de choisir le mot de passe.

### 1.3 Mot de passe et identité

```bash
curl -X POST "$BASE/auth/register/complete" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001","password":"MotDePasse2026","password_confirmation":"MotDePasse2026",
       "last_name":"Traoré","first_names":"Awa","gender":"femme","birth_date":"26/04/2000"}'
```

| Champ | Règle |
|---|---|
| `phone` | Le numéro vérifié en 1.2, dans les 30 minutes |
| `password` + `password_confirmation` | Au moins 8 caractères, identiques |
| `last_name`, `first_names` | Facultatifs (`first_name` est aussi accepté) |
| `gender` | Facultatif : `femme` ou `homme` |
| `birth_date` | Facultatif : **JJ/MM/AAAA** (`26/04/2000`) ; `2000-04-26` est aussi accepté ; âge minimum fixé par l’administration |

**201** — compte **actif**, Relais (numéro JR-… et badge). **Aucun jeton** : ouvrir l’écran de connexion (2).
```json
{
  "message": "Inscription terminée. Connectez-vous avec votre numéro et votre mot de passe.",
  "data": { "uuid": "9d6a1c2e-…", "status": "active" }
}
```

| Erreur 422 | Message |
|---|---|
| `phone` | « Ce numéro n’a pas été vérifié ou sa vérification a expiré. Recommencez l’inscription depuis le début. » |
| `password` | « Le mot de passe doit contenir au moins 8 caractères. » ou « Les deux mots de passe ne correspondent pas. » |
| `birth_date` | « La date de naissance doit être au format JJ/MM/AAAA, par exemple 26/04/2000. », date impossible, ou âge minimum non atteint |
| `gender` | Valeur autre que `femme` ou `homme` |

L’identité saisie ici pré-remplit le profil (5).

---

## 2. Connexion

Dès la fin de l’inscription. Une fois connecté, le Relais peut envoyer des relais tout de suite.

```bash
curl -X POST "$BASE/auth/login" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001","password":"MotDePasse2026"}'
```

Facultatif : `device_name`, `platform` (`android` ou `ios`), `app_version`. Sans eux, l’appareil s’appelle « Appareil mobile » et la plateforme est déduite de l’en-tête `User-Agent`.

**201**
```json
{
  "data": {
    "token": "12|Kq0nT3…",
    "token_type": "Bearer",
    "expires_at": "2027-01-10T10:15:00Z",
    "is_new_user": false,
    "password_required": false,
    "device": { "uuid": "0b1c…", "name": "Appareil mobile", "platform": "android", "app_version": null, "push_enabled": false, "last_seen_at": "2026-11-11T10:15:00Z" },
    "user": {
      "uuid": "9d6a1c2e-…",
      "first_name": "Awa",
      "last_name": "Traoré",
      "gender": "femme",
      "birth_date": "2000-04-26",
      "phone_masked": "+22507 •• •• •• 01",
      "email": null,
      "status": "active",
      "roles": ["relais"],
      "has_password": true,
      "relais": {
        "number": "JR-000003",
        "display_name": "Awa T.",
        "status": "active",
        "commune": null,
        "localite": null,
        "activated_at": "2026-11-12T10:00:00Z"
      },
      "identity_status": null,
      "gains_eligible": false,
      "created_at": "2026-11-12T10:00:00Z"
    }
  }
}
```

| Erreur | Message | Écran |
|---|---|---|
| **422** `phone` | « Identifiants incorrects. » | Rester sur la connexion |
| **429** | « Trop de mots de passe erronés. Réessayez dans 15 minutes ou redéfinissez votre mot de passe par SMS. » | Proposer « Mot de passe oublié » |

`password_required: true` ne concerne que les comptes créés sur le site web, sans mot de passe : ouvrir l’écran « Choisissez votre mot de passe » (3.3). En attendant, les autres routes protégées répondent **403** `{"message": "Choisissez votre mot de passe pour continuer.", "code": "password_required"}`.

---

## 3. Mot de passe oublié

### 3.1 Code par SMS

```bash
curl -X POST "$BASE/auth/otp" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001"}'
```

**202** `{"message": "Si ce numéro est valide, un code de vérification vient de lui être envoyé par SMS.", "meta": {"expires_in_seconds": 300}}` — même réponse que le numéro soit inscrit ou non. **503** si l’envoi du SMS échoue.

### 3.2 Validation du code

```bash
curl -X POST "$BASE/auth/verify" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001","code":"4821"}'
```

**201** : même réponse que la connexion (2). **422** `code` : code faux ou expiré, ou « Aucun compte n’est associé à ce numéro. Inscrivez-vous d’abord. »

### 3.3 Nouveau mot de passe

Dans les **15 minutes** qui suivent 3.2, sans l’ancien mot de passe :

```bash
curl -X PUT "$BASE/me/password" -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"password":"NouveauSecret!8","password_confirmation":"NouveauSecret!8"}'
```

Changement ordinaire (depuis les réglages) : ajouter `"current_password": "MotDePasse2026"`.

**204** sans contenu. **422** `current_password` : « Le mot de passe est incorrect. » ; **422** `password` : règles de 1.3.

---

## 4. Compte et déconnexion

```bash
curl "$BASE/me" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
curl -X POST "$BASE/auth/logout" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

`GET /me` (200) : mêmes champs que `user` en 2 : `first_name` et `last_name` séparés (pas de `name`), `gender`, `birth_date`, profil `relais` (numéro, nom affiché, statut, commune), `identity_status` et `gains_eligible`. Les champs non renseignés valent `null`. `logout` : **204**.

**401** `{"message": "Unauthenticated."}` sur n’importe quelle route : jeton expiré ou révoqué → revenir à l’écran de connexion.

---

## 5. Profil et vérification d’identité (pour les commissions)

À compléter **quand le Relais le souhaite** : obligatoire seulement pour recevoir ses commissions. Ni la connexion ni l’envoi de relais n’en dépendent. Le profil peut se remplir en plusieurs fois.

### 5.0 Pièces d’identité acceptées

```bash
curl "$BASE/auth/register/options" -H "Accept: application/json"
```

**200** (public, mis en cache 5 minutes). **La liste est gérée par l’administration** (console → Référentiels → Pièces d’identité) : une pièce peut être ajoutée ou retirée à tout moment. Ne pas la coder en dur : l’afficher telle que reçue, et demander les fichiers indiqués par `sides`.
```json
{
  "data": [
    { "value": "cni", "label": "Carte nationale d’identité (CNI)", "sides": ["recto", "verso"] },
    { "value": "passeport", "label": "Passeport", "sides": ["page"] },
    { "value": "permis", "label": "Permis de conduire", "sides": ["recto", "verso"] },
    { "value": "carte_consulaire", "label": "Carte consulaire", "sides": ["recto", "verso"] },
    { "value": "attestation_identite", "label": "Attestation d’identité", "sides": ["page"] }
  ],
  "meta": { "accepted_mime_types": ["image/jpeg", "image/png", "image/heic", "image/heif", "application/pdf"], "max_file_kb": 10240 }
}
```

### 5.1 État et éléments manquants

```bash
curl "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

**200**
```json
{
  "data": {
    "status": "draft",
    "status_label": "À compléter",
    "is_editable": true,
    "gains_eligible": false,
    "last_name": null,
    "first_name": null,
    "gender": null,
    "birth_date": null,
    "commune": null,
    "localite": null,
    "document_type": null,
    "required_files": ["selfie", "profile_photo"],
    "files": [],
    "missing": {
      "last_name": "Indiquez votre nom.",
      "first_names": "Indiquez vos prénoms.",
      "gender": "Indiquez votre sexe.",
      "birth_date": "Indiquez votre date de naissance.",
      "commune": "Choisissez votre commune.",
      "document_type": "Choisissez le type de votre pièce d’identité.",
      "files.selfie": "Ajoutez : Selfie avec la pièce.",
      "files.profile_photo": "Ajoutez : Photo de profil."
    },
    "decision_comment": null,
    "document_types": [ "… (comme 5.0)" ]
  }
}
```

Statuts : `draft` (à compléter) · `submitted` (en cours de vérification) · `verified` (vérifiée, `gains_eligible: true`) · `rejected` (à corriger selon `decision_comment`).

### 5.2 Identité, commune ou localité, type de pièce (mise à jour partielle)

Seuls les champs envoyés sont modifiés : chaque écran du profil peut envoyer uniquement ses champs.

```bash
curl -X PUT "$BASE/identity" -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"last_name":"Traoré","first_names":"Awa","gender":"femme","birth_date":"2001-04-17",
       "commune":"<uuid>","localite":"<uuid>","document_type":"cni"}'
```

| Champ | Règle |
|---|---|
| `last_name`, `first_names` | Texte |
| `gender` | `femme` ou `homme` |
| `birth_date` | **JJ/MM/AAAA** (`26/04/2000`) ou `AAAA-MM-JJ` ; âge minimum fixé par l’administration |
| `commune`, `localite` | uuid **renvoyés par** `GET /communes` et `GET /communes/{commune}/localites` (5.2 bis), jamais les exemples du Swagger ; la localité doit appartenir à la commune envoyée (sinon 422) ; une localité seule suffit, sa commune est déduite |
| `document_type` | Une `value` de 5.0 (pièce active) ; changer de type retire les faces devenues inutiles |

**200** : même forme que 5.1. **422** : champ invalide. **409** si la vérification est envoyée ou déjà vérifiée.

### 5.2 bis Communes et localités (référentiels publics)

La **localité** est le quartier, le village ou la sous-préfecture où vit le Relais, à l’intérieur de sa commune. Elle est facultative : la commune suffit. Écran conseillé : choisir la commune, puis (facultatif) la localité.

```bash
curl "$BASE/communes" -H "Accept: application/json"
curl "$BASE/communes/<uuid de la commune>/localites" -H "Accept: application/json"
```

**200** (public, mis en cache 5 minutes)
```json
{
  "data": [
    { "uuid": "01a1…", "name": "Abobo-Gare", "type": "quartier" },
    { "uuid": "01a1…", "name": "Anonkoua-Kouté", "type": "village" }
  ]
}
```

`type` : `quartier`, `village` ou `sous_prefecture`. Liste vide si l’administration n’a pas encore saisi de localité pour cette commune : n’afficher alors que la commune. **404** si la commune est fermée aux Relais.

### 5.3 Fichiers (multipart)

```bash
curl -X POST "$BASE/identity/files" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "kind=selfie" -F "file=@selfie.jpg"
```

`kind` : `recto`, `verso`, `page` (image ou PDF) ; `selfie`, `profile_photo` (image seulement). 10 Mo maximum. **201** : le fichier (`uuid`, `kind`, `label`, `mime`, `size`, `uploaded_at`). **422** `kind` : face non demandée pour cette pièce ; **422** `file` : format, taille ou antivirus. Retirer : `DELETE /identity/files/{kind}` (**204**).

### 5.4 Envoi à l’équipe

```bash
curl -X POST "$BASE/identity/submit" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

**200** : `status: "submitted"`. **422** : chaque élément manquant dans `errors`. Après décision : `verified` (éligible aux commissions) ou `rejected` (corriger puis renvoyer).

---

## 6. Envoyer un relais (un seul appel, pas de brouillon)

### 6.1 Contenu et liste des fichiers

```bash
curl -X POST "$BASE/relais" -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "client_uuid": "01a1c6a8-6f00-7000-8000-000000000001",
    "category": "<uuid de GET /categories>",
    "title": "Route coupée au carrefour Siporex",
    "description": "L’eau arrive aux genoux.",
    "urgency": "urgent",
    "location": {"lat": 5.341, "lng": -4.069, "accuracy_m": 12},
    "declared_commune": "<uuid de GET /communes>",
    "protections": {"has_minors": false, "people_consent": "not_applicable", "credit_mode": "anonymous"},
    "medias": [
      {"client_uuid": "01a1c6a8-6f00-7000-8000-0000000000a1", "kind": "video", "mime": "video/mp4",
       "size": 48211337, "sha256": "e3b0c442…"}
    ]
  }'
```

| Champ | Règle |
|---|---|
| `client_uuid` | Généré par l’application : renvoyer le même rend le même relais (reprise sans doublon) |
| `category`, `title` | Obligatoires |
| `location` ou `declared_commune` | Au moins l’un des deux ; le GPS prime pour choisir la commune |
| `protections` | `has_minors` (oui/non), `people_consent` (`yes`, `no`, `not_applicable`), `credit_mode` (`name`, `pseudonym`, `anonymous`), `credit_name` si pseudonyme |
| `medias` | 1 à 10 fichiers : `client_uuid`, `kind` (`photo`, `video`, `audio`), `mime`, `size` (octets), `sha256` |
| `appel` | Facultatif : `uuid` de l’appel à relais auquel on répond |

**201** — relais créé (`status: "uploading"`) et, dans `uploads`, les URL d’envoi de chaque fichier :
```json
{
  "data": { "uuid": "…", "status": "uploading", "status_label": "Envoi en cours", "title": "Route coupée au carrefour Siporex", "…": "…" },
  "uploads": [
    {
      "uuid": "<uuid du fichier>",
      "status": "pending",
      "upload": {
        "part_size": 8388608,
        "part_count": 6,
        "missing_parts": [ { "number": 1, "url": "https://…" }, { "number": 2, "url": "https://…" } ]
      }
    }
  ]
}
```

**422** : contenu incomplet ou fichier refusé, rien n’est créé (`errors` : `category`, `title`, `location`, `declared_commune`, `protections.*`, `medias`, `medias.0.size`…). **409** : `client_uuid` déjà utilisé par un autre compte. **403** : Relais suspendu.

### 6.2 Envoi des fichiers (directement vers le stockage, reprenable)

Pour chaque fichier de `uploads` :
1. Découper le fichier en morceaux de `part_size` octets et envoyer chacun par `PUT` brut sur son URL (`missing_parts`), **sans** en-tête `Authorization`. Garder l’en-tête `ETag` de chaque réponse.
2. Après une coupure : `GET /medias/{uuid}` redonne les morceaux reçus et des URL neuves pour les manquants.
3. Clore : `POST /medias/{uuid}/complete` avec `{"parts": [{"number": 1, "etag": "…"}, …]}`.

**Quand le dernier fichier est clos, le relais est soumis automatiquement** (`status: "received"`, en vérification) : aucun autre appel. Suivi : `GET /relais` et `GET /relais/{uuid}`.

### 6.3 À quoi servent les routes de fichiers

| Route | Rôle |
|---|---|
| `GET /medias/{uuid}` | État d’un fichier : morceaux reçus et nouvelles URL après une coupure ; puis contrôle (`processing`, `ready`, `rejected` avec la raison) |
| `POST /medias/{uuid}/complete` | Clôt l’envoi d’un fichier ; le dernier déclenche la soumission du relais |
| `POST /relais/{uuid}/medias` | Ajoute un fichier à un relais existant, quand le coordonnateur demande un **complément** ; puis `POST /relais/{uuid}/complement` |

## 7. Appels à relais

```bash
curl "$BASE/appels" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

**200** — appels ouverts qui concernent le Relais : commune de son profil, sinon commune de sa vérification d’identité ou de ses relais récents ; pour un rayon, aussi ses relais géolocalisés dans ce rayon.
```json
{
  "data": [
    {
      "uuid": "…",
      "title": "Rentrée scolaire",
      "instructions": "Ambiance devant les écoles, sans filmer les enfants de face.",
      "target": "communes",
      "target_label": "Communes",
      "zone": ["Abobo", "Cocody"],
      "zone_label": "Communes : Abobo, Cocody",
      "radius": null,
      "lieu_type": "École",
      "is_open": true,
      "deadline_at": "2026-11-14T12:00:00Z",
      "published_at": "2026-11-13T09:00:00Z"
    }
  ]
}
```

| Champ | Usage |
|---|---|
| `zone_label` | Zone en clair, prête à afficher |
| `radius` | Pour un appel « rayon » : `{"lat", "lng", "radius_km"}` (carte) ; sinon `null` |
| `lieu_type` | Type de lieu à couvrir (école, marché…) ou `null` |

Détail : `GET /appels/{uuid}` (404 si l’appel ne concerne pas le Relais). Pour répondre : `POST /relais` avec `"appel": "<uuid>"`. Un appel peut être modifié par l’équipe (sujet, zone, date limite) : recharger la liste à l’ouverture de l’écran.

---

## Récapitulatif des codes HTTP

| Code | Signification | Que faire |
|---|---|---|
| 200 / 201 / 202 / 204 | Succès | — |
| 401 | Jeton absent, expiré ou révoqué | Écran de connexion |
| 403 `password_required` | Compte sans mot de passe | Écran « Choisissez votre mot de passe » |
| 403 | Action non permise | Afficher le message |
| 409 | Vérification d’identité verrouillée | Recharger `GET /identity` |
| 422 | Saisie invalide | Afficher chaque message sous son champ (messages en français, prêts à afficher) |
| 429 | Trop de tentatives (`Retry-After`) | Patienter |
| 503 | SMS ou antivirus momentanément indisponible (`Retry-After`) | Réessayer plus tard |
