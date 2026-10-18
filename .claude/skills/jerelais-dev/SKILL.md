---
name: jerelais-dev
description: Procédure de développement de la plateforme JE RELAIS (REFLET TV) — Laravel 13 + PostgreSQL/PostGIS, web + API pour l'app mobile. À charger AVANT toute tâche de développement dans ce dépôt (nouvelle fonctionnalité, module, migration, route API, composant Livewire, correction, revue de code) ou quand l'utilisateur parle d'étape, de module, d'exigence (MOB-xx, WEB-xx, COO-xx, ADM-xx) ou de « démarrer l'étape N ».
---

# JE RELAIS — procédure de développement

Source de vérité : cahier des charges v1.2 (30/09/2026) et le document « JE RELAIS — Procédure de développement de la plateforme » (Claude Docs). Ce skill en est la version opérationnelle.

## Périmètre

- **Ce dépôt (équipe Laravel)** : site public + espace Relais web, back-office coordonnateur, console REFLET TV, API REST `/api/v1`, services (médias, notifications, temps réel, audit).
- **Hors dépôt** : l'application Android/iOS (équipe mobile). Elle ne consomme que l'API. Toute règle métier vit côté serveur.

## Stack imposée

Laravel 13 · PHP 8.3+ · PostgreSQL + PostGIS (`clickbar/laravel-magellan`) · Redis · Horizon · Reverb · Fortify (2FA) · Sanctum (API) · `spatie/laravel-permission` · `spatie/laravel-model-states` · Livewire 4 + design system maison (Blade + Tailwind 4, style Apple) · Wasabi (S3, privé) · FFmpeg (`pbmedia/laravel-ffmpeg`) · ClamAV · Leaflet/OSM · Scramble (OpenAPI) · Pest 4 · Larastan · Pint · Sentry.

Ne pas ajouter de paquet hors de cette liste sans le signaler et le justifier à l'utilisateur.

## Procédure pour chaque fonctionnalité

1. **Identifier l'exigence** : retrouver la ou les références du cahier des charges (MOB/WEB/COO/ADM) et l'étape concernée dans [references/etapes.md](references/etapes.md). Si l'exigence dépend d'un point ouvert (Annexe C), utiliser la valeur provisoire et la rendre paramétrable.
2. **Placer le code dans son domaine** : `app/Domain/<Domaine>/{Models,Actions,Data,Enums,Events,Listeners,Jobs,Policies,States}`. Voir [references/architecture.md](references/architecture.md).
3. **Écrire d'abord le test** qui cite l'exigence : `it('… [COO-14]')`, avec un test de refus d'accès (autre commune, autre rôle).
4. **Implémenter** :
   - migration réversible, UUID exposés, index sur les colonnes filtrées, types PostGIS pour les géométries ;
   - Action (`handle()`) dans une transaction ; changement de statut via les States ; Event émis ;
   - Policy pour chaque action + `CommuneScope` quand la donnée est communale ;
   - Form Request + `$fillable` ; API Resource pour toute sortie API ;
   - écriture dans l'audit pour toute action sensible ;
   - effets secondaires (notifications, médias, temps réel) en Listener/Job sur Horizon.
5. **Appliquer les règles de sécurité** de [references/securite.md](references/securite.md) — non négociables.
6. **API mobile** : respecter [references/api-mobile.md](references/api-mobile.md) ; tout changement de contrat est signalé (étiquette `api-change`).
7. **Vérifier** avant de dire « terminé » :
   ```bash
   vendor/bin/pint --dirty
   vendor/bin/phpstan analyse        # dès que Larastan est installé
   php artisan test --parallel
   ```
   puis cocher la Definition of Done de [references/dod.md](references/dod.md). Signaler honnêtement tout test en échec ou étape non faite.
8. **Commit** (uniquement si l'utilisateur le demande) au format Conventional Commits en français, avec la référence : `feat(validation): verrou de 10 minutes [COO-14]`. Branche `feat/<REF>-<sujet>` depuis `develop`.

## Règles de vocabulaire

- Observateur = `Relais` (profil lié à `User`). Contenu envoyé = `RelaisItem` (table `relais_items`).
- Code et colonnes en anglais technique, termes métier français sans équivalent (`Commune`, `Candidature`, `Relais`).
- Tout texte affiché passe par `lang/fr` (`__('…')`).

## Les 5 points à ne jamais rater

1. Aucune fuite entre communes (Policy + `CommuneScope` + test de refus).
2. Fichiers : type réel vérifié, ClamAV, S3 privé, URL signées courtes, SHA-256.
3. OTP : haché, 5 min, 5 essais, limites par numéro et par IP.
4. Audit append-only (trigger PostgreSQL) ; export d'un relais non validé bloqué sauf dérogation tracée.
5. Pièces d'identité et GPS : chiffrés/restreints, jamais dans les logs ni dans une réponse qui n'en a pas besoin.
