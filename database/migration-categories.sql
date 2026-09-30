-- database/migration-categories.sql
-- Ajoute de nouvelles catégories sans dupliquer si le script est relancé.

INSERT INTO categories (nom, icone)
SELECT 'Sport & Loisirs', 'sport' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Sport & Loisirs');

INSERT INTO categories (nom, icone)
SELECT 'Informatique', 'computer' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Informatique');

INSERT INTO categories (nom, icone)
SELECT 'Musique & instruments', 'music' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Musique & instruments');

INSERT INTO categories (nom, icone)
SELECT 'Jeux vidéo', 'games' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Jeux vidéo');

INSERT INTO categories (nom, icone)
SELECT 'Beauté & bien-être', 'beauty' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Beauté & bien-être');

INSERT INTO categories (nom, icone)
SELECT 'Décoration', 'decor' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Décoration');

INSERT INTO categories (nom, icone)
SELECT 'Vélos & mobilité', 'bike' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Vélos & mobilité');

INSERT INTO categories (nom, icone)
SELECT 'Papeterie & fournitures', 'paper' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Papeterie & fournitures');
