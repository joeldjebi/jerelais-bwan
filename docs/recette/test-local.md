# Tester JE RELAIS en local

Ce guide permet de tester toute la plateforme sur ton Mac avant la production : site public, espace Relais, console et API mobile.

## 1. Démarrer

```bash
docker compose --profile media up -d       # PostgreSQL, Redis, Mailpit, ClamAV
PATH=/opt/homebrew/bin:$PATH /opt/homebrew/opt/php@8.4/bin/php artisan dev
```

`artisan dev` lance dans un seul terminal :

| Service | Rôle |
|---|---|
| server | Le site, sur http://localhost:8000 |
| horizon | Les files `default` et `medias` |
| reverb | Le temps réel |
| scheduler | Les tâches planifiées : escalades, verrous, purges |
| logs | Le suivi des journaux |

`Ctrl+C` arrête tout.

> `PATH=/opt/homebrew/bin:$PATH` force Node 22. Ton nvm utilise Node 18 par défaut, trop ancien pour l'outil qui affiche les services. La commande `nvm use 22` fonctionne aussi.

| Adresse | Pour quoi faire |
|---|---|
| http://localhost:8000 | Site public, espace Relais (`/devenir-relais`), console (`/login`) |
| http://localhost:8025 | **Mailpit** : tous les SMS (codes de connexion, décisions) et les e-mails |
| http://localhost:8000/docs/api | Documentation interactive de l'API mobile |
| http://localhost:8000/horizon | Files d'attente : ouvert sans connexion en local, réservé aux administrateurs en production |

## 2. Comptes de démonstration

**Console** : mot de passe `12345678`. Code de double authentification : **`123456`**.

| E-mail | Rôle | Périmètre |
|---|---|---|
| admin@jerelais.com | Administrateur | Tout |
| redaction@jerelais.com | Rédacteur en chef (Aïcha Koné) | Réseau national |
| journaliste@jerelais.com | Journaliste (Yao Kouassi) | Réseau national |
| lecture@jerelais.com | Lecture seule | Réseau national, sans action |
| superviseur@jerelais.com | Superviseur régional | District d'Abidjan |
| coordinateur@jerelais.com | Coordonnateur titulaire | Yopougon |
| suppleant@jerelais.com | Coordonnateur suppléant | Yopougon |
| coordinateur.cocody@jerelais.com | Coordonnateur titulaire | Cocody et Abobo |

**Relais** : mot de passe `12345678`. Sur le site, la connexion se fait par code SMS, à lire dans Mailpit.

| Téléphone | Relais | Commune | État |
|---|---|---|---|
| 07 00 00 00 01 | Awa Traoré | Yopougon | Actif |
| 07 00 00 00 02 | Koffi N'Guessan | Yopougon | Actif |
| 07 00 00 00 03 | Mariam Bamba | Yopougon | Actif |
| 07 00 00 00 04 | Fatou Diallo | Cocody | Actif |
| 07 00 00 00 05 | Jean-Marc Yao | Abobo | Actif |
| 07 00 00 00 06 | Ibrahim Ouattara | Abobo | **Suspendu** |
| 07 00 00 00 11 | Adjoua Konan | Yopougon | Candidature déposée |
| 07 00 00 00 12 | Moussa Coulibaly | Yopougon | Candidature en examen |
| 07 00 00 00 13 | Grâce Aka | — | Candidature à compléter |

**Relais déjà présents**, dans tous les états, avec de vraies photos passées par Wasabi et ClamAV :

| Commune | Relais | État |
|---|---|---|
| Yopougon | Incendie à Selmer | Urgent, à valider |
| Yopougon | Lampadaires | En retard de validation |
| Yopougon | Route coupée | Complément demandé |
| Yopougon | Inondation Siporex | Validé |
| Yopougon | Hors sujet | Rejeté |
| Yopougon | Accident | Sensible, à arbitrer |
| Yopougon | Wassakara | Diffusé et sur la vitrine |
| Cocody | Embouteillage | À valider |
| Cocody | Coupure Angré | Sélectionné |
| Abobo | Pluies | Diffusé et sur la vitrine |

Le jeu contient aussi 2 appels à relais, une conversation, un message groupé et la collection « Saison des pluies 2026 ».

## 3. Parcours de test conseillés

Fais chaque parcours avec deux fenêtres côte à côte (ou une fenêtre de navigation privée) : tu vois ainsi le temps réel.

### A. Devenir Relais (site)

1. Ouvre `/devenir-relais` et saisis un **nouveau** numéro, par exemple `07 11 22 33 44`.
2. Lis le code dans Mailpit, puis remplis les 3 étapes.
3. Dépose les pièces : n'importe quelle photo JPEG ou PNG.
4. Accepte les textes juridiques, puis réponds au quiz. Échoue une fois exprès, puis réussis.
5. Dépose le dossier.
6. Console avec **coordinateur@** > Candidatures : ouvre le dossier et vérifie les pièces, le selfie et l'alerte de doublon éventuelle. Décide : demander un complément, puis accepter.
7. Côté candidat : le statut change, un SMS arrive dans Mailpit et le badge avec QR code apparaît. Ouvre le lien du badge dans le navigateur : la page de vérification s'affiche. Un téléphone qui scanne le QR ne pourra pas joindre `localhost`.

### B. Envoyer et valider un relais

1. Espace Relais avec **Awa** (07 00 00 00 01) > Envoyer : ajoute une photo et une courte vidéo, remplis le formulaire et réponds aux questions de protection.
2. Recharge la page pendant l'envoi d'une vidéo : l'envoi **reprend** là où il en était.
3. Console avec **coordinateur@** > Validation : le relais arrive **sans recharger la page**. S'il est urgent, un signal sonore retentit.
4. Ouvre-le : il est réservé 10 min (essaie de l'ouvrir avec **suppleant@** en même temps). Vérifie la carte, les dates et le lecteur (zoom, image par image, vitesse), puis décide.
5. Côté Awa : le statut change dans « Mes relais » et un SMS arrive.

### C. Rédaction

1. Avec **redaction@** :
   - arbitre le relais « Accident » (sensible) ;
   - sélectionne « Inondation Siporex » et affecte-le à Yao.
2. Télécharge l'original, puis le ZIP avec sa fiche PDF.
3. Tente d'exporter un relais non validé : l'export est bloqué. Seul **admin@** peut déroger, avec un motif.
4. Marque le relais comme diffusé, puis publie-le sur la vitrine. Vérifie `/vu-sur-reflet-tv`.
5. Crée un appel à relais ciblant Cocody. **Fatou** le voit, **Awa** non.

### D. Administration

- **Référentiels** : ajoute un quartier à Yopougon, puis vérifie qu'il est proposé dans le formulaire de candidature.
- **Équipe** : crée un coordonnateur suppléant. Le lien d'activation arrive dans Mailpit.
- **Relais** > fiche d'Awa :
  - « Appareils connectés » > déconnecter ;
  - transfert vers Cocody : **coordinateur@** perd l'accès au dossier.
- **Paramètres** : passe la durée vidéo à 60 s, puis envoie une vidéo plus longue : elle est refusée.
- **Journal d'audit** : « Vérifier l'intégrité ».
- **Rapports** : export Excel et PDF.
- **Données personnelles** : export, puis suppression d'un compte de test.

### E. Sécurité et limites

- **Type réel du fichier** : renomme un fichier texte en `test.jpg` et envoie-le. Il est refusé, car le contenu est vérifié, pas l'extension.
- **Antivirus** : un fichier de test EICAR n'est pas une image valide. Le contrôle de type le refuse donc avant l'antivirus. Pour tester ClamAV lui-même :

  ```bash
  printf '%s' 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > /tmp/eicar.txt
  /opt/homebrew/opt/php@8.4/bin/php artisan tinker --execute 'dump(app(App\Domain\Media\Contracts\VirusScanner::class)->scan("/tmp/eicar.txt"));'
  # attendu : "Eicar-Test-Signature"
  ```
- **Relais suspendu** : connecte-toi avec **Ibrahim** (07 00 00 00 06). L'envoi est refusé.
- **Quota SMS** : 4 demandes de code en moins de 15 min pour un même numéro, la 4e est refusée.
- **Cloisonnement** : avec **coordinateur.cocody@**, ouvre l'URL d'un relais de Yopougon. Tu obtiens une page 404.

### F. API mobile

Dans `/docs/api`, connecte-toi avec `POST /auth/login` (téléphone `0700000001`, mot de passe `12345678`), puis colle le jeton dans « Authorize ». Teste ensuite :
- `GET /me/stats` ;
- `GET /relais` ;
- `GET /appels` ;
- `GET /diffusions`.

## 4. Remettre à zéro

```bash
/opt/homebrew/opt/php@8.4/bin/php artisan migrate:fresh --seed   # efface la base « jerelais » et recrée le jeu de démonstration (~45 s)
```

Ne lance **jamais** les tests (`php artisan test`) juste après un `config:cache` : ils refusent désormais de démarrer, pour protéger ta base. Un `php artisan optimize:clear` suffit à repartir.

## 5. Noter les anomalies

Reporte chaque écart dans le [cahier de recette](cahier-de-recette.md) : coche la case « Résultat » de l'exigence et indique la gravité (bloquante, majeure, mineure). Le critère pour passer en production : aucune anomalie bloquante ni majeure ouverte.
