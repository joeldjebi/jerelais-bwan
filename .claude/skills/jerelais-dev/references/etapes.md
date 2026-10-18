# Étapes de développement (8 semaines)

Une étape n'est close que si son critère de sortie est démontré sur l'environnement de recette.

| Étape | Semaines | Modules | Jalon cahier des charges |
|---|---|---|---|
| 0. Cadrage et socle technique | S1 | — | Maquettes validées (fin S1) |
| 1. Socle | S2 | 1 à 5 | — |
| 2. Recrutement des Relais | S2–S3 | 6 à 9 | — |
| 3. Cœur métier | S3–S4 | 10 à 12 | Démo complète (fin S4) |
| 4. Interfaces métier | S4–S6 | 13 à 18 | — |
| 5. Site public, pilotage, conformité | S5–S6 | 19 à 22 | Version prête (fin S6), gel fonctionnel |
| Support API mobile | S1–S7 | 23 | — |
| 6. Recette et sécurité | S6–S7 | — | Applications publiées |
| 7. Pilote | S7 | — | Bilan du pilote |
| 8. Lancement national | S8 | — | JE RELAIS en service |

## Liste des modules

| N° | Module | Domaine (`app/Domain`) | Exigences |
|---|---|---|---|
| 1 | Authentification et comptes | `Auth` | MOB-01, MOB-31, ADM-24 |
| 2 | Rôles, droits, périmètre commune | `Access` | Annexe A |
| 3 | Référentiel géographique (PostGIS) | `Territoire` | §2.2, ADM-20 |
| 4 | Journal d'audit non modifiable | `Audit` | ADM-25, §10 |
| 5 | Paramétrage | `Settings` | ADM-23 |
| 6 | Candidatures | `Candidature` | MOB-02 à 06, WEB-02, COO-01 à 03 |
| 7 | Documents juridiques et consentements | `Legal` | MOB-04, §9 |
| 8 | Formation et quiz | `Formation` | MOB-05 |
| 9 | Profil Relais et badge | `Relais` | MOB-07, MOB-24, COO-04, ADM-22 |
| 10 | Relais (contenus), 10 états | `Contribution` | MOB-10 à 15, MOB-19, MOB-20, WEB-03 |
| 11 | Médias, téléversement, traitement | `Media` | MOB-16, MOB-18, WEB-04, §10 |
| 12 | Routage, verrou, validation, escalade | `Validation` | §8.2, COO-10 à 18 |
| 13 | Back-office coordonnateur | `Livewire/Coordinateur` | §6 |
| 14 | Console temps réel | `Livewire/Console` | ADM-01 à 05 |
| 15 | Exploitation éditoriale | `Editorial` | ADM-10 à 17 |
| 16 | Appels à relais | `AppelRelais` | §3.3, ADM-14, MOB-23, COO-21 |
| 17 | Messagerie | `Messagerie` | MOB-22, COO-20 |
| 18 | Notifications (push, SMS, in-app) | `Notification` | MOB-21, §12 |
| 19 | Site public et espace Relais web | `Livewire/Public`, `Livewire/Relais` | WEB-01 à 08 |
| 20 | Tableaux de bord et rapports | `Reporting` | §13, ADM-26 |
| 21 | Données personnelles, purges | `Legal` / `Settings` | §9.4 |
| 22 | Sécurité du Relais (révocation…) | `Auth` | MOB-30 à 33 (côté serveur) |
| 23 | API REST v1 | `Http/Controllers/Api/V1` | toutes MOB |
| 24 | Options lot 2 (Mobile Money, WhatsApp…) | — | après S8 |

## Détail et critères de sortie

### Étape 0 — Cadrage et socle (S1)
1. Compléter le projet Laravel 13 : Laravel Boost, Docker Compose (PostgreSQL+PostGIS, Redis, MinIO, Mailpit, ClamAV).
2. CI complète, Sentry, environnement de recette.
3. Structure `app/Domain`, tests d'architecture Pest.
4. Modélisation des menaces STRIDE, référent sécurité.
5. Modèle de données v1.
6. Import du référentiel des communes avec contours PostGIS.
7. Contrat OpenAPI v0 ; choix tus vs multipart S3 avec l'équipe mobile.
8. Maquettes back-office et console validées.

**Sortie** : projet déployé en recette, CI verte, maquettes validées, OpenAPI v0 accepté.

### Étape 1 — Socle (S2)
OTP SMS (`SmsGateway`), mot de passe, 2FA Fortify, jetons Sanctum par appareil ; rôles, `CommuneScope`, Policies ; CRUD référentiel ; audit append-only + trigger ; paramètres.

**Sortie** : OTP limité testé ; audit non modifiable prouvé ; refus entre communes testé.

### Étape 2 — Recrutement (S2–S3)
Candidature en étapes avec sauvegarde, pièces privées, règle des mineurs ; documents versionnés et consentements ; formation et quiz ; décisions coordonnateur et doublons ; profil Relais, badge QR.

**Sortie** : un candidat devient Relais actif de bout en bout (web + API).

### Étape 3 — Cœur métier (S3–S4)
`RelaisItem` + 10 états, brouillons, questions de protection ; téléversement reprenable, idempotence `client_uuid` ; ClamAV, SHA-256, EXIF, FFmpeg, vignettes, empreinte perceptuelle ; routage `ST_Contains` + repli ; verrou 10 min ; décisions ; escalade planifiée (30 min urgent / 4 h normal) ; événements Reverb.

**Sortie (jalon)** : relais envoyé par l'API → routé → visible en console < 5 s → validé → Relais notifié.

### Étape 4 — Interfaces métier (S4–S6)
Back-office coordonnateur ; console (flux, carte Leaflet, alertes urgent/retard/afflux, recherche, mode salle de rédaction) ; éditorial (sélection, affectation, ZIP + fiche PDF, export bloqué + dérogation, diffusé, collections) ; appels à relais (`ST_DWithin`) ; messagerie ; notifications.

**Sortie** : parcours 3.1, 3.2, 3.3 de bout en bout.

### Étape 5 — Site public, pilotage, conformité (S5–S6)
Site public, espace Relais web, vitrine, pages légales, cookies, SEO ; rapports Excel/PDF ; droits des personnes, pseudonymisation, purges ; durcissement.

**Sortie (jalon)** : version prête, gel fonctionnel.

### Étape 6 — Recette et sécurité (S6–S7)
Cahier de recette par exigence ; ZAP ; k6 à 10× ; réseau dégradé avec l'équipe mobile ; test d'intrusion + corrections ; restauration testée ; docs et guides.

**Sortie** : aucune anomalie bloquante ni majeure.

### Étapes 7 et 8 — Pilote (S7) et lancement national (S8)
Communes pilotes, formation, corrections ; puis ouverture nationale, surveillance de charge, astreinte.

## Points ouverts (valeurs provisoires, à rendre paramétrables)

| Point | Valeur provisoire |
|---|---|
| Âge minimum / mineurs | 18 ans, autorisation parentale prévue mais désactivée |
| Durées de conservation | vides, purge inactive |
| Volumes | 10 000 Relais, 2 000 relais/jour (hypothèse) |
| Hébergement | cloud standard en recette |
| Régie | export ZIP + fiche PDF uniquement |
| Cession de droits | texte provisoire versionné |
| Communes pilotes | 3 communes d'Abidjan |
| Passerelle SMS | driver `log` en local et recette |
