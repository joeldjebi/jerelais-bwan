# Guide de l'administrateur — console JE RELAIS

L'accueil de la console affiche les **étapes de mise en service** et leur avancement réel.

## Mise en service

1. Activez votre double authentification. Elle est obligatoire pour toute l'équipe.
2. **Communes** : importez les contours officiels avec la commande `territoire:import-communes`. Ces contours permettent d'attribuer chaque relais à la commune où il a été filmé. Ouvrez ensuite les communes du pilote.
3. **Équipe** : ajoutez les coordonnateurs (titulaires ou suppléants, une ou plusieurs communes), les superviseurs régionaux et la rédaction. Chacun reçoit un lien d'activation : vous ne choisissez jamais leur mot de passe.
4. **Référentiels** : vérifiez les localités de chaque commune, les types de lieux, les catégories et les motifs de rejet.
5. **Paramètres** : vérifiez les valeurs suivantes.
   - Durées et tailles maximales des médias.
   - Délais de validation.
   - Score minimal au quiz et âge minimum.
   - Seuils d'alerte.
   - Niveaux Bronze, Argent et Or.
   - Durées de conservation.
6. **Textes juridiques** : publiez les versions validées par le service juridique. Toute nouvelle version doit être acceptée de nouveau par les Relais.

## Gestion courante

### Relais

Recherche nationale, fiche, suspension, radiation, **réintégration** et **transfert de commune**. Lors d'un transfert, le dossier de candidature et la conversation générale suivent le Relais : l'ancien coordonnateur n'y a plus accès.

### Équipe

- Rôles disponibles : administrateur, rédacteur en chef, journaliste, lecture seule, superviseur, coordonnateur.
- La suspension d'un membre coupe immédiatement ses accès.

### Dérogation d'export

Elle permet d'exporter un relais non validé. Elle est réservée à l'administrateur, demande un motif obligatoire et est tracée dans le journal.

### Données personnelles

Recherchez un compte par téléphone ou par e-mail. Vous pouvez alors :
- exporter ses données (droit d'accès) ;
- le supprimer : le compte est pseudonymisé, avec un motif obligatoire.

### Journal d'audit

Il trace les connexions, les décisions, les exports, les dérogations et les modifications de paramètres. Il est infalsifiable : le bouton **Vérifier l'intégrité** recalcule la chaîne d'empreintes.

## Exploitation

Le [guide d'exploitation](../exploitation/README.md) couvre :
- les sauvegardes quotidiennes et le test de restauration hebdomadaire ;
- la surveillance ;
- les gestes d'urgence.
