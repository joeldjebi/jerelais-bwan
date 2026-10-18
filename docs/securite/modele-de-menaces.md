# Modèle de menaces (STRIDE) — v1, étape 0

Périmètre : plateforme web et API JE RELAIS (dépôt Laravel). L'application mobile a son propre modèle (OWASP MASVS, équipe mobile).
Révision obligatoire en fin d'étape 3 (cœur métier) et avant le test d'intrusion (S6).

## Actifs à protéger

| Actif | Sensibilité | Où |
|---|---|---|
| Pièces d'identité, selfies, autorisations parentales | Critique | Disque `identity` (Wasabi, privé) |
| Positions GPS précises des Relais et des relais | Élevée | PostgreSQL (PostGIS) |
| Numéros de téléphone, identité des Relais | Élevée | PostgreSQL (chiffré / HMAC) |
| Médias originaux et leur empreinte SHA-256 | Élevée (preuve, HACA) | Disque `medias` + audit |
| Décisions de validation, dérogations d'export | Élevée (traçabilité) | `audit_logs` append-only |
| Comptes console (administrateurs, rédaction) | Critique | Fortify + 2FA |

## Flux critiques et menaces

### 1. Inscription et connexion par OTP SMS

| STRIDE | Menace | Contre-mesure | Étape |
|---|---|---|---|
| S | Usurpation d'un numéro, force brute du code | OTP haché, 5 min, 5 essais, verrouillage temporaire | 1 |
| D | Envoi massif de SMS (coût, épuisement du crédit) | Limite 3 envois / 15 min par numéro + limite par IP | 1 |
| I | Énumération des numéros inscrits | Réponse identique que le numéro existe ou non | 1 |
| R | Contestation d'une connexion | Connexions inscrites dans l'audit | 1 |

### 2. Envoi de médias

| STRIDE | Menace | Contre-mesure | Étape |
|---|---|---|---|
| T | Fichier piégé (polyglotte, exécutable renommé) | Type vérifié par magic bytes, liste blanche, ClamAV, nom UUID, stockage hors webroot | 3 |
| D | Fichiers géants, saturation du stockage | Taille max paramétrable, quotas, envoi fractionné contrôlé | 3 |
| T | Média modifié après réception | Empreinte SHA-256 à la réception, inscrite dans l'audit | 3 |
| I | Fuite de la position via les EXIF | Versions allégées sans EXIF ; original accessible aux seuls rôles habilités | 3 |
| S | Relais envoyé au nom d'un autre | Jeton Sanctum par appareil, rattachement serveur à l'utilisateur authentifié | 1–3 |

### 3. Validation par le coordonnateur

| STRIDE | Menace | Contre-mesure | Étape |
|---|---|---|---|
| E / I | Coordonnateur qui consulte ou valide une autre commune (IDOR) | Policies + `CommuneScope` + UUID + tests de refus | 1–3 |
| T | Décisions contradictoires | Verrou de 10 min | 3 |
| R | Contestation d'une décision | Audit append-only avec chaîne d'empreintes | 1 |

### 4. Exploitation éditoriale et export

| STRIDE | Menace | Contre-mesure | Étape |
|---|---|---|---|
| E | Export d'un relais non validé | Blocage serveur ; dérogation administrateur motivée et auditée | 4 |
| I | Lien de téléchargement partagé hors rédaction | URL signées de 5 à 15 min | 3–4 |

### 5. Console et temps réel

| STRIDE | Menace | Contre-mesure | Étape |
|---|---|---|---|
| S | Vol de session administrateur | 2FA TOTP obligatoire, cookies secure/httponly, expiration d'inactivité | 1 |
| I | Écoute d'un canal temps réel d'une autre commune | Canaux Reverb privés autorisés par commune et rôle | 3–4 |
| T | XSS dans un titre ou une description de relais | Échappement Blade, CSP (report-only puis bloquante en étape 5) | 0–5 |
| — | Clickjacking | `X-Frame-Options: DENY`, `frame-ancestors 'none'` | 0 ✅ |

## Mesures déjà en place (étape 0)

- En-têtes de sécurité sur toutes les réponses (`SecurityHeaders`), CSP en report-only.
- Identifiants publics UUID (`HasPublicUuid`), Eloquent strict hors production.
- Disques Wasabi privés séparés pour médias et pièces d'identité.
- Tests d'architecture : pas de fonctions de debug, preset sécurité Pest, `env()` hors config interdit.
- CI : Larastan niveau 8, `composer audit`, `npm audit`, Semgrep (OWASP Top 10, secrets).

## Risques résiduels à arbitrer

- Hébergement et transfert de données hors de Côte d'Ivoire (Wasabi : région à confirmer) — point ouvert n° 6.
- Durées de conservation non fixées — purge inactive tant que REFLET TV n'a pas tranché.
