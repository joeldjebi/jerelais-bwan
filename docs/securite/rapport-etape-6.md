# Rapport de sécurité — étape 6 (19/10/2026)

Ce rapport couvre deux contrôles complémentaires :

1. une **revue de code orientée intrusion**, en boîte blanche, sur l'ensemble du dépôt ;
2. un **scan dynamique OWASP ZAP** (baseline) sur l'application en fonctionnement.

Chaque faille corrigée est couverte par un test de non-régression. Les tests portent la mention « revue sécurité » dans leur intitulé.

## 1. Synthèse

| Gravité | Trouvées | Corrigées | Acceptées |
|---|---|---|---|
| Critique | 0 | — | — |
| Majeure | 4 (revue) + 1 (test de charge) | 5 | 0 |
| Mineure | 6 | 6 | 0 |
| Information | 5 | 2 | 3 |
| ZAP — risque élevé | 0 | — | — |
| ZAP — risque moyen ou faible | 10 alertes | 4 | 6 (justifiées en §4) |

**Critère de sortie** : aucune anomalie bloquante ni majeure ouverte.

## 2. Revue de code : constats et corrections

### Majeures

| # | Constat | Correction | Test |
|---|---|---|---|
| M1 | Après un transfert de commune, l'ancien coordonnateur gardait l'accès au dossier de candidature (pièces d'identité) et à la conversation générale du Relais. | La candidature et la conversation générale suivent le Relais, dans la même transaction (`ChangeRelaisStatus::transfer`). | `RelaisManagementTest` — transfert |
| M2 | La taille réelle des morceaux déposés sur Wasabi n'était pas contrôlée. Un Relais pouvait annoncer 1 Mo, déposer plusieurs Go et saturer les workers. | Avant la clôture, la somme des morceaux (`listParts`) doit égaler la taille annoncée, et aucun morceau ne doit dépasser `part_size`. Sinon l'envoi est annulé, le média rejeté, et rien n'est téléchargé. | `MediaUploadTest` — plus gros qu'annoncé |
| M3 | Un Relais suspendu ou radié pouvait encore envoyer un brouillon créé avant la sanction. | `RelaisSender::ensureCanSend` est revérifié à chaque étape : annonce et clôture des médias, envoi, complément, site et application. La radiation révoque les jetons de l'application. | `RelaisSubmissionTest`, `RelaisManagementTest` |
| M4 | Les middlewares de permission n'étaient pas rejoués sur les requêtes Livewire. Un droit retiré restait actif sur un onglet ouvert (pseudonymisation, messagerie, collections). | Les middlewares deviennent persistants pour Livewire (`staff`, `two-factor`, `permission`, `role`), avec une revérification dans chaque action concernée. | `ConsoleRoutesSecurityTest` |

### Mineures

| # | Constat | Correction |
|---|---|---|
| m5 | L'historique d'un Relais affichait ses relais routés dans d'autres communes. | Le périmètre communal est appliqué à l'historique. |
| m6 | Un coordonnateur pouvait ouvrir la fiche d'un appel local d'une autre commune. | Le périmètre est contrôlé à l'ouverture et à la clôture de l'appel. |
| m7 | `PUT /user/profile-information` (Fortify) permettait de changer l'e-mail sans mot de passe, donc de prendre un compte. | Fonctionnalité désactivée ; la page Compte suffit. |
| m8 | Un média pas encore contrôlé par l'antivirus pouvait être lu ou exporté par l'équipe. | Seuls les médias `ready` sont servis. |
| m9 | Le quota de SMS par numéro était doublé : un quota pour le site, un pour l'application. | Le quota par numéro est commun, appliqué dans `RequestOtp`. |
| m10 | Un compte de l'équipe disposant d'un téléphone pouvait obtenir un jeton d'API sans double authentification. | L'API refuse les comptes de l'équipe (`IssueDeviceToken`). |

### Information

- **Corrigé** : des filtres invalides dans l'URL de la console (date, identifiant) provoquaient une erreur 500. Ils sont désormais ignorés.
- **Accepté** : le nom d'un doublon de candidature venant d'une autre commune reste visible du coordonnateur. Ce nom est nécessaire pour vérifier le doublon (COO-03).
- **Accepté** : `PUT /me/password` ne révoque pas les autres appareils. Le Relais peut les révoquer lui-même ; à revoir avec l'équipe mobile.
- **Accepté** : le quiz renvoie la correction. Ce choix est pédagogique (MOB-05) ; les tentatives sont limitées à 10 par heure.
- **Accepté** : l'URL signée de la photo du badge reste valable 15 minutes.

### Zones vérifiées saines

- **Cloisonnement par commune** : `CommuneScope` et les policies, sans aucune fuite.
- **Accès aux objets par l'API** : relais, médias, conversations, appels et pièces.
- **Injections** : aucune injection SQL (requêtes paramétrées), aucune faille XSS (Markdown filtré, échappement Blade et Leaflet, CSP avec nonce).
- **Fichiers** : type réel du contenu, antivirus, SHA-256, chemins générés par le serveur.
- **Codes SMS (OTP)** : hachés, valables 5 minutes, 5 essais.
- **Journal d'audit** : chaîné et en ajout seul.

## 3. Scan OWASP ZAP baseline

Commande :

```bash
docker run --rm -v "$PWD/zap:/zap/wrk:rw" ghcr.io/zaproxy/zaproxy:stable \
  zap-baseline.py -t http://host.docker.internal:8000 -m 3 -J zap.json -r zap.html -I
```

**Résultat** : FAIL 0, WARN 10, PASS 57.

| Alerte ZAP | Risque | Suite donnée |
|---|---|---|
| 10037 `X-Powered-By` | Faible | Corrigé : en-tête retiré par `SecurityHeaders`, plus `expose_php = Off` en production |
| 90004 Cross-Origin-Resource-Policy absent | Faible | Corrigé : `same-origin` |
| 10021 `X-Content-Type-Options` absent sur `/build/*` | Faible | Fichiers statiques servis par Nginx en production : en-têtes ajoutés dans la configuration fournie (docs/exploitation) |
| 10063 Permissions-Policy absent sur `/build/*` | Faible | Idem |
| 10055 CSP `script-src 'unsafe-eval'` | Moyen | Accepté : requis par Alpine.js (build standard livré par Livewire). Atténué par le nonce obligatoire, `object-src 'none'` et `frame-ancestors 'none'`. Passage au build CSP d'Alpine à étudier. |
| 10055 CSP `style-src 'unsafe-inline'` | Moyen | Accepté : les styles en ligne de Livewire et Alpine (transitions) ; aucun vecteur d'exécution de script |
| 10010 Cookie `XSRF-TOKEN` sans HttpOnly | Faible | Accepté : ce cookie doit être lisible par JavaScript, par construction (protection CSRF de Laravel) ; le cookie de session est HttpOnly, Secure et chiffré |
| 90004 Cross-Origin-Embedder-Policy absent | Faible | Accepté : `require-corp` bloquerait les tuiles OpenStreetMap et les médias Wasabi signés |
| 10044 « Big redirect » | Faible | Faux positif : page de redirection standard de Laravel, sans donnée |
| 10049 / 10111 / 10112 | Info | Informatif |

## 4. Reste à faire avant la mise en production

- Faire réaliser un test d'intrusion par un tiers indépendant sur la préproduction, avec un périmètre incluant l'application mobile.
- Relancer ZAP en mode authentifié sur la console (`zap-full-scan` avec contexte de session) une fois la préproduction en HTTPS.
- Faire tourner les clés Wasabi partagées pendant le développement.
