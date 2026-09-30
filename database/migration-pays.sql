-- database/migration-pays.sql
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS pays VARCHAR(100) NOT NULL DEFAULT 'France';
