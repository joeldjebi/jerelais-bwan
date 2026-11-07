# Test de charge — pic × 10

Script : [tests/Load/pic-10x.js](../../tests/Load/pic-10x.js) (k6).

## Hypothèse de pic (à confirmer par REFLET TV)

Le cahier des charges ne chiffre pas la charge. Hypothèse retenue pour le pilote, puis pour le lancement national :

| | Pilote (Abidjan) | National |
|---|---|---|
| Relais actifs | 500 | 5 000 |
| Pic nominal (événement majeur, journal du soir) | 2 req/s | 20 req/s |
| Test « × 10 » | 20 req/s | 200 req/s |

`PEAK_RPS` (pic nominal) et `MULTIPLIER` sont des paramètres du script. La répartition du trafic :
- 70 % application des Relais : mes relais, appels, statistiques, messagerie, paramètres, création de brouillons ;
- 20 % site public : accueil, vitrine, carte du réseau, devenir Relais.

La console (10 % du trafic attendu) n'est pas incluse, car elle passe par des sessions avec double authentification. Le téléversement des médias va directement vers Wasabi et ne charge pas nos serveurs ; seule la clôture déclenche un traitement, mesuré dans les files Horizon.

**Seuils de réussite** :
- moins de 1 % d'erreurs ;
- 95 % des requêtes de l'application sous 800 ms ;
- 95 % des pages publiques sous 600 ms.

## Lancer le test

```bash
# Préproduction uniquement (la commande refuse la production)
php artisan jerelais:load-test-accounts 50 --commune=yopougon

BASE_URL=https://preprod.jerelais.reflet.tv PEAK_RPS=20 MULTIPLIER=10 DURATION=10m \
  TOKENS_FILE=storage/app/private/k6/tokens.json k6 run tests/Load/pic-10x.js
```

Sans k6 installé, l'image Docker `grafana/k6` fonctionne aussi (voir l'en-tête du script). Sur macOS, lancez k6 nativement : le pont réseau de Docker Desktop provoque des `dial: i/o timeout` qui faussent la mesure.

## Résultats du 19/10/2026 — poste de développement

Contexte : MacBook, PHP 8.4 avec OPcache, serveur intégré PHP à 8 processus, cache de configuration et de routes, PostgreSQL et Redis en conteneurs. Cette machine reste moins puissante que la préproduction cible.

| Essai | Débit visé | Requêtes | Erreurs | p95 application | p95 site |
|---|---|---|---|---|---|
| × 10 du pic pilote réduit (1 req/s) | 10 req/s | 713 | 0 % | 180 ms | 102 ms |
| × 10 de 5 req/s | 50 req/s (rampe incluse : 33,5 req/s en moyenne) | 3 502 | 0 % | 251 ms | 175 ms |

### Anomalies trouvées et corrigées pendant le test

1. **429 sur les référentiels publics (majeure)**. `/api/v1/settings` était limité à 120 req/min par adresse IP. Or les opérateurs ivoiriens partagent une même adresse publique entre de nombreux abonnés (CGNAT) : au démarrage de l'application pendant un événement, des Relais auraient été bloqués.
   - Les appels anonymes sont désormais limités à 1 200 req/min par IP (`API_ANONYMOUS_PER_IP_PER_MINUTE`).
   - Les appels authentifiés restent limités à 120 req/min par compte.
   - Les référentiels sont mis en cache 5 minutes (`Cache-Control` + `ETag`).
   - Les quotas SMS et de connexion sont inchangés.
2. **Mesures faussées en local**, sans effet sur l'application elle-même :
   - `artisan serve` traite une seule requête à la fois et tourne sans OPcache en CLI ;
   - le pont réseau de Docker Desktop provoque des timeouts.

   Ces deux artefacts sont documentés ci-dessus pour ne pas les retrouver en préproduction.

### À faire en préproduction (critère de sortie)

- Lancer `PEAK_RPS=20 MULTIPLIER=10 DURATION=10m` sur l'infrastructure cible (PHP-FPM, Nginx, Horizon, Reverb).
- Surveiller pendant le test :
  - CPU et mémoire de PHP-FPM ;
  - connexions PostgreSQL (`max_connections`) ;
  - mémoire Redis ;
  - longueur des files Horizon.
- Envoyer en parallèle 20 vidéos réelles depuis 5 téléphones, et vérifier que la file `medias` (`supervisor-medias`) se vide en moins de 5 min.
- Reporter les résultats dans ce document.
