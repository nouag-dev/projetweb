-- Schéma PostgreSQL — Campus (appli étudiante)
-- Correspond au MLD défini pour le projet

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    id_categorie INT REFERENCES categories(id_categorie)
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

-- Quelques catégories de départ pour tester
INSERT INTO categories (nom, icone) VALUES
    ('Électronique', 'laptop'),
    ('Livres & cours', 'book'),
    ('Mobilier', 'chair'),
    ('Vêtements', 'shirt'),
    ('Services', 'hand');
