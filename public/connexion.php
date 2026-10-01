<?php
require_once __DIR__ . '/../src/config/auth.php';

if (currentUser() !== null) {
    header('Location: /profil.php');
    exit;
}

$errors = [];
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/';
if (!is_string($redirect) || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['mot_de_passe'] ?? '');

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Merci de recommencer.';
    }

    if ($email === '') {
        $errors[] = 'Renseigne ton adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Le format de l’email est invalide.';
    }

    if ($password === '') {
        $errors[] = 'Renseigne ton mot de passe.';
    }

    if ($errors === []) {
        try {
            $stmt = getConnexion()->prepare('SELECT id_utilisateur, nom, prenom, email, mot_de_passe, photo_url, theme, telephone, adresse, ville, pays FROM utilisateurs WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user === false || !password_verify($password, $user['mot_de_passe'])) {
                $errors[] = 'Les identifiants sont incorrects. Vérifie ton email et ton mot de passe.';
            } else {
                unset($user['mot_de_passe']);
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                header('Location: ' . $redirect);
                exit;
            }
        } catch (PDOException $exception) {
            $errors[] = 'Une erreur technique est survenue. Merci de réessayer dans quelques instants.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Connexion | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="account-page">
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><a class="nav-link" href="/inscription.php">Créer un compte</a></header>
    <main class="account-layout login-layout">
        <section class="account-intro"><p class="eyebrow">Bon retour</p><h1>Retrouve les annonces qui t'attendent.</h1><p>Connecte-toi pour publier, gérer ton profil et suivre tes échanges.</p></section>
        <section class="form-panel"><p class="eyebrow">Ton espace</p><h2>Se connecter</h2>
            <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
                <label>Mot de passe<input type="password" name="mot_de_passe" required></label>
                <button class="primary-button" type="submit">Se connecter</button>
            </form>
            <p class="form-footnote">Pas encore de compte ? <a href="/inscription.php">Inscris-toi ici</a></p>
        </section>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
