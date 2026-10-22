# Campus

Campus est une marketplace destinée aux étudiants. Elle permet de publier et de parcourir des annonces d'objets et de services, de prendre contact avec un vendeur et de suivre des offres.

Ce dépôt contient une application web développée en PHP, ainsi que sa base PostgreSQL et sa configuration Docker Compose. Le projet est conçu pour être lancé localement à des fins de démonstration et de développement.

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Technologies](#technologies)
- [Prérequis](#prérequis)
- [Installation et démarrage](#installation-et-démarrage)
- [Utilisateurs et données de démonstration](#utilisateurs-et-données-de-démonstration)
- [Configuration](#configuration)
- [Base de données et migrations](#base-de-données-et-migrations)
- [Structure du dépôt](#structure-du-dépôt)
- [Sécurité et limites connues](#sécurité-et-limites-connues)
- [Tests et vérifications](#tests-et-vérifications)
- [Dépannage](#dépannage)
- [Crédits des photos](#crédits-des-photos)
- [Présentation de soutenance](#présentation-de-soutenance)

## Fonctionnalités

### Annonces et découverte

- Consulter les annonces disponibles sur la page d'accueil.
- Les annonces d'un utilisateur connecté ne sont pas affichées dans son propre catalogue public, ses catégories ou ses favoris.
- Rechercher du texte et filtrer les annonces par catégorie, prix et état.
- Trier les annonces, notamment par date ou par prix.
- Parcourir les annonces dans une interface de découverte de type « swipe ».
- Ajouter une annonce aux favoris ou l'ignorer dans la découverte ; les choix sont enregistrés par compte et les annonces ignorées ne sont plus proposées lors d'une visite suivante.
- Déposer et modifier ses annonces, ajouter des photos, puis marquer une annonce comme vendue ou la retirer.
- Afficher des photos d'exemple associées au titre ou à la catégorie lorsqu'une annonce n'a pas de photo fournie. Les photos ajoutées par le vendeur restent prioritaires.

### Comptes et échanges

- Créer un compte, se connecter, modifier son profil et choisir un thème clair, sombre ou adapté à l'appareil.
- Envoyer des messages au vendeur depuis une annonce.
- Envoyer une proposition de prix, puis la suivre et consulter la réponse du vendeur.
- Consulter les notifications liées aux échanges.
- Laisser un avis après un échange éligible ou signaler une annonce.
- Consulter dans son profil un tableau de bord personnel : annonces en ligne et vendues, offres reçues ou envoyées en attente, favoris, messages non lus et note moyenne lorsqu'il existe des avis.

### Établissement et proximité

- Sélectionner un établissement connu ou choisir « Autre » et saisir son nom.
- Pour un établissement connu, l'adresse doit correspondre à un domaine enregistré pour cet établissement. Certains établissements peuvent accepter plusieurs domaines.
- Les annonces de vendeurs de la même ville que celle indiquée dans le profil sont affichées en priorité.
- Le tri de proximité utilise la ville du profil : il ne repose pas sur le GPS ni sur l'adresse complète.

> **À propos du statut étudiant :** l'inscription ne nécessite pas de code envoyé par e-mail. Pour un établissement connu, Campus contrôle le domaine de l'adresse déclarée, mais ne vérifie pas l'identité auprès du portail de l'école. Pour « Autre », le nom de l'établissement est déclaré par la personne. Campus ne demande jamais le mot de passe du compte scolaire.

## Technologies

- **PHP 8.2** avec Apache
- **PostgreSQL 16**
- **Docker Compose** pour lancer les services web et base de données
- **HTML, CSS et JavaScript** pour l'interface
- **PDO PostgreSQL** pour l'accès aux données

## Prérequis

- Windows, macOS ou Linux
- Docker Desktop récent (Windows/macOS) ou Docker Engine et le plugin Docker Compose (Linux)
- Docker Compose v2, disponible avec `docker compose`
- Git, si le dépôt doit être cloné
- Un port local disponible :
  - `8081` pour le site web
  - `5432` pour PostgreSQL

Sous Windows, les commandes de ce guide sont prévues pour PowerShell. Sur un autre système, adapter les commandes de lecture des fichiers de migration.

## Installation et démarrage

1. Cloner le dépôt puis se placer à sa racine :

   ```powershell
   git clone <URL_DU_DEPOT>
   Set-Location projetweb
   ```

   Si le dépôt est déjà ouvert dans VS Code, ouvrir un terminal PowerShell à la racine du projet.

2. Construire et démarrer les services :

   ```powershell
   docker compose up --build -d
   ```

   La première construction peut prendre quelques minutes : Docker télécharge les images de base, installe l'extension PHP PostgreSQL et initialise la base.

3. Vérifier que les services sont démarrés :

   ```powershell
   docker compose ps
   ```

4. Ouvrir le site :

   - Application : [http://localhost:8081](http://localhost:8081)
   - Page de découverte : [http://localhost:8081/decouvrir.php](http://localhost:8081/decouvrir.php)
   - Connexion : [http://localhost:8081/connexion.php](http://localhost:8081/connexion.php)

Les fichiers PHP/CSS/JavaScript sont montés depuis le dépôt dans le conteneur web : les changements de code sont généralement visibles sans reconstruire l'image. Après une modification du `Dockerfile` ou de la configuration Compose, reconstruire et redémarrer avec :

```powershell
docker compose up --build -d
```

### Arrêter et redémarrer

Arrêter les conteneurs tout en conservant les données de la base :

```powershell
docker compose down
```

Redémarrer ensuite :

```powershell
docker compose up -d
```

Les données PostgreSQL sont conservées dans le volume Docker `postgres_data`. **Ne pas ajouter `-v` à `docker compose down` si les données doivent être conservées** : cette option supprime également les volumes.

## Utilisateurs et données de démonstration

Lors de la première initialisation de la base, `database/init.sql` crée des comptes et des annonces d'exemple. Les comptes de démonstration partagent le même mot de passe :

```text
Mot de passe : password123
```

Exemples de comptes :

```text
lucas.martin@etu-campus.fr
emma.dupont@etu-campus.fr
noah.bernard@etu-campus.fr
```

Ces identifiants sont uniquement des données locales de démonstration. Ne pas les réutiliser pour un déploiement public ou pour des comptes réels.

## Configuration

Le service web lit ses paramètres PostgreSQL depuis les variables d'environnement définies dans `docker-compose.yml` :

| Variable | Valeur locale par défaut | Rôle |
|---|---|---|
| `DB_HOST` | `db` | Nom du service PostgreSQL dans le réseau Docker |
| `DB_NAME` | `campus_app` | Nom de la base |
| `DB_USER` | `campus` | Utilisateur PostgreSQL |
| `DB_PASSWORD` | `campus_password` | Mot de passe PostgreSQL local |

Le conteneur PostgreSQL expose le port `5432` sur la machine et le site web le port `8081`. Si l'un de ces ports est déjà utilisé, modifier le côté gauche de la déclaration de port dans `docker-compose.yml` — par exemple `8082:80` pour le site — puis redémarrer Compose.

La configuration PHP limite les photos téléversées à **5 Mo par image**, accepte les formats JPEG, PNG et WebP, et configure la limite totale de formulaire à 30 Mo.

## Base de données et migrations

### Initialisation d'une nouvelle base

Sur un volume PostgreSQL neuf, Docker exécute automatiquement [`database/init.sql`](./database/init.sql). Le script crée les écoles, leurs domaines, les catégories, les comptes et les annonces de démonstration.

Le script d'initialisation Docker ne s'exécute automatiquement qu'à la **première création** du volume. Redémarrer les conteneurs ne réinitialise pas une base existante.

### Mise à jour d'une base existante

Les scripts de [`database/migrations/`](./database/migrations/) sont à appliquer une seule fois sur les bases qui n'ont pas encore reçu les changements concernés. Vérifier l'historique de la base avant de les lancer ; le dépôt ne contient pas de registre automatique des migrations appliquées.

À la date de rédaction, les migrations sont à appliquer dans cet ordre :

1. `20261005_marketplace_features.sql` : statut des annonces, avis et signalements.
2. `20261022_email_verification_other_school.sql` : établissement « Autre », domaines e-mail supplémentaires et index de ville.
3. `20261022_remove_email_verification.sql` : marque comme vérifiés les comptes précédemment en attente et supprime l'ancienne table des codes.

Depuis PowerShell, avec les conteneurs démarrés, exécuter uniquement les migrations qui manquent à la base :

```powershell
Get-Content -Raw -Encoding utf8 database/migrations/20261005_marketplace_features.sql |
    docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app

Get-Content -Raw -Encoding utf8 database/migrations/20261022_email_verification_other_school.sql |
    docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app

Get-Content -Raw -Encoding utf8 database/migrations/20261022_remove_email_verification.sql |
    docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app
```

Ne pas recopier les trois commandes aveuglément sur une base déjà à jour. Si une commande échoue, arrêter la séquence, lire le message PostgreSQL et vérifier l'état du schéma avant de poursuivre.

### Accéder à PostgreSQL

Ouvrir une console `psql` dans le conteneur :

```powershell
docker compose exec db psql -U campus -d campus_app
```

Exemples de requêtes de lecture :

```sql
SELECT id_annonce, titre, prix, statut_vente FROM annonces ORDER BY id_annonce;
SELECT id_utilisateur, email, ville FROM utilisateurs ORDER BY id_utilisateur;
\q
```

### Sauvegarder la base

Créer une sauvegarde SQL dans le répertoire courant :

```powershell
docker compose exec -T db pg_dump -U campus -d campus_app > campus_app_backup.sql
```

Conserver cette sauvegarde dans un emplacement sûr et ne pas la publier si elle contient des données personnelles.

## Structure du dépôt

```text
.
├── database/
│   ├── init.sql                 # Schéma et données de démonstration
│   └── migrations/              # Évolutions de schéma
├── docker/
│   └── php/Dockerfile           # Image PHP/Apache avec PDO PostgreSQL
├── presentation/
│   ├── Campus_Soutenance.pptx   # Diaporama de soutenance
│   └── generate_presentation.py # Script de génération du diaporama
├── public/
│   ├── api/                     # Points d'accès AJAX/JSON
│   ├── assets/                  # Feuille de style et photos de remplacement
│   └── *.php                    # Pages web
├── src/
│   ├── config/                  # Connexion à la base et fonctions communes
│   └── includes/                # Navigation, pied de page et notifications
├── docker-compose.yml
└── Readme.md
```

### Pages principales

| Page | Rôle |
|---|---|
| `public/index.php` | Accueil, recherche et liste des annonces |
| `public/categorie.php` | Liste d'une catégorie |
| `public/annonce.php` | Détail, offres, avis et signalement |
| `public/decouvrir.php` | Découverte interactive des annonces |
| `public/creer-annonce.php` | Création d'une annonce et ajout de photos |
| `public/modifier-annonce.php` | Modification ou retrait d'une annonce |
| `public/favoris.php` | Annonces aimées |
| `public/chat.php` | Messagerie entre utilisateurs |
| `public/panier.php` | Suivi des offres envoyées |
| `public/profil.php` | Tableau de bord personnel, profil, annonces, offres reçues et conversations |
| `public/parametres.php` | Profil, photo et thème |
| `public/inscription.php` et `public/connexion.php` | Création de compte et connexion |
| `public/api/like.php` | Enregistrement des choix de la page de découverte |

Les photos téléversées par les utilisateurs sont stockées sous `public/uploads/` et ne sont pas suivies par Git, à l'exception des fichiers `.gitkeep`.

## Sécurité et limites connues

- Les mots de passe des comptes créés sont hachés côté serveur.
- Les formulaires et actions sensibles vérifient un jeton CSRF.
- Les pages utilisent des requêtes SQL préparées pour les données fournies par l'utilisateur.
- Les téléversements d'images sont limités en taille et leurs types sont contrôlés.
- La validation d'un domaine e-mail ne prouve pas que la personne est inscrite dans l'établissement.
- Campus ne traite ni les paiements ni la livraison.
- La proximité est fondée sur la ville indiquée dans le profil ; ce n'est pas une géolocalisation GPS.
- Les valeurs d'accès présentes dans `docker-compose.yml` sont des valeurs locales de développement. Elles ne doivent pas être utilisées en production.

Cette configuration n'est pas un guide de déploiement de production. Avant toute exposition publique, prévoir une configuration de secrets adaptée, HTTPS, sauvegardes, journalisation, contrôles supplémentaires contre les abus, modération et tests approfondis.

## Tests et vérifications

Le dépôt ne contient pas actuellement de suite de tests automatisés dédiée. Pour vérifier localement l'environnement :

```powershell
docker compose config --quiet
docker compose ps
docker compose logs --tail 100 web
docker compose logs --tail 100 db
```

Parcours fonctionnels à vérifier après une modification :

1. Créer un compte avec un domaine accepté et vérifier l'arrivée sur le profil.
2. Vérifier qu'un domaine non accepté pour un établissement connu est refusé.
3. Créer une annonce avec une photo puis une annonce sans photo.
4. Parcourir la découverte, ajouter un favori et envoyer une offre.
5. Tester la messagerie avec deux comptes distincts.
6. Marquer une annonce comme vendue et vérifier les statuts de l'offre.
7. Vérifier l'affichage mobile et les thèmes clair/sombre.

## Dépannage

### Le site ne s'ouvre pas

Vérifier que les conteneurs tournent et que le port `8081` n'est pas occupé :

```powershell
docker compose ps
docker compose logs --tail 100 web
```

### Le site ne se connecte pas à PostgreSQL

Vérifier que le service `db` est démarré et consulter ses journaux :

```powershell
docker compose ps
docker compose logs --tail 100 db
```

La connexion applicative utilise le nom `db` à l'intérieur du réseau Compose ; ne pas remplacer `DB_HOST` par `localhost` depuis le conteneur web.

### Les changements SQL n'apparaissent pas

Le script `database/init.sql` ne s'exécute qu'à la création du volume. Pour une base existante, appliquer la migration correspondante une seule fois, comme décrit dans la section [Base de données et migrations](#base-de-données-et-migrations).

### Les photos ne sont pas visibles

Vérifier le type et la taille de l'image (JPEG, PNG ou WebP, au plus 5 Mo) et confirmer que le dossier `public/uploads/` est accessible en écriture par le conteneur web.

### Réinitialiser les données de démonstration

La suppression du volume de base efface **toutes les données locales**, y compris les comptes, annonces et messages créés. N'effectuer cette opération que si cette perte est souhaitée :

```powershell
docker compose down -v
docker compose up --build -d
```

Après cette réinitialisation, la base est recréée depuis `database/init.sql`.

## Crédits des photos

Les photos de remplacement sont stockées dans [`public/assets/illustrations/`](./public/assets/illustrations/). Elles ont été téléchargées depuis Unsplash et sont utilisées conformément à la licence Unsplash. Les sources image par image sont répertoriées dans [`credits.txt`](./public/assets/illustrations/credits.txt).

## Présentation de soutenance

Le diaporama de présentation est disponible ici :

- [Campus_Soutenance.pptx](./presentation/Campus_Soutenance.pptx)
- [Script de génération du diaporama](./presentation/generate_presentation.py)

La présentation décrit les fonctionnalités, l'architecture, les vérifications réalisées et les limites connues du projet. Ajouter les noms des membres de l'équipe sur la couverture avant de la remettre si nécessaire.
