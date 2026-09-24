-- database/seed.sql
-- Données de test : 5 comptes étudiants + 10 annonces réparties dans les catégories.
-- Mot de passe pour tous les comptes de test : password123

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, email_verifie) VALUES
    ('Martin', 'Lucas', 'lucas.martin@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Dupont', 'Emma', 'emma.dupont@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Bernard', 'Noah', 'noah.bernard@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Petit', 'Léa', 'lea.petit@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE),
    ('Robert', 'Hugo', 'hugo.robert@etu-campus.fr', '$2b$12$Ny463NAMYweOUMgw8Zz6FOIbazb2hdnpURCu88ynUw1Cu6EAh2xOi', TRUE)
ON CONFLICT (email) DO NOTHING;

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
