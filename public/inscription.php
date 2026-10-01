<?php
require_once __DIR__ . '/../src/config/auth.php';

if (currentUser() !== null) {
    header('Location: /profil.php');
    exit;
}

$ecoles = getConnexion()->query('SELECT id_ecole, nom, domaine_email FROM ecoles ORDER BY nom')->fetchAll();

$errors = [];
$values = [
    'prenom' => '', 'nom' => '', 'email' => '', 'date_naissance' => '',
    'ville' => '', 'adresse' => '', 'telephone' => '', 'sexe' => '', 'id_ecole' => '', 'pays' => 'France',
];

$ageMinDate = (new DateTime('-17 years'))->format('Y-m-d');
$ageMaxDate = (new DateTime('-25 years'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $values['email'] = strtolower($values['email']);
    $password = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Recommencez.';
    }
    if ($values['prenom'] === '' || $values['nom'] === '') {
        $errors[] = 'Le prénom et le nom sont obligatoires.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Saisissez une adresse email valide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    $birthDate = DateTime::createFromFormat('Y-m-d', $values['date_naissance']);
    if ($birthDate === false) {
        $errors[] = 'Indique une date de naissance valide.';
    } else {
        $age = (new DateTime())->diff($birthDate)->y;
        if ($age < 17 || $age > 25) {
            $errors[] = 'L\'inscription est réservée aux 17-25 ans.';
        }
    }

    if ($values['ville'] === '') {
        $errors[] = 'La ville est obligatoire.';
    }
    if ($values['adresse'] === '') {
        $errors[] = 'L\'adresse est obligatoire.';
    }
    if ($values['pays'] === '') {
        $errors[] = 'Le pays est obligatoire.';
    }
    if (!preg_match('/^[0-9+\s.-]{6,20}$/', $values['telephone'])) {
        $errors[] = 'Saisis un numéro de téléphone valide.';
    }
    if (!in_array($values['sexe'], ['Femme', 'Homme', 'Autre'], true)) {
        $errors[] = 'Sélectionne une option pour le sexe.';
    }

    $ecoleChoisie = null;
    foreach ($ecoles as $ecole) {
        if ((string) $ecole['id_ecole'] === $values['id_ecole']) {
            $ecoleChoisie = $ecole;
            break;
        }
    }
    if ($ecoleChoisie === null) {
        $errors[] = 'Sélectionne ton établissement dans la liste.';
    }

    if ($errors === []) {
        try {
            $pdo = getConnexion();
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs
                    (nom, prenom, email, mot_de_passe, email_verifie, id_ecole,
                     date_naissance, ville, adresse, telephone, sexe, pays)
                 VALUES
                    (:nom, :prenom, :email, :mot_de_passe, TRUE, :id_ecole,
                     :date_naissance, :ville, :adresse, :telephone, :sexe, :pays)
                 RETURNING id_utilisateur, nom, prenom, email, photo_url, theme,
                           telephone, adresse, ville, pays'
            );
            $stmt->execute([
                'nom' => $values['nom'],
                'prenom' => $values['prenom'],
                'email' => $values['email'],
                'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT),
                'id_ecole' => $ecoleChoisie['id_ecole'],
                'date_naissance' => $values['date_naissance'],
                'ville' => $values['ville'],
                'adresse' => $values['adresse'],
                'telephone' => $values['telephone'],
                'sexe' => $values['sexe'],
                'pays' => $values['pays'],
            ]);
            $_SESSION['user'] = $stmt->fetch();
            header('Location: /profil.php?created=1');
            exit;
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23505'
                ? 'Cette adresse email est déjà utilisée.'
                : 'Une erreur technique est survenue pendant la création du compte. Merci de réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="account-page">
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><a class="nav-link" href="/connexion.php">Déjà inscrit ? Se connecter</a></header>
    <main class="account-layout">
        <section class="account-intro"><p class="eyebrow">Rejoins la communauté</p><h1>Ton campus, tes échanges, ton espace.</h1><p>Réservé aux étudiants de 17 à 25 ans, avec un email de ton établissement.</p></section>
        <section class="form-panel"><p class="eyebrow">Première étape</p><h2>Créer un compte</h2>
            <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="form-row">
                    <label>Prénom<input name="prenom" value="<?= e($values['prenom']) ?>" required></label>
                    <label>Nom<input name="nom" value="<?= e($values['nom']) ?>" required></label>
                </div>

                <label>Établissement
                    <select name="id_ecole" required>
                        <option value="">Choisir ton établissement</option>
                        <?php foreach ($ecoles as $ecole): ?>
                            <option value="<?= (int) $ecole['id_ecole'] ?>" <?= $values['id_ecole'] == $ecole['id_ecole'] ? 'selected' : '' ?>>
                                <?= e($ecole['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Ton email doit être une adresse de ton établissement, par exemple prenom.nom@ecole.fr.</small>
                </label>

                <label>Email étudiant<input type="email" name="email" value="<?= e($values['email']) ?>" placeholder="prenom.nom@ecole.fr" required></label>

                <div class="form-row">
                    <label>Date de naissance
                        <input type="date" name="date_naissance" value="<?= e($values['date_naissance']) ?>" min="<?= e($ageMaxDate) ?>" max="<?= e($ageMinDate) ?>" required>
                        <small>Réservé aux 17-25 ans</small>
                    </label>
                    <label>Sexe
                        <select name="sexe" required>
                            <option value="">Choisir</option>
                            <option value="Femme" <?= $values['sexe'] === 'Femme' ? 'selected' : '' ?>>Femme</option>
                            <option value="Homme" <?= $values['sexe'] === 'Homme' ? 'selected' : '' ?>>Homme</option>
                            <option value="Autre" <?= $values['sexe'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </label>
                </div>

                <div class="form-row">
                    <label>Ville<input name="ville" value="<?= e($values['ville']) ?>" placeholder="Limoges" required></label>
                    <label>Téléphone<input type="tel" name="telephone" value="<?= e($values['telephone']) ?>" placeholder="06 12 34 56 78" required></label>
                </div>

                <div class="form-row">
                    <label>Adresse<input name="adresse" value="<?= e($values['adresse']) ?>" placeholder="12 rue des Étudiants" required></label>
                    <label>Pays<input name="pays" value="<?= e($values['pays']) ?>" required></label>
                </div>

                <label>Mot de passe <small>8 caractères minimum</small><input type="password" name="mot_de_passe" required></label>
                <label>Confirmation<input type="password" name="confirmation" required></label>
                <button class="primary-button" type="submit">Créer mon compte</button>
            </form>
        </section>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
