ALTER TABLE annonces
    ADD COLUMN IF NOT EXISTS statut_vente VARCHAR(20) NOT NULL DEFAULT 'disponible';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'annonces_statut_vente_check'
    ) THEN
        ALTER TABLE annonces
            ADD CONSTRAINT annonces_statut_vente_check
            CHECK (statut_vente IN ('disponible', 'vendue', 'retiree'));
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS avis (
    id_avis SERIAL PRIMARY KEY,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE,
    id_acheteur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    note SMALLINT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire VARCHAR(1000) NOT NULL DEFAULT '',
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_annonce, id_acheteur)
);

CREATE TABLE IF NOT EXISTS signalements (
    id_signalement SERIAL PRIMARY KEY,
    id_annonce INT NOT NULL REFERENCES annonces(id_annonce) ON DELETE CASCADE,
    id_utilisateur INT NOT NULL REFERENCES utilisateurs(id_utilisateur),
    motif VARCHAR(1000) NOT NULL,
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_annonce, id_utilisateur)
);
