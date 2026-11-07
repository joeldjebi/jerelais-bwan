# Protocole de recette en réseau dégradé — équipe Laravel × équipe mobile

**Objectif.** Prouver que l'envoi d'un relais reste fiable sur les réseaux réellement utilisés par les Relais : 3G saturée, 4G instable en périphérie, coupures dans les transports, Wi-Fi public lent. Exigences couvertes : MOB-16 (envoi fractionné et reprenable), MOB-17 (hors ligne puis envoi automatique), MOB-18 (compression), MOB-11 (limites des médias), WEB-04 (même protocole côté site).

**Participants.**
- Équipe mobile : pilote les téléphones.
- Équipe Laravel : surveille Horizon, les journaux et le stockage Wasabi.
- Un témoin REFLET TV : signe le procès-verbal.

**Environnement.** Préproduction, avec la même configuration que la production : Wasabi, ClamAV, Horizon et Reverb.

## 1. Matériel

| Élément | Exigence |
|---|---|
| Téléphones | Un Android d'entrée de gamme (2 Go de RAM, Android 10), un Android récent, un iPhone |
| Cartes SIM | Orange, MTN et Moov, pour comparer les opérateurs et leurs NAT |
| Simulation | Network Link Conditioner (iOS / macOS), profils « Developer options » Android, ou un proxy `toxiproxy` devant la préproduction |
| Fichiers | Photo 4 Mo, vidéo 1 min (≈ 60 Mo), vidéo 3 min (≈ 180 Mo), audio 10 min, photo de galerie avec EXIF et GPS |

## 2. Profils réseau

| Profil | Débit montant | Latence | Pertes | Cas réel visé |
|---|---|---|---|---|
| R1 — 3G | 384 kbit/s | 300 ms | 2 % | Périphérie, heures de pointe |
| R2 — EDGE | 128 kbit/s | 600 ms | 5 % | Village, intérieur de bâtiment |
| R3 — 4G instable | 5 Mbit/s | 80 ms | 1 % + coupure de 30 s toutes les 2 min | Gbaka, taxi |
| R4 — Très lossy | 1 Mbit/s | 200 ms | 15 % | Rassemblement, réseau saturé |
| R5 — Hors ligne | 0 | — | 100 % pendant 10 min | Mode avion, zone blanche |

## 3. Scénarios

Pour chaque scénario, l'équipe Laravel relève le `client_uuid`, l'état du relais en console, les morceaux reçus (`GET /medias/{uuid}`) et la présence d'un éventuel doublon.

| # | Scénario | Profil | Résultat attendu |
|---|---|---|---|
| D1 | Vidéo de 3 min envoyée d'un trait | R1 | Envoi terminé ; aucun morceau renvoyé deux fois ; relais « reçu » dans la file |
| D2 | Coupure à 40 % de l'envoi de la vidéo, réseau rétabli après 30 s | R3 | Reprise automatique aux morceaux manquants (`missing_parts`), sans repartir de zéro |
| D3 | Application tuée (balayage) pendant l'envoi, puis relancée | R1 | Reprise depuis l'état local ; le serveur renvoie les URL des morceaux manquants |
| D4 | Relais préparé en mode avion, puis réseau rétabli | R5 → R1 | Envoi automatique au retour du réseau ; option « Wi-Fi seulement » respectée pour les vidéos |
| D5 | Double envoi : l'appel `POST /relais` est rejoué (timeout côté client) | R4 | Le même relais est renvoyé (200) : jamais de doublon (idempotence `client_uuid`) |
| D6 | Les URL signées des morceaux expirent (envoi interrompu plus de 30 min) | R2 | `GET /medias/{uuid}` fournit de nouvelles URL ; l'envoi aboutit |
| D7 | Clôture `complete` perdue (réponse non reçue) puis rejouée | R4 | Second appel sans effet (statut déjà `uploaded`) ; pas de seconde analyse antivirus |
| D8 | Photo de galerie avec EXIF | R1 | « Média importé » et date de prise de vue visibles en console ; vignette publique sans EXIF |
| D9 | Vidéo de 4 min (au-delà de la limite) | R1 | Refus clair avant l'envoi (application) ou au contrôle (serveur), avec le message en français |
| D10 | Réponse à un complément avec un média supplémentaire | R3 | Le relais revient dans la file avec le nouveau média |
| D11 | Envoi depuis le site, navigateur mobile, page rechargée à mi-parcours | R1 | Reprise avec la barre de progression (WEB-04) |
| D12 | Abandon : envoi commencé puis jamais terminé | R2 | Purge du multipart après le délai (`medias:purge-abandoned`), aucun fichier orphelin sur Wasabi |

## 4. Mesures relevées

- Durée d'envoi par profil et par taille de fichier ; nombre de morceaux renvoyés.
- Consommation de données réelle (opérateur) comparée à la taille du fichier, pour vérifier que les renvois ne doublent pas la facture du Relais.
- Délai entre la fin de l'envoi et l'apparition dans la file du coordonnateur (objectif : moins de 2 min pour une vidéo de 180 Mo, analyse antivirus comprise).
- Batterie consommée pour une vidéo de 3 min en R1 (indicatif).

## 5. Points de contrôle côté serveur

```bash
php artisan horizon:status                      # files « medias » et « default » actives
php artisan tinker --execute 'App\Domain\Media\Models\Media::query()->latest()->first(["status","rejection","size"]);'
tail -f storage/logs/laravel.log                # aucun 500 pendant la séance
```

## 6. Critères de réussite

- Les scénarios D1 à D12 sont conformes sur les trois téléphones et au moins deux opérateurs.
- Aucun relais en double et aucun média perdu.
- Aucun envoi bloqué sans message compréhensible pour le Relais.

Une anomalie qui fait perdre un relais est **bloquante**. Un renvoi complet inutile (au lieu d'une reprise) est **majeur**.

## 7. Procès-verbal

| Scénario | Android entrée de gamme | Android récent | iPhone | Opérateur | Anomalie (n°, gravité) |
|---|---|---|---|---|---|
| D1 | ☐ | ☐ | ☐ | | |
| D2 | ☐ | ☐ | ☐ | | |
| D3 | ☐ | ☐ | ☐ | | |
| D4 | ☐ | ☐ | ☐ | | |
| D5 | ☐ | ☐ | ☐ | | |
| D6 | ☐ | ☐ | ☐ | | |
| D7 | ☐ | ☐ | ☐ | | |
| D8 | ☐ | ☐ | ☐ | | |
| D9 | ☐ | ☐ | ☐ | | |
| D10 | ☐ | ☐ | ☐ | | |
| D11 | ☐ | ☐ | ☐ | | |
| D12 | ☐ | ☐ | ☐ | | |

Date : ______ · Équipe mobile : ______ · Équipe Laravel : ______ · REFLET TV : ______
