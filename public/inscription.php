<?php
require_once __DIR__ . '/../src/config/auth.php';

if (currentUser() !== null) {
    header('Location: /profil.php');
    exit;
}

$errors = [];
$values = ['prenom' => '', 'nom' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['prenom'] = trim($_POST['prenom'] ?? '');
    $values['nom'] = trim($_POST['nom'] ?? '');
    $values['email'] = strtolower(trim($_POST['email'] ?? ''));
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

    if ($errors === []) {
        try {
            $pdo = getConnexion();
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, email_verifie)
                 VALUES (:nom, :prenom, :email, :mot_de_passe, FALSE)
                 RETURNING id_utilisateur, nom, prenom, email'
            );
            $stmt->execute([
                'nom' => $values['nom'],
                'prenom' => $values['prenom'],
                'email' => $values['email'],
                'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $_SESSION['user'] = $stmt->fetch();
            header('Location: /profil.php?created=1');
            exit;
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23505'
                ? 'Cette adresse email est déjà utilisée.'
                : 'Impossible de créer le compte pour le moment.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="account-page">
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><a class="nav-link" href="/connexion.php">Déjà inscrit ? Se connecter</a></header>
    <main class="account-layout">
        <section class="account-intro"><p class="eyebrow">Rejoins la communauté</p><h1>Ton campus, tes échanges, ton espace.</h1><p>Crée un compte pour publier des annonces et retrouver facilement tes échanges.</p></section>
        <section class="form-panel"><p class="eyebrow">Première étape</p><h2>Créer un compte</h2>
            <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="form-row"><label>Prénom<input name="prenom" value="<?= e($values['prenom']) ?>" required></label><label>Nom<input name="nom" value="<?= e($values['nom']) ?>" required></label></div>
                <label>Email<input type="email" name="email" value="<?= e($values['email']) ?>" required></label>
                <label>Mot de passe <small>8 caractères minimum</small><input type="password" name="mot_de_passe" required></label>
                <label>Confirmation<input type="password" name="confirmation" required></label>
                <button class="primary-button" type="submit">Créer mon compte</button>
            </form>
        </section>
    </main>
</body>
</html>
