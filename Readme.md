# Campus

Marketplace étudiante construite en PHP 8.2, Apache et PostgreSQL 16.

## Lancer le projet

Depuis PowerShell, à la racine du dépôt :

```powershell
docker compose up --build -d
```

Le site est accessible sur http://localhost:8081. Pour arrêter les services sans supprimer les données : `docker compose down`.

## Inscription étudiante

La création de compte ne nécessite pas de code envoyé par email. Pour les établissements connus, l’adresse doit utiliser l’un de leurs domaines enregistrés. Ne demandez jamais aux étudiants leur mot de passe institutionnel : Campus ne se connecte pas aux portails des établissements.

Lorsqu'un compte est connecté, l'accueil et la page « Découvrir » affichent d'abord les annonces des vendeurs de la même ville que celle enregistrée dans son profil. L'adresse complète et le GPS ne sont pas utilisés.

## Mise à jour de la base

Après avoir récupéré une version qui ajoute une migration, appliquer les scripts SQL sur le conteneur PostgreSQL existant. Depuis PowerShell :

```powershell
Get-Content database/migrations/20261005_marketplace_features.sql | docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app
```

Cette migration ajoute le statut des annonces, les avis et les signalements sans effacer les données. Les nouveaux environnements reçoivent également le schéma à jour lors de leur initialisation.

Pour activer l'établissement « Autre » et l'index de localisation sur une base existante, appliquer également la migration `database/migrations/20261022_email_verification_other_school.sql` :

```powershell
Get-Content database/migrations/20261022_email_verification_other_school.sql | docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app
```

Pour supprimer les anciennes demandes de code email et permettre l'accès aux comptes qui étaient en attente, appliquer aussi `database/migrations/20261022_remove_email_verification.sql` :

```powershell
Get-Content database/migrations/20261022_remove_email_verification.sql | docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app
```

Les domaines étudiants autorisés pour chaque école sont stockés dans `ecole_domaines`, qui accepte plusieurs domaines pour un même établissement.