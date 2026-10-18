# Règles de sécurité obligatoires

Référentiels : OWASP ASVS 4.0 niveau 2, OWASP API Security Top 10 (2023), loi ivoirienne n° 2013-450 (ARTCI), obligations HACA.

## Authentification
- OTP : 6 chiffres, stocké **haché**, 5 min, 5 essais puis blocage ; `RateLimiter` 3 envois / 15 min par numéro + limite par IP.
- Mots de passe : `Password::min(10)->uncompromised()`, Argon2id.
- Console et back-office : 2FA TOTP obligatoire (Fortify), codes de secours, session expirée après 30 min d'inactivité.
- Sanctum : un jeton par appareil, abilities limitées, expiration, révocation à distance.
- Cookies `secure`, `httponly`, `samesite=lax` ; régénération de session à la connexion.

## Autorisation
- Policy sur chaque modèle/action, appelée explicitement (`Gate::authorize`, middleware `can:`).
- `CommuneScope` pour coordonnateurs/superviseurs sur `RelaisItem`, `Candidature`, `Relais`.
- UUID dans les URL, jamais d'ID séquentiel.
- Matrice Annexe A couverte par des tests (datasets Pest).
- Option défense en profondeur : Row Level Security PostgreSQL.

## Entrées / sorties
- Form Request partout ; `$fillable` sur tous les modèles ; pas de `DB::raw` avec saisie utilisateur.
- Blade `{{ }}` ; `{!! !!}` interdit sur contenu utilisateur.
- En-têtes : CSP stricte, HSTS, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`.
- `APP_DEBUG=false` en production.

## Fichiers et médias
- Type vérifié sur le contenu (magic bytes) ; liste blanche JPEG, PNG, HEIC, MP4, MOV, M4A, MP3, AAC ; taille max paramétrable (500 Mo).
- ClamAV avant mise à disposition ; quarantaine si infecté.
- Nom = UUID ; S3 privé chiffré ; URL signées 5–15 min.
- SHA-256 à la réception, inscrit dans l'audit.
- Versions allégées sans EXIF ; original intact.
- Pièces d'identité / selfies / autorisations parentales sur un disque séparé ; consultation auditée.

## Données sensibles
- Cast `encrypted` (n° de pièce, adresse, documents sensibles).
- Téléphone : empreinte HMAC pour recherche/doublons.
- Rotation `APP_KEY` via `APP_PREVIOUS_KEYS`.
- Jamais dans les logs : téléphone, OTP, jeton, GPS précis, n° de pièce.

## Temps réel / API
- Canaux Reverb privés autorisés par commune et rôle (`routes/channels.php`).
- Rate limiting par route sensible ; idempotence `client_uuid`.
- Pagination obligatoire ; champs filtrés par API Resource.

## Éditorial
- Export d'un relais non validé bloqué côté serveur ; dérogation admin avec motif obligatoire + audit (ADM-12).

## Base de données
- Rôle `app` sans DDL, rôle `migrator` pour les migrations.
- `audit_logs` : `REVOKE UPDATE, DELETE` + trigger qui lève une exception ; chaîne d'empreintes (`prev_hash`).

## Données personnelles (ARTCI)
- Minimisation ; GPS jamais public (stats à la commune) ; consentements versionnés horodatés.
- Suppression de compte = pseudonymisation (l'audit reste).
- Purge planifiée selon durées paramétrables, elle-même auditée.
- Pas de vraies données personnelles hors production.
