ALTER TABLE utilisateurs
    ADD COLUMN IF NOT EXISTS ecole_autre VARCHAR(150);

CREATE INDEX IF NOT EXISTS idx_utilisateurs_ville_normalisee
    ON utilisateurs (lower(trim(ville)));

CREATE TABLE IF NOT EXISTS ecole_domaines (
    id_ecole INT NOT NULL REFERENCES ecoles(id_ecole) ON DELETE CASCADE,
    domaine_email VARCHAR(150) NOT NULL,
    PRIMARY KEY (id_ecole, domaine_email)
);

INSERT INTO ecole_domaines (id_ecole, domaine_email)
SELECT id_ecole, domaine_email FROM ecoles
ON CONFLICT (id_ecole, domaine_email) DO NOTHING;

INSERT INTO ecole_domaines (id_ecole, domaine_email)
SELECT id_ecole, '3il.fr' FROM ecoles WHERE domaine_email = 'etu-3il.fr'
ON CONFLICT (id_ecole, domaine_email) DO NOTHING;
