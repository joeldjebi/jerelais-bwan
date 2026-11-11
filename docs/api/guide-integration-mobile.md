# JE RELAIS — Guide d’intégration de l’application mobile

Inscription, connexion, mot de passe oublié et profil (vérification d’identité pour les commissions).
Référence complète de toutes les routes : Swagger en ligne `https://jerelais.com/docs/swagger` (accès fourni par l’équipe JE RELAIS) ou fichier autonome `docs/api/swagger.html`.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | `https://jerelais.com/api/v1` |
| En-têtes | `Accept: application/json` sur **toutes** les requêtes ; `Content-Type: application/json`, sauf `identity/files` (multipart) |
| Numéro | 10 chiffres ivoiriens (`0700000001`, espaces acceptés) ou international (`+2250700000001`) |
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
Commissions :  plus tard, dans le profil : identity (identité, commune, pièce, selfie, photo) → identity/submit → vérifiée par l’équipe
```

---

## 1. Inscription (3 appels)

L’inscription ne demande **que le numéro, le code SMS et le mot de passe**. Le reste se complète plus tard dans le profil (5).

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
| **503** | « L’envoi du SMS a échoué. Réessayez dans quelques instants. » | Réessayer plus tard |

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

### 1.3 Mot de passe

```bash
curl -X POST "$BASE/auth/register/complete" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone":"0700000001","password":"MotDePasse2026","password_confirmation":"MotDePasse2026"}'
```

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
      "uuid": "9d6a1c2e-…", "name": "Awa Traoré", "phone_masked": "+22507 •• •• •• 01",
      "email": null, "status": "active", "roles": ["relais"], "has_password": true,
      "identity_status": "draft", "gains_eligible": false, "created_at": "2026-11-10T09:00:00Z"
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

`GET /me` (200) : mêmes champs que `user` en 2, dont `identity_status` et `gains_eligible`. `logout` : **204**.

**401** `{"message": "Unauthenticated."}` sur n’importe quelle route : jeton expiré ou révoqué → revenir à l’écran de connexion.

---

## 5. Profil et vérification d’identité (pour les commissions)

À compléter **quand le Relais le souhaite** : obligatoire seulement pour recevoir ses commissions. Ni la connexion ni l’envoi de relais n’en dépendent. Le profil peut se remplir en plusieurs fois.

### 5.0 Pièces d’identité acceptées

```bash
curl "$BASE/auth/register/options" -H "Accept: application/json"
```

**200** (public, mis en cache 5 minutes)
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
    "first_names": null,
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
| `birth_date` | `AAAA-MM-JJ` ; âge minimum fixé par l’administration |
| `commune`, `localite` | uuid de `GET /communes` et `GET /communes/{commune}/localites` ; une localité seule suffit |
| `document_type` | Une valeur de 5.0 ; changer de type retire les faces devenues inutiles |

**200** : même forme que 5.1. **422** : champ invalide. **409** si la vérification est envoyée ou déjà vérifiée.

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

## Récapitulatif des codes HTTP

| Code | Signification | Que faire |
|---|---|---|
| 200 / 201 / 202 / 204 | Succès | — |
| 401 | Jeton absent, expiré ou révoqué | Écran de connexion |
| 403 `password_required` | Compte sans mot de passe | Écran « Choisissez votre mot de passe » |
| 403 | Action non permise | Afficher le message |
| 409 | Vérification d’identité verrouillée | Recharger `GET /identity` |
| 422 | Saisie invalide | Afficher chaque message sous son champ |
| 429 | Trop de tentatives (`Retry-After`) | Patienter |
| 503 | SMS ou antivirus momentanément indisponible (`Retry-After`) | Réessayer plus tard |
