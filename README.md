# JE RELAIS

Plateforme des observateurs citoyens de REFLET TV : site public, back-office coordonnateur, console REFLET TV et API REST v1 pour l'application mobile.

Procédure de développement : skill `.claude/skills/jerelais-dev` et document « JE RELAIS — Procédure de développement de la plateforme ».

## Prérequis

- PHP 8.3+ (8.4 recommandé), Composer 2
- Node 22 (`.nvmrc`)
- Docker (PostgreSQL + PostGIS, Redis, Mailpit, ClamAV)
- FFmpeg

## Installation

```bash
cp .env.example .env              # puis renseigner WAS_ACCESS_KEY, WAS_SECRET_KEY, WASABI_BUCKET…
docker compose up -d              # PostGIS :5442, Redis :6390, Mailpit :8025
composer install
php artisan key:generate
php artisan reverb:install        # génère les clés REVERB_*
php artisan migrate --seed
npm ci && npm run build
```

ClamAV (utile à partir de l'étape 3) : `docker compose --profile media up -d`.

## Lancer en local

```bash
composer run dev                  # serveur, file, logs, Vite
php artisan reverb:start          # temps réel (port 8085)
php artisan horizon               # files de tâches
```

- Site public : http://localhost:8000
- Maquettes (hors production) : `/maquettes/console`, `/maquettes/coordinateur`
- Documentation de l'API : `/docs/api`
- Mails : http://localhost:8025

## Qualité

```bash
vendor/bin/pint                   # style
vendor/bin/phpstan analyse        # Larastan niveau 8
php artisan test --compact        # Pest (base jerelais_test sur PostGIS)
```

## Référentiel géographique

```bash
php artisan territoire:import-communes chemin/communes.geojson \
    --name-field=NOM --region-field=REGION --code-field=CODE
```

## Documentation

- `docs/securite/modele-de-menaces.md`
- `docs/architecture/modele-de-donnees.md`
- `docs/api/contrat-v0.md`
