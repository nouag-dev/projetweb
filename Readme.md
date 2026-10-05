# Campus

Marketplace étudiante construite en PHP 8.2, Apache et PostgreSQL 16.

## Lancer le projet

Depuis PowerShell, à la racine du dépôt :

```powershell
docker compose up --build -d
```

Le site est accessible sur http://localhost:8081. Pour arrêter les services sans supprimer les données : `docker compose down`.

## Mise à jour de la base

Après avoir récupéré une version qui ajoute une migration, appliquer les scripts SQL sur le conteneur PostgreSQL existant. Depuis PowerShell :

```powershell
Get-Content database/migrations/20261005_marketplace_features.sql | docker compose exec -T db psql -v ON_ERROR_STOP=1 -U campus -d campus_app
```

Cette migration ajoute le statut des annonces, les avis et les signalements sans effacer les données. Les nouveaux environnements reçoivent également le schéma à jour lors de leur initialisation.