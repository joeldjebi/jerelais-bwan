# Recette — étape 6

**Critère de sortie** : aucune anomalie bloquante ni majeure ouverte.

| Livrable | Document | État au 19/10/2026 |
|---|---|---|
| Cahier de recette par exigence | [cahier-de-recette.md](cahier-de-recette.md), généré par `php artisan recette:matrice` depuis [exigences.json](exigences.json) et les tests | 73 exigences : 64 couvertes par des tests automatisés et un scénario manuel, 5 côté équipe mobile, 4 hors lot. Aucune exigence indispensable sans test (`--check`) |
| Scan OWASP ZAP | [../securite/rapport-etape-6.md](../securite/rapport-etape-6.md) | 0 FAIL ; 4 alertes corrigées, 6 justifiées |
| Revue d'intrusion (boîte blanche) | idem | 0 critique ; 4 majeures et 6 mineures corrigées, avec tests |
| Test de charge × 10 | [charge.md](charge.md) | Validé sur le poste de développement (0 % d'erreurs, p95 = 251 ms à 50 req/s) ; **à rejouer en préproduction** |
| Réseau dégradé avec l'équipe mobile | [reseau-degrade.md](reseau-degrade.md) | Protocole prêt (12 scénarios, 5 profils réseau) ; **séance à planifier avec l'équipe mobile** |
| Restauration testée | [../exploitation/README.md](../exploitation/README.md#6-sauvegardes-et-restauration) | Sauvegarde puis restauration réelles réussies (empreinte, migrations, volumes, chaîne d'audit, PostGIS) ; automatisées chaque semaine |
| Documentation et guides | [../architecture](../architecture/README.md) · [../api](../api/contrat-v0.md) · [../exploitation](../exploitation/README.md) · [../guides](../guides/) | Rédigés |

## Registre des anomalies de l'étape 6

| N° | Gravité | Origine | Description | État |
|---|---|---|---|---|
| A1 | Majeure | Revue | Accès conservé par l'ancien coordonnateur après un transfert de commune | Corrigée |
| A2 | Majeure | Revue | Taille réelle des morceaux d'envoi non contrôlée | Corrigée |
| A3 | Majeure | Revue | Envoi encore possible par un Relais suspendu ou radié | Corrigée |
| A4 | Majeure | Revue | Permissions non rejouées sur les requêtes Livewire | Corrigée |
| A5 | Majeure | Charge | 429 sur les référentiels publics derrière une IP d'opérateur partagée | Corrigée |
| A6 | Majeure | Recette | ADM-20 et ADM-23 incomplets : pas de gestion des localités, types de lieux, catégories et motifs de rejet en console | Corrigée (page Référentiels) |
| A7 | Mineure | Recette | Suppléance (ADM-21) non exposée à la création d'un coordonnateur | Corrigée |
| A8 | Mineure | Recette | MOB-24 et MOB-25 absents de l'API | Corrigée (`/me/stats`, `/diffusions`) |
| A9 | Mineure | Recette | MOB-31 : déconnexion à distance sans bouton dans la console | Corrigée (fiche Relais > Appareils) |
| A10–A15 | Mineure | Revue | Points m5 à m10 du rapport de sécurité | Corrigées |
| A16 | Mineure | ZAP | `X-Powered-By` et en-tête CORP absent | Corrigée |

**Bilan : aucune anomalie bloquante ou majeure ouverte côté serveur.**

## Points ouverts (hors critère serveur)

1. **ADM-16**, export vers la régie : le protocole de la régie REFLET TV (dossier partagé, SFTP ou API) reste à fournir. L'export ZIP avec fiche couvre le besoin en attendant.
2. **MOB-21**, notifications push : les jetons FCM sont enregistrés par l'API. L'envoi FCM reste à brancher avec l'équipe mobile ; le SMS de secours est opérationnel.
3. **Préproduction** :
   - rejouer le test de charge à 20 req/s × 10 ;
   - mener la séance « réseau dégradé » ;
   - faire réaliser un test d'intrusion par un tiers indépendant.
4. **Hypothèse de charge** : à confirmer par REFLET TV (voir [charge.md](charge.md)).
