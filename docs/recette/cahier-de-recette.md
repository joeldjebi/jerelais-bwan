# Cahier de recette — JE RELAIS

> Généré par `php artisan recette:matrice` le 21/10/2026 à 14:23. Ne pas modifier à la main : éditer `docs/recette/exigences.json` ou les tests, puis régénérer.

**Critère de sortie de l’étape 6** : aucune anomalie bloquante ni majeure ouverte. Chaque exigence est recettée sur la préproduction par le scénario manuel ; les tests automatisés en garantissent la non-régression.

| Statut | Exigences |
|---|---|
| Automatisé + manuel | 64 |
| Manuel | 0 |
| Équipe mobile | 5 |
| Hors lot | 4 |

## Matrice de traçabilité

| Exigence | Priorité | Périmètre | Statut | Tests | Résultat |
|---|---|---|---|---|---|
| [MOB-01](#mob-01) | Indispensable | mixte | Automatisé + manuel | 5 | ☐ |
| [MOB-02](#mob-02) | Indispensable | mixte | Automatisé + manuel | 4 | ☐ |
| [MOB-03](#mob-03) | Indispensable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-04](#mob-04) | Indispensable | mixte | Automatisé + manuel | 4 | ☐ |
| [MOB-05](#mob-05) | Souhaitable | mixte | Automatisé + manuel | 3 | ☐ |
| [MOB-06](#mob-06) | Indispensable | mixte | Automatisé + manuel | 5 | ☐ |
| [MOB-07](#mob-07) | Souhaitable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-10](#mob-10) | Indispensable | mobile | Équipe mobile | 0 | ☐ |
| [MOB-11](#mob-11) | Indispensable | mixte | Automatisé + manuel | 4 | ☐ |
| [MOB-12](#mob-12) | Indispensable | mixte | Automatisé + manuel | 1 | ☐ |
| [MOB-13](#mob-13) | Indispensable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-14](#mob-14) | Indispensable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-15](#mob-15) | Indispensable | mixte | Automatisé + manuel | 1 | ☐ |
| [MOB-16](#mob-16) | Indispensable | mixte | Automatisé + manuel | 3 | ☐ |
| [MOB-17](#mob-17) | Indispensable | mobile | Équipe mobile | 0 | ☐ |
| [MOB-18](#mob-18) | Souhaitable | mobile | Équipe mobile | 0 | ☐ |
| [MOB-19](#mob-19) | Souhaitable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-20](#mob-20) | Indispensable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-21](#mob-21) | Indispensable | mixte | Automatisé + manuel | 1 | ☐ |
| [MOB-22](#mob-22) | Indispensable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-23](#mob-23) | Indispensable | mixte | Automatisé + manuel | 3 | ☐ |
| [MOB-24](#mob-24) | Souhaitable | mixte | Automatisé + manuel | 2 | ☐ |
| [MOB-25](#mob-25) | Souhaitable | mixte | Automatisé + manuel | 1 | ☐ |
| [MOB-26](#mob-26) | Optionnel | hors-lot | Hors lot | 0 | ☐ |
| [MOB-30](#mob-30) | Indispensable | mobile | Équipe mobile | 0 | ☐ |
| [MOB-31](#mob-31) | Souhaitable | mixte | Automatisé + manuel | 3 | ☐ |
| [MOB-32](#mob-32) | Souhaitable | mobile | Équipe mobile | 0 | ☐ |
| [MOB-33](#mob-33) | Optionnel | hors-lot | Hors lot | 0 | ☐ |
| [WEB-01](#web-01) | Indispensable | serveur | Automatisé + manuel | 2 | ☐ |
| [WEB-02](#web-02) | Indispensable | serveur | Automatisé + manuel | 5 | ☐ |
| [WEB-03](#web-03) | Indispensable | serveur | Automatisé + manuel | 2 | ☐ |
| [WEB-04](#web-04) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [WEB-05](#web-05) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [WEB-06](#web-06) | Optionnel | serveur | Automatisé + manuel | 1 | ☐ |
| [WEB-07](#web-07) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [WEB-08](#web-08) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-01](#coo-01) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [COO-02](#coo-02) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [COO-03](#coo-03) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-04](#coo-04) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [COO-10](#coo-10) | Indispensable | serveur | Automatisé + manuel | 2 | ☐ |
| [COO-11](#coo-11) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-12](#coo-12) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-13](#coo-13) | Souhaitable | serveur | Automatisé + manuel | 2 | ☐ |
| [COO-14](#coo-14) | Indispensable | serveur | Automatisé + manuel | 4 | ☐ |
| [COO-15](#coo-15) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [COO-16](#coo-16) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-17](#coo-17) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-18](#coo-18) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-20](#coo-20) | Indispensable | serveur | Automatisé + manuel | 2 | ☐ |
| [COO-21](#coo-21) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [COO-22](#coo-22) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-01](#adm-01) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-02](#adm-02) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-03](#adm-03) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-04](#adm-04) | Indispensable | serveur | Automatisé + manuel | 2 | ☐ |
| [ADM-05](#adm-05) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-10](#adm-10) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-11](#adm-11) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-12](#adm-12) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-13](#adm-13) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-14](#adm-14) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-15](#adm-15) | Souhaitable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-16](#adm-16) | Souhaitable | hors-lot | Hors lot | 0 | ☐ |
| [ADM-17](#adm-17) | Optionnel | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-20](#adm-20) | Indispensable | serveur | Automatisé + manuel | 6 | ☐ |
| [ADM-21](#adm-21) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [ADM-22](#adm-22) | Indispensable | serveur | Automatisé + manuel | 3 | ☐ |
| [ADM-23](#adm-23) | Indispensable | serveur | Automatisé + manuel | 6 | ☐ |
| [ADM-24](#adm-24) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-25](#adm-25) | Indispensable | serveur | Automatisé + manuel | 6 | ☐ |
| [ADM-26](#adm-26) | Indispensable | serveur | Automatisé + manuel | 1 | ☐ |
| [ADM-27](#adm-27) | Optionnel | hors-lot | Hors lot | 0 | ☐ |

## Scénarios par exigence

### MOB-01

*Indispensable · mixte · Automatisé + manuel*

> Création de compte par numéro de téléphone avec code de vérification SMS (OTP). Connexion ultérieure par code SMS ou mot de passe, et déverrouillage biométrique si l'appareil le permet.

**Scénario manuel** : Demander un code pour un numéro inconnu, le saisir : compte candidat créé. Se reconnecter par code puis par mot de passe. Biométrie : côté application.

**Tests automatisés**

- `Feature/Auth/DeviceSessionTest.php — connecte par mot de passe un Relais qui en a défini un [MOB-01]`
- `Feature/Auth/OtpAuthenticationTest.php — envoie un code SMS de 6 chiffres et ne stocke que son empreinte [MOB-01]`
- `Feature/Auth/OtpAuthenticationTest.php — crée le compte candidat et un jeton d’appareil au premier code valide [MOB-01]`
- `Feature/Auth/OtpAuthenticationTest.php — refuse un mauvais code puis bloque après 5 essais, même avec le bon code [MOB-01]`
- `Feature/Auth/PhoneNumberTest.php — normalise les saisies courantes au format E.164 [MOB-01]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-02

*Indispensable · mixte · Automatisé + manuel*

> Formulaire de candidature en plusieurs étapes, avec sauvegarde automatique : nom, prénoms, date de naissance, genre, téléphone, e-mail (facultatif), photo de profil, commune, localité, lieu type (école, université…), profession ou statut (élève, étudiant, commerçant…), langues parlées, centres d'intérêt, motivation.

**Scénario manuel** : Remplir les 3 étapes (identité, territoire, profil), quitter puis revenir : les réponses sont conservées.

**Tests automatisés**

- `Feature/Candidature/CandidatureApiTest.php — ouvre un dossier en brouillon au premier accès [MOB-02]`
- `Feature/Candidature/CandidatureApiTest.php — enregistre chaque étape séparément, même partiellement (sauvegarde automatique) [MOB-02]`
- `Feature/Candidature/CandidatureApiTest.php — rattache le dossier à une commune ouverte et à une localité de cette commune [MOB-02]`
- `Feature/Candidature/CandidatureWebTest.php — enregistre automatiquement chaque champ du dossier [MOB-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-03

*Indispensable · mixte · Automatisé + manuel*

> Téléversement de la pièce d'identité (recto/verso) et d'un selfie de contrôle. Les candidats mineurs sont soumis à une règle spécifique (voir section 9).

**Scénario manuel** : Déposer recto, verso et selfie ; tenter un PDF renommé en .jpg (refusé). Candidat de 16 ans : refusé tant que les mineurs ne sont pas admis.

**Tests automatisés**

- `Feature/Candidature/CandidatureDocumentsTest.php — stocke la pièce sur le disque privé, sous un nom aléatoire, avec son empreinte [MOB-03]`
- `Feature/Candidature/CandidatureWebTest.php — envoie une pièce depuis le site, l’affiche à son propriétaire uniquement [MOB-03, WEB-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-04

*Indispensable · mixte · Automatisé + manuel*

> Acceptation obligatoire et horodatée de la charte du Relais, des conditions d'utilisation et de la politique de confidentialité ; version acceptée conservée.

**Scénario manuel** : Accepter charte, CGU et confidentialité ; publier une nouvelle version depuis la console : une nouvelle acceptation est exigée.

**Tests automatisés**

- `Feature/Legal/LegalDocumentsTest.php — publie les trois textes en vigueur, sans HTML injectable [MOB-04, WEB-07]`
- `Feature/Legal/LegalDocumentsTest.php — enregistre une acceptation horodatée par version, avec le canal et l’adresse IP [MOB-04]`
- `Feature/Legal/LegalDocumentsTest.php — exige l’acceptation des textes avant le dépôt du dossier [MOB-04]`
- `Feature/Legal/LegalDocumentsTest.php — fait accepter les textes depuis le site, case par case [MOB-04, WEB-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-05

*Souhaitable · mixte · Automatisé + manuel*

> Module de formation intégré (vidéos courtes ou fiches) suivi d'un quiz ; score minimal paramétrable pour que la candidature soit transmise.

**Scénario manuel** : Suivre les modules, échouer au quiz (< score minimal) puis réussir : la candidature devient transmissible.

**Tests automatisés**

- `Feature/Formation/QuizTest.php — publie les fiches de formation [MOB-05]`
- `Feature/Formation/QuizTest.php — corrige le quiz et applique le score minimal paramétré [MOB-05]`
- `Feature/Formation/QuizTest.php — fait passer le quiz depuis le site, question par question [MOB-05, WEB-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-06

*Indispensable · mixte · Automatisé + manuel*

> Suivi de l'état de la candidature (reçue, en cours d'examen, complément demandé, acceptée, refusée avec motif).

**Scénario manuel** : Suivre la candidature à chaque décision du coordonnateur (examen, complément, acceptée, refusée avec motif).

**Tests automatisés**

- `Feature/Candidature/CandidatureApiTest.php — refuse le dépôt d’un dossier incomplet en listant ce qui manque [MOB-06]`
- `Feature/Candidature/CandidatureApiTest.php — dépose le dossier complet, le trace et le verrouille [MOB-06]`
- `Feature/Candidature/CandidatureReviewTest.php — passe le dossier en cours d’examen à son ouverture [MOB-06]`
- `Feature/Candidature/CandidatureWebTest.php — liste ce qui manque puis dépose le dossier complet [MOB-06]`
- `Feature/Candidature/CandidatureWebTest.php — affiche le motif d’un refus au candidat [MOB-06]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-07

*Souhaitable · mixte · Automatisé + manuel*

> Badge numérique du Relais (carte avec photo, numéro de Relais, commune, QR code de vérification) consultable dans l'application.

**Scénario manuel** : Afficher le badge après acceptation ; scanner le QR : page publique de vérification du badge.

**Tests automatisés**

- `Feature/Relais/RelaisBadgeTest.php — crée le profil de Relais à l’acceptation, avec un numéro unique [MOB-07]`
- `Feature/Relais/RelaisBadgeTest.php — fournit le badge à l’application, sans donnée sensible [MOB-07]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-10

*Indispensable · mobile · Équipe mobile*

> Bouton central « Relayer » accessible depuis tous les écrans, ouvrant la capture en un geste.

**Scénario manuel** : Bouton « Relayer » visible sur tous les écrans de l’application.

**Note** : Interface de l’application : recette par l’équipe mobile.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-11

*Indispensable · mixte · Automatisé + manuel*

> Capture intégrée : photo, vidéo (durée maximale paramétrable, par défaut 3 minutes), enregistrement audio (par défaut 10 minutes). Plusieurs médias par relais (par défaut jusqu'à 10).

**Scénario manuel** : Envoyer photo, vidéo de 3 min et audio ; tenter 11 médias ou une vidéo trop longue : refus avec message.

**Tests automatisés**

- `Feature/Api/ReferentielTest.php — expose les paramètres publics à l’application [MOB-11]`
- `Feature/Contribution/RelaisSubmissionTest.php — exige au moins un média entièrement reçu avant l’envoi [MOB-11]`
- `Feature/Media/MediaUploadTest.php — limite la taille, le nombre et les formats annoncés [MOB-11]`
- `Feature/Media/MediaUploadTest.php — refuse une vidéo plus longue que la durée maximale [MOB-11]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-12

*Indispensable · mixte · Automatisé + manuel*

> Import depuis la galerie, avec lecture des métadonnées d'origine (date de prise de vue, GPS si présent) et signalement « média importé » pour le coordonnateur.

**Scénario manuel** : Importer une photo de la galerie avec EXIF : le coordonnateur voit « média importé » et la date de prise de vue.

**Tests automatisés**

- `Feature/Media/MediaUploadTest.php — lit la durée, la date et la position d’une vidéo, et en tire une vignette [MOB-12, MOB-13]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-13

*Indispensable · mixte · Automatisé + manuel*

> Ajout automatique des métadonnées : position GPS et précision, date et heure (heure du serveur à la réception et heure de l'appareil), modèle d'appareil, orientation, durée ; déduction automatique de la commune.

**Scénario manuel** : Envoyer avec GPS : commune déduite du lieu de capture, précision et modèle d’appareil visibles en console.

**Tests automatisés**

- `Feature/Media/MediaUploadTest.php — lit la durée, la date et la position d’une vidéo, et en tire une vignette [MOB-12, MOB-13]`
- `Feature/Territoire/ResolveCommuneFromPointTest.php — déduit la commune qui contient la position GPS [MOB-13]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-14

*Indispensable · mixte · Automatisé + manuel*

> Formulaire d'accompagnement court : catégorie (liste paramétrable), titre, description en texte ou en note vocale, niveau d'urgence (normal, important, urgent), lieu modifiable si le GPS est imprécis.

**Scénario manuel** : Choisir catégorie, urgence, titre ; corriger le lieu quand le GPS est imprécis.

**Tests automatisés**

- `Feature/Api/ReferentielTest.php — liste les catégories de l’Annexe B dans l’ordre [MOB-14]`
- `Feature/Contribution/RelaisSubmissionTest.php — crée un relais en brouillon (envoi en cours) [MOB-14, MOB-19]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-15

*Indispensable · mixte · Automatisé + manuel*

> Questions de protection obligatoires : présence de mineurs identifiables ; accord des personnes filmées ; souhait du Relais d'être crédité ou de rester anonyme à l'antenne.

**Scénario manuel** : Tenter d’envoyer sans répondre aux questions de protection : refusé. Choisir pseudonyme : crédit affiché à l’antenne.

**Tests automatisés**

- `Feature/Contribution/RelaisSubmissionTest.php — refuse l’envoi sans les questions de protection ni la commune [MOB-15]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-16

*Indispensable · mixte · Automatisé + manuel*

> Envoi fractionné et reprenable : en cas de coupure, l'envoi reprend là où il s'est arrêté, sans tout renvoyer.

**Scénario manuel** : Couper le réseau à 50 % d’une vidéo, le rétablir : l’envoi reprend aux morceaux manquants.

**Note** : Voir docs/recette/reseau-degrade.md.

**Tests automatisés**

- `Feature/Contribution/RelaisSubmissionTest.php — est idempotent : le même client_uuid ne crée jamais deux relais [MOB-16]`
- `Feature/Media/MediaUploadTest.php — annonce un média et renvoie les URL signées de ses morceaux [MOB-16]`
- `Feature/Media/MediaUploadTest.php — reprend l’envoi là où il s’est arrêté, sans tout renvoyer [MOB-16]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-17

*Indispensable · mobile · Équipe mobile*

> Mode hors ligne : le relais est préparé et stocké chiffré sur le téléphone, puis envoyé automatiquement au retour du réseau ; choix « envoyer uniquement en Wi-Fi » pour les vidéos lourdes.

**Scénario manuel** : Préparer un relais en mode avion, rétablir le réseau : envoi automatique ; option « Wi-Fi seulement ».

**Note** : Stockage chiffré local : équipe mobile. Le serveur garantit l’idempotence (client_uuid).

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-18

*Souhaitable · mobile · Équipe mobile*

> Compression intelligente avant envoi (aperçu rapide envoyé en premier, fichier d'origine en second), avec option « qualité d'origine » pour les relais urgents.

**Scénario manuel** : Vérifier la compression avant envoi et l’option « qualité d’origine » pour un relais urgent.

**Note** : Compression côté application ; le serveur accepte plusieurs médias par relais.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-19

*Souhaitable · mixte · Automatisé + manuel*

> Brouillons : un relais peut être commencé puis terminé plus tard.

**Scénario manuel** : Commencer un relais, fermer l’application, le reprendre et l’envoyer.

**Tests automatisés**

- `Feature/Contribution/RelaisSubmissionTest.php — crée un relais en brouillon (envoi en cours) [MOB-14, MOB-19]`
- `Feature/Contribution/RelaisSubmissionTest.php — complète un brouillon, puis le verrouille après l’envoi [MOB-19]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-20

*Indispensable · mixte · Automatisé + manuel*

> Liste « Mes relais » avec statut en temps réel : envoi en cours, reçu, en validation, complément demandé, validé, rejeté (avec motif), sélectionné par la rédaction, diffusé.

**Scénario manuel** : Suivre un relais de l’envoi à la diffusion dans « Mes relais » ; motif visible en cas de rejet.

**Tests automatisés**

- `Feature/Contribution/RelaisSubmissionTest.php — liste mes relais, du plus récent au plus ancien, avec le statut public [MOB-20]`
- `Feature/Relais/RelaisWebSpaceTest.php — affiche mes relais et me laisse répondre à un complément [MOB-20, WEB-03]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-21

*Indispensable · mixte · Automatisé + manuel*

> Notifications push (et SMS de secours pour les messages importants) à chaque changement de statut, pour les appels à relais et les messages du coordonnateur.

**Scénario manuel** : Valider un relais en console : le Relais reçoit la notification (SMS de secours).

**Note** : Push FCM : jeton enregistré par l’API, envoi à brancher avec l’équipe mobile.

**Tests automatisés**

- `Feature/Auth/DeviceSessionTest.php — enregistre le jeton de notifications push de l’appareil courant [MOB-21]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-22

*Indispensable · mixte · Automatisé + manuel*

> Messagerie entre le Relais et son coordonnateur, rattachée à un relais ou générale ; possibilité de joindre un média complémentaire.

**Scénario manuel** : Échanger avec le coordonnateur sur un relais et en conversation générale.

**Tests automatisés**

- `Feature/Messagerie/MessagerieTest.php — laisse le Relais écrire à son coordonnateur et suivre la conversation [MOB-22]`
- `Feature/Relais/RelaisWebSpaceTest.php — écrit à son coordonnateur depuis le site [MOB-22]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-23

*Indispensable · mixte · Automatisé + manuel*

> Réception et consultation des appels à relais de sa zone, avec réponse directe.

**Scénario manuel** : Publier un appel ciblant la commune : le Relais le voit et y répond ; un Relais hors zone ne le voit pas.

**Tests automatisés**

- `Feature/Appels/AppelRelaisTest.php — cible les Relais des communes choisies et les prévient par SMS [ADM-14, MOB-23]`
- `Feature/Appels/AppelRelaisTest.php — montre au Relais les appels de sa zone seulement [MOB-23]`
- `Feature/Relais/RelaisWebSpaceTest.php — montre les appels de ma zone avec un lien pour y répondre [MOB-23]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-24

*Souhaitable · mixte · Automatisé + manuel*

> Tableau de bord personnel : nombre de relais envoyés, validés, diffusés ; taux de validation ; niveau et badges (ex. : Relais Bronze, Argent, Or).

**Scénario manuel** : Consulter le tableau de bord : envoyés, validés, diffusés, taux, niveau Bronze/Argent/Or et badges.

**Note** : API GET /api/v1/me/stats ; seuils dans Paramètres > Niveaux.

**Tests automatisés**

- `Feature/Relais/RelaisActivityTest.php — donne au Relais ses volumes, son taux de validation, son niveau et ses badges [MOB-24]`
- `Feature/Relais/RelaisActivityTest.php — présente un nouveau Relais sans taux de validation [MOB-24]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-25

*Souhaitable · mixte · Automatisé + manuel*

> Fil « Diffusé sur REFLET TV » montrant les relais du réseau passés à l'antenne (extraits validés par la rédaction), pour valoriser les Relais.

**Scénario manuel** : Publier un relais diffusé sur la vitrine : il apparaît dans le fil « Diffusé sur REFLET TV ».

**Note** : API GET /api/v1/diffusions.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — expose le fil « Diffusé sur REFLET TV » à l’application, sans donnée interne [MOB-25]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-26

*Optionnel · hors-lot · Hors lot*

> Programme de récompenses (points, primes, dotations) avec versement éventuel par Mobile Money.

**Scénario manuel** : —

**Note** : Optionnel : non retenu au lot 1 (règles de récompense à définir, Annexe C).

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-30

*Indispensable · mobile · Équipe mobile*

> Les médias capturés dans l'application ne sont pas copiés dans la galerie publique du téléphone (option), et sont effacés du téléphone après envoi confirmé.

**Scénario manuel** : Vérifier que les médias capturés n’apparaissent pas dans la galerie et sont effacés après envoi confirmé (statut « ready »).

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-31

*Souhaitable · mixte · Automatisé + manuel*

> Verrouillage de l'application par code ou biométrie ; déconnexion à distance par l'administrateur en cas de perte ou de vol déclaré.

**Scénario manuel** : Déconnecter un appareil à distance depuis la console : le jeton est refusé à l’appel suivant. Verrouillage par code : application.

**Tests automatisés**

- `Feature/Auth/DeviceSessionTest.php — se déconnecte : le jeton de l’appareil devient inutilisable [MOB-31]`
- `Feature/Auth/DeviceSessionTest.php — permet à l’administrateur de révoquer un appareil perdu ou volé [MOB-31]`
- `Feature/Relais/RelaisManagementTest.php — déconnecte à distance le téléphone perdu d’un Relais depuis sa fiche [MOB-31]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-32

*Souhaitable · mobile · Équipe mobile*

> Rappel de sécurité avant la capture sur les sujets sensibles (manifestations, accidents, incendies) : ne pas se mettre en danger, ne pas gêner les secours.

**Scénario manuel** : Choisir « Manifestation » : le rappel de sécurité s’affiche avant la capture.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### MOB-33

*Optionnel · hors-lot · Hors lot*

> Bouton d'alerte discret permettant au Relais en difÏculté de prévenir son coordonnateur avec sa position.

**Scénario manuel** : —

**Note** : Optionnel : non retenu au lot 1.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-01

*Indispensable · serveur · Automatisé + manuel*

> Pages de présentation : le principe, comment devenir Relais, la charte, questions fréquentes, contact, liens de téléchargement de l'application.

**Scénario manuel** : Parcourir accueil, principe, devenir Relais, charte, FAQ, contact, liens de téléchargement.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — transmet le formulaire de contact, avec limite et piège à robots [WEB-01]`
- `Feature/PublicPagesTest.php — affiche la page d’accueil publique [WEB-01]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-02

*Indispensable · serveur · Automatisé + manuel*

> Candidature en ligne identique à celle de l'application (MOB-02 à MOB-06), avec vérification par code SMS.

**Scénario manuel** : Candidater depuis le site avec vérification par SMS, jusqu’à la soumission.

**Tests automatisés**

- `Feature/Candidature/CandidatureWebTest.php — affiche la page Devenir Relais [WEB-02]`
- `Feature/Candidature/CandidatureWebTest.php — crée le compte candidat après le code SMS et ouvre le dossier [WEB-02]`
- `Feature/Candidature/CandidatureWebTest.php — envoie une pièce depuis le site, l’affiche à son propriétaire uniquement [MOB-03, WEB-02]`
- `Feature/Formation/QuizTest.php — fait passer le quiz depuis le site, question par question [MOB-05, WEB-02]`
- `Feature/Legal/LegalDocumentsTest.php — fait accepter les textes depuis le site, case par case [MOB-04, WEB-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-03

*Indispensable · serveur · Automatisé + manuel*

> Espace Relais : connexion, envoi d'un relais par téléversement de fichiers (photos, vidéos, audio) avec formulaire d'accompagnement, suivi des statuts, messagerie, profil.

**Scénario manuel** : Se connecter à l’espace Relais, envoyer un relais, suivre son statut, échanger avec le coordonnateur, consulter ses données.

**Tests automatisés**

- `Feature/Relais/RelaisWebSpaceTest.php — prépare un brouillon et envoie un relais depuis le site, avec les questions de protection [WEB-03]`
- `Feature/Relais/RelaisWebSpaceTest.php — affiche mes relais et me laisse répondre à un complément [MOB-20, WEB-03]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-04

*Indispensable · serveur · Automatisé + manuel*

> Téléversement de fichiers volumineux fractionné et reprenable, avec barre de progression.

**Scénario manuel** : Envoyer une vidéo de 300 Mo depuis le navigateur, couper le réseau à mi-parcours puis le rétablir et cliquer « Réessayer » : seuls les morceaux manquants repartent, barre de progression comprise.

**Note** : Après un rechargement de page, le navigateur ne garde pas le fichier : il faut le choisir de nouveau (reprise automatique après rechargement : piste d’amélioration).

**Tests automatisés**

- `Feature/Relais/RelaisWebSpaceTest.php — dépose un média depuis le navigateur par le même protocole que l’application [WEB-04]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-05

*Souhaitable · serveur · Automatisé + manuel*

> Vitrine « Vu sur REFLET TV » : sélection de relais diffusés, publiée par la rédaction, avec crédit du Relais ou mention anonyme selon son choix.

**Scénario manuel** : Publier un relais diffusé sur la vitrine avec crédit nominatif puis anonyme.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — publie un relais diffusé sur la vitrine, avec un texte rédigé par la rédaction [WEB-05, ADM-17]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-06

*Optionnel · serveur · Automatisé + manuel*

> Carte publique du réseau : nombre de Relais par commune (sans aucune donnée personnelle).

**Scénario manuel** : Ouvrir la carte publique : nombre de Relais par commune, aucune donnée personnelle.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — publie la carte du réseau sans aucune donnée personnelle [WEB-06, §9.4]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-07

*Indispensable · serveur · Automatisé + manuel*

> Pages légales : conditions d'utilisation, politique de confidentialité, mentions légales, gestion des cookies.

**Scénario manuel** : Ouvrir CGU, confidentialité, mentions légales et cookies.

**Tests automatisés**

- `Feature/Legal/LegalDocumentsTest.php — publie les trois textes en vigueur, sans HTML injectable [MOB-04, WEB-07]`
- `Feature/Legal/LegalDocumentsTest.php — affiche les textes sur le site public [WEB-07]`
- `Feature/Public/PublicSiteTest.php — sert les pages légales, cookies, plan du site et robots [WEB-07, WEB-08]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### WEB-08

*Souhaitable · serveur · Automatisé + manuel*

> Référencement naturel de base (titres, descriptions, partage sur les réseaux sociaux).

**Scénario manuel** : Vérifier titres, descriptions, Open Graph, sitemap.xml et robots.txt.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — sert les pages légales, cookies, plan du site et robots [WEB-07, WEB-08]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-01

*Indispensable · serveur · Automatisé + manuel*

> File des candidatures de la commune, triée par date, avec dossier complet : identité, pièce d'identité, selfie, localité, motivation, score au quiz.

**Scénario manuel** : Ouvrir la file des candidatures de sa commune et un dossier complet (pièces, selfie, quiz).

**Tests automatisés**

- `Feature/Candidature/CandidatureReviewTest.php — montre au coordonnateur la file de sa seule commune, du plus ancien au plus récent [COO-01]`
- `Feature/Candidature/CandidatureReviewTest.php — refuse l’accès au dossier d’une autre commune (aucune fuite) [COO-01]`
- `Feature/Contribution/RelaisValidationTest.php — refuse l’accès au relais d’une autre commune [COO-01, §8.2]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-02

*Indispensable · serveur · Automatisé + manuel*

> Décision : accepter, refuser (motif obligatoire choisi dans une liste et commentaire), mettre en attente, demander un complément. Chaque décision est notifiée au candidat.

**Scénario manuel** : Accepter, refuser avec motif, mettre en attente, demander un complément : le candidat est notifié.

**Tests automatisés**

- `Feature/Candidature/CandidatureReviewTest.php — accepte un candidat : il devient Relais et est prévenu par SMS [COO-02]`
- `Feature/Candidature/CandidatureReviewTest.php — exige un motif de la liste pour refuser, sans le divulguer par SMS [COO-02]`
- `Feature/Candidature/CandidatureReviewTest.php — demande un complément avec un message, ce qui rouvre le dossier au candidat [COO-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-03

*Souhaitable · serveur · Automatisé + manuel*

> Aide à la vérification : comparaison visuelle photo / pièce d'identité, alerte en cas de doublon (même numéro de téléphone, même pièce, même appareil).

**Scénario manuel** : Comparer selfie et pièce ; créer un doublon de téléphone ou d’appareil : alerte affichée.

**Tests automatisés**

- `Feature/Candidature/CandidatureReviewTest.php — signale les doublons : même pièce, même identité, même appareil [COO-03]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-04

*Indispensable · serveur · Automatisé + manuel*

> Gestion des Relais de la commune : liste, fiche, historique, statistiques, suspension ou radiation motivée (avec information de l'administrateur).

**Scénario manuel** : Ouvrir la fiche d’un Relais, suspendre puis réactiver avec motif : l’administration est informée.

**Tests automatisés**

- `Feature/Relais/RelaisManagementTest.php — liste les Relais de la commune du coordonnateur seulement [COO-04]`
- `Feature/Relais/RelaisManagementTest.php — montre la fiche, l’historique et la fiabilité du Relais [COO-04]`
- `Feature/Relais/RelaisManagementTest.php — suspend un Relais avec un motif, informe l’administration et bloque ses envois [COO-04]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-10

*Indispensable · serveur · Automatisé + manuel*

> File de validation en temps réel, avec tri par urgence puis par ancienneté ; signal sonore et notification pour les relais urgents.

**Scénario manuel** : Recevoir un relais urgent : il passe en tête de file, signal sonore et notification.

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — trie la file par urgence puis par ancienneté, dans la seule commune du coordonnateur [COO-10]`
- `Feature/Contribution/RelaisValidationTest.php — signale l’arrivée d’un nouveau relais urgent [COO-10]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-11

*Indispensable · serveur · Automatisé + manuel*

> Lecteur intégré pour photos, vidéos et audio, avec zoom, lecture image par image et vitesse réglable.

**Scénario manuel** : Lire photo (zoom), vidéo (image par image, vitesse) et audio dans la console.

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — affiche le lecteur intégré : zoom photo, image par image et vitesse réglable [COO-11]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-12

*Indispensable · serveur · Automatisé + manuel*

> Panneau de vérification : position sur une carte, écart entre lieu déclaré et GPS, date de prise de vue et date d'envoi, média importé ou capturé en direct, historique et taux de fiabilité du Relais.

**Scénario manuel** : Contrôler la carte, l’écart lieu déclaré / GPS, les dates, l’origine du média et la fiabilité du Relais.

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — alerte sur un média importé, une commune déclarée et un GPS imprécis [COO-12]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-13

*Souhaitable · serveur · Automatisé + manuel*

> Détection de doublons : alerte si un média identique ou très proche a déjà été envoyé (par le même Relais ou un autre).

**Scénario manuel** : Renvoyer la même photo depuis un autre Relais : alerte de doublon.

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — signale un média identique déjà envoyé dans un autre relais [COO-13]`
- `Feature/Media/MediaUploadTest.php — contrôle la photo reçue : type, empreinte, dimensions, vignette sans EXIF, empreinte perceptuelle [§10, COO-13]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-14

*Indispensable · serveur · Automatisé + manuel*

> Décisions : valider ; valider avec corrections (titre, description, catégorie, lieu) ; demander un complément au Relais ; rejeter avec motif ; signaler comme sensible pour arbitrage de la rédaction.

**Scénario manuel** : Valider, valider avec corrections, demander un complément, rejeter avec motif, signaler comme sensible.

**Tests automatisés**

- `Feature/Contribution/RelaisLifecycleTest.php — laisse le Relais répondre à un complément, ce qui remet le relais dans la file avec un nouveau délai [COO-14]`
- `Feature/Contribution/RelaisValidationTest.php — valide avec corrections, trace l’avant/après et prévient le Relais [COO-14]`
- `Feature/Contribution/RelaisValidationTest.php — rejette avec un motif de la liste des relais [COO-14, COO-15]`
- `Feature/Contribution/RelaisValidationTest.php — demande un complément ou transmet à la rédaction, avec un commentaire obligatoire [COO-14]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-15

*Indispensable · serveur · Automatisé + manuel*

> Motifs de rejet paramétrables : hors sujet, qualité insufÏsante, information non vérifiable, contenu ancien, contenu déjà publié ailleurs, atteinte à la dignité, mineurs identifiables, contenu dangereux ou illicite, doublon.

**Scénario manuel** : Ajouter ou désactiver un motif de rejet dans Référentiels : la liste du coordonnateur est à jour.

**Tests automatisés**

- `Feature/Api/ReferentielTest.php — seed les motifs de rejet des relais (COO-15) et des candidatures`
- `Feature/Contribution/RelaisValidationTest.php — rejette avec un motif de la liste des relais [COO-14, COO-15]`
- `Feature/Settings/ReferentielsTest.php — ajoute un motif de rejet propre aux relais, en fin de liste [ADM-23, COO-15]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-16

*Indispensable · serveur · Automatisé + manuel*

> Indication obligatoire pour valider un relais contenant des mineurs ou des personnes identifiables : floutage nécessaire oui / non.

**Scénario manuel** : Valider un relais avec mineurs sans répondre « floutage » : refusé.

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — exige l’indication de floutage pour valider un relais avec des mineurs [COO-16]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-17

*Indispensable · serveur · Automatisé + manuel*

> Délai de traitement cible paramétrable (par défaut 30 minutes pour un relais urgent, 4 heures pour un relais normal). Au-delà, le relais est signalé en retard et remonte au suppléant, au superviseur régional ou à l'administrateur.

**Scénario manuel** : Laisser un relais urgent 31 min sans décision : signalé en retard et remonté au suppléant.

**Tests automatisés**

- `Feature/Contribution/RelaisLifecycleTest.php — escalade un relais urgent en retard au suppléant, puis au superviseur, puis à l’administration [COO-17]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-18

*Souhaitable · serveur · Automatisé + manuel*

> Note interne attachée au relais, visible par la rédaction et non par le Relais.

**Scénario manuel** : Ajouter une note interne : visible par la rédaction, jamais par le Relais (API et web).

**Tests automatisés**

- `Feature/Contribution/RelaisValidationTest.php — enregistre une note interne invisible du Relais [COO-18]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-20

*Indispensable · serveur · Automatisé + manuel*

> Messagerie avec chaque Relais et message groupé à tous les Relais de la commune.

**Scénario manuel** : Répondre à un Relais et envoyer un message groupé à la commune.

**Tests automatisés**

- `Feature/Messagerie/MessagerieTest.php — répond depuis la console et prévient le Relais par SMS au plus une fois par heure [COO-20]`
- `Feature/Messagerie/MessagerieTest.php — envoie un message groupé à tous les Relais actifs de la commune [COO-20]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-21

*Souhaitable · serveur · Automatisé + manuel*

> Relais des appels à relais de la rédaction vers sa commune, et création d'appels locaux (soumis à l'accord de l'administrateur si paramétré).

**Scénario manuel** : Créer un appel local (avec accord de l’administrateur si paramétré) et relayer un appel de la rédaction.

**Tests automatisés**

- `Feature/Appels/AppelRelaisTest.php — limite l’appel local d’un coordonnateur à ses communes et peut exiger l’accord de l’administrateur [COO-21]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### COO-22

*Indispensable · serveur · Automatisé + manuel*

> Tableau de bord de la commune : Relais actifs, relais reçus, validés, rejetés, diffusés ; délai moyen de validation ; carte de couverture par quartier.

**Scénario manuel** : Contrôler les chiffres du tableau de bord de la commune et la couverture par quartier.

**Tests automatisés**

- `Feature/Relais/RelaisManagementTest.php — affiche le tableau de bord de la commune avec ses vrais chiffres [COO-22]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-01

*Indispensable · serveur · Automatisé + manuel*

> Flux en direct de tous les relais dès leur envoi, avec leur statut (en attente, validé, rejeté…), mis à jour sans recharger la page.

**Scénario manuel** : Garder le flux ouvert pendant qu’un Relais envoie : le relais apparaît sans recharger.

**Tests automatisés**

- `Feature/Console/ConsoleMonitoringTest.php — montre à la rédaction tous les relais du réseau, avec leur statut [ADM-01]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-02

*Indispensable · serveur · Automatisé + manuel*

> Carte interactive de la Côte d'Ivoire : relais par commune, regroupements, filtres par période, catégorie, urgence et statut ; zoom jusqu'au quartier.

**Scénario manuel** : Filtrer la carte par période, catégorie, urgence et statut ; zoomer jusqu’au quartier.

**Tests automatisés**

- `Feature/Console/ConsoleMonitoringTest.php — place les relais localisés sur la carte, filtrables par période [ADM-02]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-03

*Indispensable · serveur · Automatisé + manuel*

> Alertes : relais urgents, afÒux inhabituel de relais sur une même zone (signal d'un événement), relais en retard de validation.

**Scénario manuel** : Envoyer 5 relais en 30 min sur une commune : alerte d’afflux ; alertes urgents et retards.

**Tests automatisés**

- `Feature/Console/ConsoleMonitoringTest.php — signale un afflux inhabituel de relais sur une même commune [ADM-03]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-04

*Indispensable · serveur · Automatisé + manuel*

> Filtres et recherche : texte, commune, région, catégorie, Relais, coordonnateur, période, type de média, appel à relais.

**Scénario manuel** : Combiner les filtres texte, commune, région, catégorie, Relais, coordonnateur, période, média, appel.

**Tests automatisés**

- `Feature/Console/ConsoleMonitoringTest.php — filtre par texte, commune, catégorie, statut, urgence et Relais [ADM-04]`
- `Feature/Console/ConsoleMonitoringTest.php — ignore sans erreur des filtres invalides saisis dans l’URL [ADM-04]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-05

*Souhaitable · serveur · Automatisé + manuel*

> Vue « salle de rédaction » plein écran pour afÏchage sur un grand écran.

**Scénario manuel** : Afficher la salle de rédaction en plein écran sur un grand écran.

**Tests automatisés**

- `Feature/Console/ConsoleMonitoringTest.php — affiche la salle de rédaction en plein écran [ADM-05]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-10

*Indispensable · serveur · Automatisé + manuel*

> Sélection d'un relais validé par un membre de la rédaction, avec affectation à un journaliste ou à une émission.

**Scénario manuel** : Sélectionner un relais validé et l’affecter à un journaliste et une émission.

**Tests automatisés**

- `Feature/Editorial/EditorialTest.php — sélectionne un relais validé et l’affecte à un journaliste et une émission [ADM-10]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-11

*Indispensable · serveur · Automatisé + manuel*

> Téléchargement des médias en qualité d'origine, individuellement ou par lot, avec fiche d'accompagnement (titre, description, lieu, date, heure, Relais, crédit à afÏcher, consignes de floutage).

**Scénario manuel** : Télécharger un original puis un lot ZIP avec fiche PDF d’accompagnement.

**Tests automatisés**

- `Feature/Editorial/EditorialTest.php — télécharge un ZIP des originaux avec une fiche PDF, et trace l’export [ADM-11]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-12

*Indispensable · serveur · Automatisé + manuel*

> Blocage de l'export des relais non validés ; dérogation possible par un administrateur, avec motif obligatoire et trace dans le journal d'audit.

**Scénario manuel** : Tenter d’exporter un relais non validé : bloqué ; dérogation administrateur avec motif tracée dans l’audit.

**Tests automatisés**

- `Feature/Editorial/EditorialTest.php — bloque l’export d’un relais non validé, sauf dérogation motivée d’un administrateur [ADM-12]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-13

*Indispensable · serveur · Automatisé + manuel*

> Marquage « diffusé » avec date, émission et lien éventuel ; notification automatique du Relais.

**Scénario manuel** : Marquer diffusé (date, émission, lien) : le Relais est notifié.

**Tests automatisés**

- `Feature/Editorial/EditorialTest.php — marque un relais diffusé avec date, émission et lien, et prévient le Relais [ADM-13]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-14

*Indispensable · serveur · Automatisé + manuel*

> Création et suivi des appels à relais : zone ciblée (communes, régions, rayon), type de lieu, sujet, consignes, date limite ; statistiques de réponse.

**Scénario manuel** : Créer un appel ciblant communes, région ou rayon, avec type de lieu et date limite ; suivre ses réponses.

**Tests automatisés**

- `Feature/Appels/AppelRelaisTest.php — cible les Relais des communes choisies et les prévient par SMS [ADM-14, MOB-23]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-15

*Souhaitable · serveur · Automatisé + manuel*

> Collections : regroupement de relais par sujet ou par événement (ex. : « Inondations juin »).

**Scénario manuel** : Créer la collection « Inondations juin », y ajouter puis retirer des relais.

**Tests automatisés**

- `Feature/Editorial/EditorialTest.php — regroupe des relais en collection et les télécharge en lot [ADM-15]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-16

*Souhaitable · hors-lot · Hors lot*

> Export vers le système de gestion des médias ou la régie de REFLET TV (dossier partagé, FTP/SFTP ou API) [ À COMPLÉTER ].

**Scénario manuel** : Export par lot ZIP disponible ; connecteur régie à définir.

**Note** : Point ouvert [À COMPLÉTER] : protocole de la régie REFLET TV (dossier partagé, SFTP ou API) à fournir.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-17

*Optionnel · serveur · Automatisé + manuel*

> Publication sur la vitrine web « Vu sur REFLET TV » et préparation de publications pour les réseaux sociaux de la chaîne.

**Scénario manuel** : Publier sur la vitrine ; la préparation de publications réseaux sociaux reste manuelle.

**Note** : Optionnel : vitrine livrée, réseaux sociaux non retenus.

**Tests automatisés**

- `Feature/Public/PublicSiteTest.php — publie un relais diffusé sur la vitrine, avec un texte rédigé par la rédaction [WEB-05, ADM-17]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-20

*Indispensable · serveur · Automatisé + manuel*

> Gestion du référentiel géographique : districts, régions, communes, localités, lieux types.

**Scénario manuel** : Importer les contours, ouvrir une commune, ajouter une localité et un type de lieu dans Référentiels.

**Note** : Districts et régions : par l’import GeoJSON officiel.

**Tests automatisés**

- `Feature/Console/ConsolePagesTest.php — ouvre puis ferme une commune, avec confirmation et audit [ADM-20]`
- `Feature/Settings/ReferentielsTest.php — ajoute une localité à une commune et l’audite [ADM-20]`
- `Feature/Settings/ReferentielsTest.php — refuse une localité en double dans la même commune [ADM-20]`
- `Feature/Settings/ReferentielsTest.php — ajoute puis renomme un type de lieu [ADM-20]`
- `Feature/Territoire/ImportCommunesTest.php — importe les communes, leurs régions et leurs contours PostGIS [ADM-20]`
- `Feature/Territoire/ImportCommunesTest.php — est idempotent : un second import met à jour sans dupliquer [ADM-20]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-21

*Indispensable · serveur · Automatisé + manuel*

> Gestion des coordonnateurs et superviseurs : création, rattachement à une ou plusieurs communes, suppléance, suspension.

**Scénario manuel** : Créer un coordonnateur titulaire et un suppléant, un superviseur régional ; suspendre un membre.

**Tests automatisés**

- `Feature/Console/ConsolePagesTest.php — ajoute un coordonnateur rattaché à sa commune et lui envoie un lien d’activation [ADM-21]`
- `Feature/Console/ConsolePagesTest.php — désigne un coordonnateur suppléant d’une commune [ADM-21]`
- `Feature/Console/ConsolePagesTest.php — suspend un membre et coupe ses accès, sans pouvoir se suspendre soi-même [ADM-21]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-22

*Indispensable · serveur · Automatisé + manuel*

> Gestion des Relais au niveau national : recherche, fiche, historique, suspension, radiation, réintégration, transfert de commune.

**Scénario manuel** : Rechercher un Relais national, le suspendre, le radier, le réintégrer, le transférer de commune.

**Tests automatisés**

- `Feature/Relais/RelaisManagementTest.php — réserve la réintégration d’un Relais radié à l’administration [ADM-22]`
- `Feature/Relais/RelaisManagementTest.php — transfère un Relais de commune (administration) [ADM-22]`
- `Feature/Relais/RelaisManagementTest.php — révoque les jetons de l’application à la radiation [ADM-22]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-23

*Indispensable · serveur · Automatisé + manuel*

> Paramétrage : catégories, motifs de rejet, délais de validation, durées et tailles maximales des médias, textes de la charte et des conditions, niveaux et badges.

**Scénario manuel** : Modifier catégories, motifs, délais, limites des médias, textes juridiques, niveaux : chaque changement est audité.

**Tests automatisés**

- `Feature/Console/ConsolePagesTest.php — enregistre un groupe de paramètres et l’audite [ADM-23]`
- `Feature/Legal/LegalDocumentsTest.php — publie une nouvelle version depuis la console, après confirmation [ADM-23]`
- `Feature/Settings/ReferentielsTest.php — désactive une catégorie sans la supprimer [ADM-23]`
- `Feature/Settings/ReferentielsTest.php — ajoute un motif de rejet propre aux relais, en fin de liste [ADM-23, COO-15]`
- `Feature/Settings/SettingsTest.php — retourne la valeur par défaut du cahier des charges [ADM-23]`
- `Feature/Settings/SettingsTest.php — enregistre une modification de l’administrateur, la met en cache et l’audite [ADM-23, ADM-25]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-24

*Indispensable · serveur · Automatisé + manuel*

> Gestion des utilisateurs de la console et de leurs rôles (administrateur, rédacteur en chef, journaliste, lecture seule) avec double authentification obligatoire.

**Scénario manuel** : Créer un compte par rôle ; vérifier que la 2FA est imposée avant toute page.

**Tests automatisés**

- `Feature/Console/ConsoleAccessTest.php — impose la double authentification à l’équipe avant toute page de la console [ADM-24]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-25

*Indispensable · serveur · Automatisé + manuel*

> Journal d'audit non modifiable : connexions, décisions de validation, exports, dérogations, modifications de paramètres.

**Scénario manuel** : Vérifier l’intégrité du journal (console et audit:verify) ; tenter un UPDATE SQL : refusé par le trigger.

**Tests automatisés**

- `Feature/Audit/AuditLogTest.php — inscrit une action avec son auteur et son sujet [ADM-25]`
- `Feature/Audit/AuditLogTest.php — refuse toute modification au niveau de la base (trigger) [ADM-25]`
- `Feature/Audit/AuditLogTest.php — refuse toute suppression au niveau de la base (trigger) [ADM-25]`
- `Feature/Auth/OtpAuthenticationTest.php — trace les demandes et connexions dans l’audit sans jamais y écrire le code [ADM-25]`
- `Feature/Console/ConsolePagesTest.php — vérifie l’intégrité du journal depuis la console [ADM-25]`
- `Feature/Settings/SettingsTest.php — enregistre une modification de l’administrateur, la met en cache et l’audite [ADM-23, ADM-25]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-26

*Indispensable · serveur · Automatisé + manuel*

> Tableaux de bord et rapports exportables (Excel, PDF) : voir section 13.

**Scénario manuel** : Exporter chaque famille de rapports en Excel et PDF ; vérifier l’envoi périodique.

**Tests automatisés**

- `Feature/Reporting/ReportsTest.php — affiche les rapports et les exporte en Excel et en PDF [ADM-26]`

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______

### ADM-27

*Optionnel · hors-lot · Hors lot*

> Gestion des récompenses : règles de points, validation des primes, suivi des paiements.

**Scénario manuel** : —

**Note** : Optionnel : dépend de MOB-26.

**Résultat** : ☐ Conforme · ☐ Anomalie mineure · ☐ Anomalie majeure · ☐ Anomalie bloquante — Testeur : ______ Date : ______
