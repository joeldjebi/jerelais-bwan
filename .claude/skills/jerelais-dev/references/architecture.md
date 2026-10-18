# Architecture et conventions

```
app/
  Domain/
    Auth/  Access/  Territoire/  Candidature/  Legal/  Formation/
    Relais/  Contribution/  Media/  Validation/  Editorial/
    AppelRelais/  Messagerie/  Notification/  Audit/  Reporting/  Settings/
  Http/
    Controllers/Api/V1/
    Requests/
    Resources/V1/
  Livewire/
    Public/  Relais/  Coordinateur/  Console/
```

Sous-dossiers d'un domaine (selon besoin) : `Models/`, `Actions/`, `Data/`, `Enums/`, `Events/`, `Listeners/`, `Jobs/`, `Policies/`, `States/`, `Exceptions/`, `Contracts/`.

## Règles

- **Action = un cas d'usage** (`ValiderRelais`, `RouterRelais`, `AccepterCandidature`) avec `handle()`. Appelée par le web, l'API et les jobs. Contrôleurs et composants Livewire restent minces.
- **Changement de statut** : Action + transaction + State + Event + audit.
- **Effets secondaires** en Listener/Job (Horizon), jamais dans la requête HTTP.
- **Services externes** derrière une interface dans `Contracts/` (SMS, push, antivirus, stockage) avec un faux pour les tests.
- **UUID** exposés (`HasUuids` ou colonne `uuid` + `getRouteKeyName`), clés numériques internes.
- **PostGIS** : `communes.geom` (MultiPolygon, SRID 4326, index GIST), `relais_items.location` (Point 4326). Routage : `ST_Contains(geom, point)`. Ciblage rayon : `ST_DWithin(geography, geography, mètres)`.
- **Eloquent** : `Model::preventLazyLoading()` hors production, `Model::shouldBeStrict()` en dev.
- **Temps réel** : événements `ShouldBroadcast` sur canaux privés (`commune.{uuid}`, `console`, `user.{uuid}`).

## Nommage

| Élément | Convention | Exemple |
|---|---|---|
| Tables | pluriel snake_case | `relais_items`, `communes`, `audit_logs` |
| Routes web | kebab-case français | `/console/appels-a-relais` |
| Routes API | `/api/v1/` + pluriel | `/api/v1/relais/{uuid}/medias` |
| Textes | `lang/fr/*.php` | `__('relais.status.validated')` |
| Branches | `type/REF-sujet` | `feat/COO-14-decisions-validation` |
| Commits | Conventional Commits FR + réf | `fix(media): rejet des HEIC corrompus [MOB-12]` |

## États d'un RelaisItem (§8.1)

`uploading` → `received` → `in_review` (verrou 10 min) → `complement_requested` ↔ `in_review` · `validated` · `rejected` · `sensitive` → (`validated` | `rejected`) · `validated` → `selected` → `broadcast` → `archived` · `rejected` → `archived`.
La rédaction peut revenir sur une décision (transition tracée dans l'audit).
