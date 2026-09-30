-- database/migration-profil.sql
-- Ajoute : photo de profil, thème, date de naissance, ville, adresse, téléphone, sexe
-- + peuple la table ecoles (vide jusqu'ici) pour le menu déroulant d'inscription.

ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255);
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS theme VARCHAR(10) NOT NULL DEFAULT 'clair';
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS date_naissance DATE;
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS ville VARCHAR(100);
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS adresse VARCHAR(255);
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS telephone VARCHAR(20);
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS sexe VARCHAR(10);

INSERT INTO ecoles (nom, domaine_email) VALUES
    ('3iL Ingénieurs', 'etu-3il.fr'),
    ('Université de Limoges', 'etu.unilim.fr'),
    ('ENSIL-ENSCI', 'etu-ensil.fr'),
    ('Sciences Po', 'sciencespo.fr'),
    ('HEC Paris', 'hec.edu')
ON CONFLICT (domaine_email) DO NOTHING;
