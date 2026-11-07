# Architecture — JE RELAIS

Ce document s'adresse aux développeurs qui reprennent ou étendent la plateforme. Le modèle de données est décrit dans [modele-de-donnees.md](modele-de-donnees.md), le modèle de menaces dans [../securite/modele-de-menaces.md](../securite/modele-de-menaces.md).

## Périmètre

- **Ce dépôt** contient :
  - le site public et l'espace Relais (Livewire) ;
  - la console de l'équipe : coordination, rédaction, administration ;
  - l'API REST `/api/v1`, utilisée par l'application mobile ;
  - les traitements en tâche de fond.
- L'application mobile est développée par une autre équipe et n'utilise que l'API. **Toutes les règles métier sont côté serveur.**

## Organisation du code

Le code métier est rangé par domaine dans `app/Domain/<Domaine>/` : `Models`, `Actions` (une opération = une classe `handle()`, en transaction), `Enums`, `Events`, `Listeners`, `Jobs`, `Policies`, `States`, `Support`.

| Domaine | Rôle |
|---|---|
| Access | Rôles et permissions (Spatie), `CommuneScope` / `BelongsToCommune`, périmètre des coordonnateurs (`CommuneAccess`), création des membres de l'équipe |
| Auth | Codes SMS (OTP), connexion par mot de passe, jetons par appareil (Sanctum), révocation à distance |
| Candidature | Dossier en étapes, pièces d'identité (compartiment dédié, URL signées), doublons, décisions |
| Legal / Formation | Textes juridiques versionnés et acceptations ; modules et quiz |
| Relais | Profil du Relais, badge et QR, statut (suspension, radiation, transfert), statistiques et niveaux (`RelaisActivity`) |
| Contribution | `RelaisItem` et sa machine à états (`spatie/laravel-model-states`), routage GPS, verrou de 10 min, délais et escalade, recherche |
| Media | Envoi multipart direct vers Wasabi, contrôle (`ProcessMedia` : type réel, ClamAV, SHA-256, EXIF/FFprobe, vignette, empreinte perceptuelle), purge |
| Validation | Motifs de rejet |
| Editorial | Arbitrage, sélection, diffusion, exports (ZIP + fiche PDF, dérogation), collections, vitrine |
| AppelRelais | Appels à relais, ciblage (communes, régions, rayon `ST_DWithin`), notification |
| Messagerie | Conversations Relais ↔ équipe, messages groupés |
| Reporting | Rapports (Excel avec openspout, PDF avec dompdf), envois périodiques |
| Privacy | Export des données personnelles, pseudonymisation, purge selon les durées de conservation |
| Settings | Paramètres modifiables (cache et audit), référentiels (`ManageReferenceList`) |
| Territoire | Régions, communes (contours PostGIS), localités, types de lieux, import GeoJSON |
| Audit | Journal en ajout seul, chaîné par empreintes, trigger PostgreSQL anti-modification |
| Exploitation | Sauvegarde et restauration PostgreSQL (`PostgresTools`) |

**Interfaces** :
- `app/Livewire` : composants de classe, routes `Route::livewire` ;
- `app/Http/Controllers/Api/V1` : API, avec les ressources de `app/Http/Resources/V1`.

## Cycle d'un relais

```
uploading ──envoi──► received ⇄ in_review   (libération sans décision : retour dans la file)
                                    ├──► validated ⇄ selected ──► broadcast
                                    ├──► rejected            (validated ⇄ rejected : arbitrage de la rédaction)
                                    ├──► complement_requested ──réponse du Relais──► received
                                    └──► sensitive ──arbitrage──► validated | rejected
validated | rejected | broadcast ──► archived
```

Chaque transition passe par `TransitionRelaisItem`. Elle écrit l'historique (`relais_status_changes`) et émet `RelaisStatusChanged`, ce qui déclenche :
- le SMS au Relais ;
- la diffusion en temps réel sur le canal de la commune.

## Règles transverses

1. **Cloisonnement par commune** : le scope global s'ajoute aux policies. Chaque accès hors scope (`withoutCommuneRestriction`) est borné par l'utilisateur ou justifié dans le code.
2. **Livewire** : les middlewares d'accès (`staff`, `two-factor`, `permission`) sont persistants. Chaque action sensible revérifie aussi la permission.
3. **Fichiers** : jamais servis avant le contrôle (statut `ready`), toujours par URL signée courte, sans métadonnées sur les vignettes publiques.
4. **Audit** : toute action sensible écrit dans le journal, via `AuditLogger`.
5. **Textes** : tout texte affiché passe par `lang/fr`. Attention, macOS ne distingue pas les majuscules dans les noms de fichiers : une clé `__('Relais')` entre en conflit avec `lang/fr/relais.php`.

## Qualité

```bash
vendor/bin/pint --dirty
php -d memory_limit=1G vendor/bin/phpstan analyse   # Larastan niveau 8
php artisan test --parallel                          # base « jerelais_test » obligatoire
php artisan recette:matrice --check                  # chaque exigence indispensable a son test
```
