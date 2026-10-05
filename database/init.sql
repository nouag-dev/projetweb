-- database/init.sql
-- Schéma complet PostgreSQL — Campus (appli étudiante)
-- Ce fichier s'exécute automatiquement au tout premier démarrage du conteneur
-- PostgreSQL (dossier docker-entrypoint-initdb.d). Il regroupe le schéma final
-- ainsi que les catégories, écoles et données de test.

CREATE TABLE ecoles (
    id_ecole SERIAL PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    domaine_email VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE utilisateurs (
    id_utilisateur SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    email_verifie BOOLEAN DEFAULT FALSE,
    id_ecole INT REFERENCES ecoles(id_ecole),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    photo_url VARCHAR(255),
    theme VARCHAR(10) NOT NULL DEFAULT 'clair',
    date_naissance DATE,
    ville VARCHAR(100),
    adresse VARCHAR(255),
    telephone VARCHAR(20),
    sexe VARCHAR(10),
    pays VARCHAR(100) NOT NULL DEFAULT 'France'
);

CREATE TABLE categories (
    id_categorie SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    icone VARCHAR(100)
);

CREATE TABLE annonces (
    id_annonce SERIAL PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    etat VARCHAR(50),
    date_publication TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    id_categorie INT REFERENCES categories(id_categorie),
    statut_vente VARCHAR(20) NOT NULL DEFAULT 'disponible'
        CHECK (statut_vente IN ('disponible', 'vendue', 'retiree'))
);

CREATE TABLE photos (
    id_photo SERIAL PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    ordre INT DEFAULT 0,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE
);

CREATE TABLE likes (
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce),
    statut VARCHAR(20) NOT NULL,
    date_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_utilisateur, id_annonce)
);

CREATE TABLE conversations (
    id_conversation SERIAL PRIMARY KEY,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_annonce INT REFERENCES annonces(id_annonce)
);

CREATE TABLE participants (
    id_conversation INT NOT NULL REFERENCES conversations(id_conversation),
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    PRIMARY KEY (id_conversation, id_utilisateur)
);

CREATE TABLE messages (
    id_message SERIAL PRIMARY KEY,
    contenu TEXT NOT NULL,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    id_conversation INT NOT NULL REFERENCES conversations(id_conversation),
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur)
);

CREATE TABLE offres (
    id_offre SERIAL PRIMARY KEY,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE,
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    montant DECIMAL(10,2) NOT NULL,
    message TEXT,
    statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_reponse TIMESTAMP NULL,
    UNIQUE (id_utilisateur, id_annonce)
);

CREATE INDEX idx_offres_annonce_statut ON offres (id_annonce, statut);

CREATE TABLE avis (
    id_avis SERIAL PRIMARY KEY,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE,
    id_acheteur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    note SMALLINT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire VARCHAR(1000) NOT NULL DEFAULT '',
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_annonce, id_acheteur)
);

CREATE TABLE signalements (
    id_signalement SERIAL PRIMARY KEY,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE,
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    motif VARCHAR(1000) NOT NULL,
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_annonce, id_utilisateur)
);

-- Écoles partenaires (vérification du domaine email à l'inscription)
INSERT INTO ecoles (nom, domaine_email) VALUES
    ('3iL Ingénieurs', 'etu-3il.fr'),
    ('Université de Limoges', 'etu.unilim.fr'),
    ('ENSIL-ENSCI', 'etu-ensil.fr'),
    ('Sciences Po', 'sciencespo.fr'),
    ('HEC Paris', 'hec.edu');

-- Catégories
INSERT INTO categories (nom, icone) VALUES
    ('Électronique', 'laptop'),
    ('Livres & cours', 'book'),
    ('Mobilier', 'chair'),
    ('Vêtements', 'shirt'),
    ('Services', 'hand'),
    ('Sport & Loisirs', 'sport'),
    ('Informatique', 'computer'),
    ('Musique & instruments', 'music'),
    ('Jeux vidéo', 'games'),
    ('Beauté & bien-être', 'beauty'),
    ('Décoration', 'decor'),
    ('Vélos & mobilité', 'bike'),
    ('Papeterie & fournitures', 'paper');

-- Comptes de test (mot de passe pour tous : password123)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, email_verifie) VALUES
    ('Martin', 'Lucas', 'lucas.martin@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Dupont', 'Emma', 'emma.dupont@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Bernard', 'Noah', 'noah.bernard@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Petit', 'Léa', 'lea.petit@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Robert', 'Hugo', 'hugo.robert@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE);

-- Annonces de test réparties dans les catégories
INSERT INTO annonces (titre, description, prix, etat, id_utilisateur, id_categorie) VALUES
    ('Calculatrice graphique TI-83', 'Utilisée un semestre, housse et câble fournis.', 35.00, 'Très bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'lucas.martin@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Électronique')),

    ('Écran PC 24 pouces', 'Parfait pour le télétravail, sorties HDMI et VGA.', 60.00, 'Bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'emma.dupont@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Électronique')),

    ('Clavier mécanique RGB', 'Switches bleus, rétroéclairage réglable.', 45.00, 'Très bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'hugo.robert@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Électronique')),

    ('Manuel Algorithmique S3', 'Édition 2024, aucune annotation.', 12.00, 'Neuf',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'noah.bernard@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Livres & cours')),

    ('Bureau blanc IKEA', 'Démonté, à récupérer sur le campus.', 25.00, 'Bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'hugo.robert@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Mobilier')),

    ('Chaise de bureau ergonomique', 'Très confortable, quelques traces d''usage.', 40.00, 'Bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'lucas.martin@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Mobilier')),

    ('Pull oversize taille M', 'Porté deux fois, comme neuf.', 10.00, 'Très bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'emma.dupont@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Vêtements')),

    ('Veste en jean', 'Taille S, style vintage.', 18.00, 'Bon état',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'noah.bernard@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Vêtements')),

    ('Cours de maths particuliers', 'Étudiant en prépa, niveau lycée à L2.', 15.00, 'Service',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'lea.petit@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Services')),

    ('Aide déménagement studio', 'Disponible le week-end, véhicule non fourni.', 20.00, 'Service',
        (SELECT id_utilisateur FROM utilisateurs WHERE email = 'lea.petit@etu-campus.fr'),
        (SELECT id_categorie FROM categories WHERE nom = 'Services'));
