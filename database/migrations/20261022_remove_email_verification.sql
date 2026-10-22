UPDATE utilisateurs
SET email_verifie = TRUE
WHERE email_verifie = FALSE;

ALTER TABLE utilisateurs
    ALTER COLUMN email_verifie SET DEFAULT TRUE;

DROP TABLE IF EXISTS email_verifications;
