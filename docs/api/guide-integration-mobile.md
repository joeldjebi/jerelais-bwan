# JE RELAIS — Guide d’intégration de l’application mobile

Inscription, connexion, mot de passe oublié et profil (vérification d’identité pour les commissions).
Référence complète de toutes les routes : Swagger en ligne `https://jerelais.com/docs/swagger` (accès fourni par l’équipe JE RELAIS) ou fichier autonome `docs/api/swagger.html`.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | `https://jerelais.com/api/v1` |
| En-têtes | `Accept: application/json` sur **toutes** les requêtes ; `Content-Type: application/json`, sauf `POST /identity` (multipart : champs + fichiers) |
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
Oubli       :  auth/otp (SMS) → auth/verify (jeton) → auth/password/reset (nouveau mot de passe, 15 min)
Relais      :  POST /relais (contenu + fichiers, une requête) → reçu ; PUT /relais/{uuid} pour le modifier
Commissions :  plus tard, dans le profil : POST /identity (identité, commune, pièce, photos en une requête) → vérifiée par l’équipe
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

Avec le jeton reçu en 3.2, **dans les 15 minutes**, sans l’ancien mot de passe :

```bash
curl -X POST "$BASE/auth/password/reset" -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"password":"NouveauSecret!8","password_confirmation":"NouveauSecret!8"}'
```

**200** `{"message": "Mot de passe enregistré. Utilisez-le désormais pour vous connecter."}` — le Relais reste connecté sur cet appareil ; ses autres appareils sont déconnectés. **422** `password` : règles de 1.3. **403** : jeton qui ne vient pas d’un code SMS validé, ou plus de 15 minutes écoulées → recommencer en 3.1.

Changer son mot de passe depuis les réglages (en connaissant l’ancien) : `PUT /me/password` avec `current_password`, `password`, `password_confirmation` (**204**).

---

## 4. Compte et déconnexion

```bash
curl "$BASE/me" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
curl -X POST "$BASE/auth/logout" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

`GET /me` (200) : mêmes champs que `user` en 2 : `first_name` et `last_name` séparés (pas de `name`), `gender`, `birth_date`, profil `relais` (numéro, nom affiché, statut, commune), `identity_status` et `gains_eligible`. Les champs non renseignés valent `null`. `logout` : **204**.

**401** `{"message": "Unauthenticated."}` sur n’importe quelle route : jeton expiré ou révoqué → revenir à l’écran de connexion.

---

## 5. Vérification d’identité (pour les commissions) — une seule requête

Facultative : nécessaire seulement pour recevoir les commissions. Ni la connexion ni l’envoi de relais n’en dépendent.

**Le principe :** l’écran du profil réunit tout (identité, commune, pièce, photos), puis l’application envoie **une seule requête** `POST /identity`. Le dossier part aussitôt chez l’administration (`submitted`), qui le valide (`verified`) ou le refuse (`rejected`, avec un motif).

```
Écran profil (listes 5.1 et 5.2) → POST /identity (tout en une fois) → submitted → verified ou rejected
```

### 5.1 Listes pour l’écran : pièces acceptées

```bash
curl "$BASE/auth/register/options" -H "Accept: application/json"
```

**200** (public). Liste **gérée par l’administration** : l’afficher telle que reçue. `sides` indique les photos de la pièce à demander.
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

### 5.2 Listes pour l’écran : communes et localités

```bash
curl "$BASE/communes" -H "Accept: application/json"
curl "$BASE/communes/<uuid de la commune>/localites" -H "Accept: application/json"
```

La **localité** (quartier, village, sous-préfecture) est facultative : la commune suffit. Liste vide si l’administration n’en a pas encore saisi. Utiliser **les uuid renvoyés par ces routes**, jamais les exemples du Swagger.

### 5.3 Envoyer la vérification (une seule requête)

**L’écran suit le type de pièce choisi** : l’utilisateur choisit d’abord `document_type` dans la liste 5.1, puis l’application affiche les photos demandées par ses `sides` :

| `sides` de la pièce | Photos à demander |
|---|---|
| `["recto", "verso"]` (CNI, permis, carte consulaire…) | `recto` et `verso` |
| `["page"]` (passeport, attestation…) | `page` |

Plus, toujours, le `selfie`. Requête **multipart/form-data** (champs texte + fichiers) :

```bash
curl -X POST "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "last_name=Traoré" \
  -F "first_name=Abou" \
  -F "gender=homme" \
  -F "birth_date=26/04/2000" \
  -F "commune=<uuid de GET /communes>" \
  -F "localite=<uuid de GET /communes/{commune}/localites>" \
  -F "document_type=cni" \
  -F "recto=@recto.jpg" \
  -F "verso=@verso.jpg" \
  -F "selfie=@selfie.jpg"
```

| Champ | Obligatoire | Règle |
|---|---|---|
| `last_name` | oui | Nom |
| `first_name` (ou `first_names`) | oui | Prénoms |
| `gender` | oui | `femme` ou `homme` |
| `birth_date` | oui | `26/04/2000` (ou `2000-04-26`) ; âge minimum fixé par l’administration |
| `commune` | oui, sauf si `localite` | uuid de `GET /communes` |
| `localite` | non | uuid de `GET /communes/{commune}/localites`, de la même commune |
| `document_type` | oui | Une `value` de 5.1 |
| `recto` + `verso` | si `sides` = recto, verso | Photo (ou PDF) de chaque face ; refusées pour une pièce à une page |
| `page` | si `sides` = page | Photo (ou PDF) de la page ; refusée pour une pièce recto-verso |
| `selfie` | oui | Photo de la personne tenant sa pièce, visage bien visible ; sert aussi de **photo du badge** |

Fichiers : JPEG, PNG, HEIC (et PDF pour la pièce), **10 Mo maximum** chacun.

**200** — dossier envoyé à l’équipe :
```json
{
  "data": {
    "status": "submitted",
    "status_label": "En cours de vérification",
    "is_editable": false,
    "gains_eligible": false,
    "last_name": "Traoré",
    "first_name": "Abou",
    "gender": "homme",
    "birth_date": "2000-04-26",
    "commune": { "uuid": "…", "name": "Abobo" },
    "localite": { "uuid": "…", "name": "Abatta" },
    "document_type": "cni",
    "files": [ { "kind": "recto", "…": "…" }, { "kind": "verso" }, { "kind": "selfie" } ],
    "missing": {},
    "decision_comment": null,
    "submitted_at": "2026-11-13T15:00:00Z"
  }
}
```

| Erreur | Signification | Écran |
|---|---|---|
| **422** | Champ ou fichier manquant ou invalide : **rien n’est enregistré**. `errors` donne un message par champ (`recto` : « Ajoutez : Recto de la pièce. », `localite` : « Cette localité n’appartient pas à la commune choisie. »…) | Afficher chaque message sous son champ, puis renvoyer |
| **409** | Dossier déjà en cours de vérification ou déjà vérifié | Afficher le statut (5.4) |
| **503** | Antivirus momentanément indisponible | Réessayer plus tard |

### 5.4 Suivre le statut

```bash
curl "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

| `status` | Écran |
|---|---|
| `draft` | Rien d’envoyé : afficher le formulaire (5.3) |
| `submitted` | « En cours de vérification » |
| `verified` | « Identité vérifiée » ; `gains_eligible: true` |
| `rejected` | Afficher `decision_comment`, puis **renvoyer la même requête 5.3 corrigée** |

Le statut est aussi dans `GET /me` (`identity_status`, `gains_eligible`).

### 5.5 Modifier (tout est facultatif)

`PUT /identity` accepte **les mêmes champs et photos que 5.3, tous facultatifs** : seuls les éléments envoyés **et non vides** changent, le reste est conservé (un champ envoyé vide ne l’efface pas). Possible tant que le statut est `draft` ou `rejected` (sinon **409**).

```bash
# Corriger un dossier refusé : nouveau selfie seulement
curl -X PUT "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "selfie=@selfie.jpg"

# Changer de pièce : passer au passeport avec sa page (les photos recto/verso sont retirées)
curl -X PUT "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "document_type=passeport" -F "page=@passeport.jpg"

# Un champ texte seulement (JSON accepté quand il n’y a pas de photo)
curl -X PUT "$BASE/identity" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"first_name":"Abou Mariam"}'
```

- Les photos de la pièce doivent correspondre au type de pièce (celui envoyé, sinon celui déjà enregistré) : sinon **422** (« « Page de la pièce » n’est pas demandé pour « Carte nationale d’identité (CNI) » »).
- **Dès que le dossier devient complet, il part aussitôt en vérification** (`status: "submitted"`), comme avec 5.3.
- Réponse **200** : même forme que 5.3.

---

## 6. Envoyer un relais (une seule requête)

### 6.1 Envoyer : contenu et fichiers ensemble

Requête **multipart/form-data**. Le relais est **aussitôt envoyé au coordonnateur** (`status: "received"`).

```bash
curl -X POST "$BASE/relais" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "category=vie-de-quartier" \
  -F "title=Route coupée au carrefour Siporex" \
  -F "description=L’eau arrive aux genoux." \
  -F "urgency=urgent" \
  -F "lat=5.341" -F "lng=-4.069" -F "accuracy_m=12" \
  -F "has_minors=false" -F "people_consent=not_applicable" -F "credit_mode=anonymous" \
  -F "files[]=@video.mp4" \
  -F "files[]=@photo.jpg"
```

| Champ | Obligatoire | Règle |
|---|---|---|
| `category` | oui | Catégorie : `uuid` (ou `slug`, ex. `vie-de-quartier`) de `GET /categories` |
| `title` | oui | 120 caractères maximum |
| `description` | non | 2 000 caractères maximum |
| `urgency` | non | `normal` (par défaut), `important`, `urgent` |
| `lat` + `lng` (`accuracy_m`) | oui, sauf `declared_commune` | Position GPS ; elle prime pour choisir la commune |
| `declared_commune` | si pas de GPS | **Commune où s’est passé l’événement**, choisie par le Relais : `uuid` (ou `slug`, ex. `abobo`) de `GET /communes`. Le relais part au coordonnateur de cette commune ; avec le GPS, c’est la position qui décide |
| `declared_place` | non | Lieu en clair |
| `has_minors` | oui | `true` ou `false` |
| `people_consent` | oui | `yes`, `no`, `not_applicable` |
| `credit_mode` | oui | `anonymous`, `name`, `pseudonym` (+ `credit_name` si pseudonyme) |
| `appel` | non | **Réponse à un appel à relais** : quand la rédaction demande de couvrir un sujet (`GET /appels`), `uuid` de cet appel ; le relais y est rattaché et remonte en priorité. Vide pour un relais spontané |
| `files[]` | oui | 1 à 10 fichiers : photos JPEG/PNG/HEIC, vidéos MP4/MOV, sons M4A/MP3/AAC — **100 Mo maximum chacun** |
| `client_uuid` | non | **Généré automatiquement** s’il est absent. L’application peut en fournir un (uuid créé sur le téléphone) pour qu’un renvoi après une coupure ne crée pas de doublon |

Les champs laissés vides sont ignorés. **201** :
```json
{
  "data": {
    "uuid": "…", "status": "received", "status_label": "Reçu", "title": "Route coupée au carrefour Siporex",
    "urgency": "urgent", "commune": { "uuid": "…", "name": "Yopougon" },
    "medias": [ { "uuid": "…", "kind": "video", "status": "uploaded" }, { "uuid": "…", "kind": "photo", "status": "uploaded" } ]
  }
}
```

Chaque fichier est ensuite contrôlé en arrière-plan (antivirus, durée) : `medias.N.status` passe à `ready`, ou `rejected` avec la raison (`GET /relais/{uuid}`).

| Erreur | Signification |
|---|---|
| **422** | Champ ou fichier invalide : **rien n’est créé** ; `errors` donne un message par champ (`title`, `declared_commune`, `protections.credit_name`, `files.0` : « Ce fichier dépasse 100 Mo : envoyez-le en morceaux… ») |
| **403** | Relais suspendu |
| **409** | `client_uuid` déjà utilisé par un autre compte |

### 6.2 Modifier un relais envoyé

`PUT /relais/{uuid}` : **mêmes champs, tous facultatifs** — seuls les éléments envoyés et non vides changent. Ajouter des fichiers : `files[]`. Retirer des fichiers : `remove_medias[]` (uuid de `medias.N.uuid`) ; il doit en rester au moins un.

```bash
# Corriger le titre et ajouter une photo
curl -X PUT "$BASE/relais/<uuid>" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "title=Route coupée à Siporex" -F "files[]=@plan-large.jpg"

# Retirer un fichier
curl -X PUT "$BASE/relais/<uuid>" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "remove_medias[]=<uuid du média>"
```

| `status` du relais | Modification |
|---|---|
| `received` (pas encore pris en charge par le coordonnateur) | Possible ; un nouveau lieu peut changer la commune |
| `complement_requested` | Possible : **la modification sert de réponse** (`message` facultatif) et le relais revient dans la file (`received`) |
| `in_review`, `validated`, `rejected`, … | **409** : plus modifiable |

**200** : le relais à jour. **422** : champ ou fichier invalide, ou requête vide.

### 6.3 Vidéos de plus de 100 Mo : envoi en morceaux (facultatif)

Pour une vidéo lourde ou un réseau instable, l’envoi en morceaux reprend là où il s’est arrêté après une coupure. `POST /relais` en **JSON** avec la liste des fichiers (`medias`) au lieu de `files[]` :

#### Annonce du relais et de ses fichiers (JSON)

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

#### Envoi des morceaux

Pour chaque fichier de `uploads` :
1. Découper le fichier en morceaux de `part_size` octets et envoyer chacun par `PUT` brut sur son URL (`missing_parts`), **sans** en-tête `Authorization`. Garder l’en-tête `ETag` de chaque réponse.
2. Après une coupure : `GET /medias/{uuid}` redonne les morceaux reçus et des URL neuves pour les manquants.
3. Clore : `POST /medias/{uuid}/complete` avec `{"parts": [{"number": 1, "etag": "…"}, …]}`.

**Quand le dernier fichier est clos, le relais est soumis automatiquement** (`status: "received"`, en vérification) : aucun autre appel. Suivi : `GET /relais` et `GET /relais/{uuid}`.

#### Routes de fichiers

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
