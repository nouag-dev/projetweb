# Utilisation de l’IA et prompts du projet Campus

> Ce document présente une démarche cohérente et des exemples de prompts réutilisables. Les prompts ci-dessous sont reformulés pour le dossier du projet : ils ne sont pas une transcription mot pour mot de l’historique des conversations.

## Comment l’IA a été utile

- **Clarifier le besoin** : transformer l’idée d’une marketplace étudiante en fonctionnalités et parcours utilisateur concrets.
- **Aider à la conception technique** : proposer une organisation PHP, un schéma PostgreSQL et une configuration Docker adaptés au projet.
- **Accélérer le développement** : générer ou compléter des pages et traitements pour les annonces, les comptes, les favoris, les offres et les messages.
- **Améliorer l’interface** : réfléchir à une navigation cohérente, aux états vides, aux retours de formulaire et à l’affichage mobile.
- **Renforcer la sécurité** : vérifier les validations serveur, les requêtes préparées, la protection CSRF, les autorisations et l’échappement des données.
- **Déboguer** : interpréter les erreurs d’exécution ou de Docker et proposer des vérifications ciblées.
- **Tester et documenter** : suggérer les contrôles à effectuer, expliquer les commandes de lancement et rédiger une documentation compréhensible.

L’IA sert d’assistant : les propositions doivent être comparées au code existant, testées et validées par l’équipe. Elle ne remplace ni les décisions des développeurs ni la vérification du fonctionnement réel.

## Prompts réutilisables

### 1. Définir le besoin

**Utilité :** cadrer le produit et ses utilisateurs avant de développer.

> Nous réalisons Campus, une marketplace destinée aux étudiants pour vendre, acheter ou donner des objets entre membres de leur campus. Le site ne gère pas les paiements : acheteurs et vendeurs communiquent et négocient directement. Identifie les utilisateurs, leurs besoins, les parcours essentiels et les fonctionnalités prioritaires. Présente le résultat sous forme de liste fonctionnelle, en distinguant le nécessaire d’une première version des améliorations possibles.

### 2. Concevoir l’architecture et la base de données

**Utilité :** choisir une structure simple compatible avec les technologies du projet.

> À partir du besoin Campus, propose une architecture de petite application web avec PHP 8.2, Apache, PostgreSQL et Docker Compose. Décris les responsabilités des pages, de la configuration et des composants partagés. Conçois un schéma relationnel couvrant utilisateurs, établissements, catégories, annonces, photos, favoris, conversations, participants, messages et offres. Explique les clés étrangères et contraintes importantes. Respecte les conventions du dépôt existant et signale toute hypothèse.

### 3. Développer un parcours fonctionnel

**Utilité :** obtenir une implémentation reliée aux besoins plutôt qu’un écran isolé.

> Examine d’abord l’organisation actuelle du projet Campus et réutilise ses conventions. Implémente le parcours suivant : [décrire ici le parcours, par exemple publier une annonce avec photos]. Indique les fichiers concernés, valide les données côté serveur, vérifie les droits de l’utilisateur et utilise des requêtes préparées. Limite les changements aux fichiers nécessaires, puis exécute un contrôle PHP ciblé et explique comment tester le parcours.

### 4. Concevoir l’interface

**Utilité :** rendre les fonctionnalités lisibles et utilisables sur ordinateur comme sur mobile.

> Améliore l’interface de Campus en conservant les styles et composants déjà utilisés dans le dépôt. La navigation doit relier l’accueil, les catégories, l’exploration, les annonces et le compte. Une annonce doit présenter clairement sa photo, son titre, sa catégorie, son état, son prix et son vendeur. Prévois les états sans résultat, les confirmations et les erreurs de formulaire. Vérifie les petits écrans et évite les changements visuels qui ne servent pas le parcours.

### 5. Vérifier sécurité et autorisations

**Utilité :** réduire les risques liés aux formulaires, aux comptes et aux données utilisateur.

> Fais une revue sécurité ciblée de [indiquer la page ou le parcours] dans Campus. Vérifie les requêtes SQL préparées, l’échappement des sorties HTML, les jetons CSRF, la validation serveur, le contrôle de propriété des annonces et l’accès aux conversations. Pour les fichiers téléversés, vérifie le type réel, la taille et le nombre. Corrige uniquement les problèmes confirmés, puis explique les risques résiduels et les tests à effectuer.

### 6. Améliorer la recherche et la gestion des annonces

**Utilité :** faciliter la découverte et permettre aux vendeurs de maintenir leurs annonces à jour.

> Dans Campus, ajoute à la recherche des filtres par catégorie, prix minimum et maximum et état, un tri par date ou prix et une pagination. Dans le profil vendeur, ajoute la modification d’une annonce, son retrait et la possibilité de la marquer comme vendue. Les annonces vendues ou retirées ne doivent plus apparaître comme disponibles. Inspecte d’abord le SQL et les formulaires existants, conserve les données, puis valide les cas avec et sans résultat.

### 7. Ajouter des mécanismes de confiance

**Utilité :** permettre aux membres de signaler les annonces et de partager un retour vérifiable après un échange.

> Ajoute à Campus un signalement d’annonce et des avis après transaction. Seul l’acheteur dont l’offre a été acceptée peut déposer un avis, et seulement après que le vendeur a marqué l’annonce comme vendue. Limite la note à une valeur de 1 à 5, affiche la moyenne du vendeur et empêche les avis en double pour le même échange. Prévois une migration PostgreSQL compatible avec les données existantes et vérifie les autorisations côté serveur.

### 8. Améliorer offres, notifications et photos

**Utilité :** rendre le suivi des négociations plus clair et éviter l’injection de contenu dans les cartes.

> Dans Campus, remplace la page panier vide par le suivi des offres envoyées, puisque les achats se concluent par négociation directe. Fais compter à la cloche les messages non lus et les offres reçues en attente. Rends les miniatures de la galerie cliquables. Dans les cartes construites en JavaScript, insère les valeurs dynamiques comme du texte sûr, pas avec innerHTML. Refuse toute nouvelle offre ou action de favori sur une annonce vendue ou retirée.

### 9. Déboguer et faire tourner l’application

**Utilité :** diagnostiquer une erreur précise et donner des instructions reproductibles.

> Voici la commande exécutée et son message d’erreur complet : [coller les informations]. Diagnostique la cause à partir de ces éléments et des fichiers du projet. Distingue une erreur de code d’un problème d’environnement ou de réseau. Propose d’abord la vérification la moins risquée, puis donne les commandes PowerShell exactes pour corriger ou relancer le projet. Ne supprime pas les volumes de données sans avertissement explicite.

### 10. Tester et documenter

**Utilité :** vérifier les changements et rendre le projet facile à lancer par une autre personne.

> Après les changements apportés à Campus, lance les vérifications adaptées : syntaxe PHP, requêtes/pages concernées, filtres, droits d’accès et absence d’erreurs serveur. Résume les résultats en distinguant les tests réussis des points non vérifiés. Mets à jour le README avec les prérequis, les commandes Docker pour démarrer et arrêter l’application, l’adresse locale, les migrations à appliquer et les précautions pour préserver la base.

## Note sur l’historique

Les demandes conservées dans la conversation disponible portaient notamment sur le lancement du projet, la résolution d’une erreur Docker, les idées d’amélioration, leur implémentation et l’explication des changements. Les prompts initiaux de création du site, s’ils ont été formulés dans une conversation antérieure, ne sont pas accessibles ici. Pour une archive historique exacte, il faut les récupérer depuis cette conversation d’origine.
