<?php
require_once __DIR__ . '/../src/config/auth.php';

if (currentUser() !== null) {
    header('Location: /profil.php');
    exit;
}

$error = null;
$email = strtolower(trim($_POST['email'] ?? ''));
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/';
if (!is_string($redirect) || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['mot_de_passe'] ?? '';
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'La session du formulaire a expiré. Recommencez.';
    } else {
        $stmt = getConnexion()->prepare('SELECT id_utilisateur, nom, prenom, email, mot_de_passe FROM utilisateurs WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user === false || !password_verify($password, $user['mot_de_passe'])) {
            $error = 'Email ou mot de passe incorrect.';
        } else {
            unset($user['mot_de_passe']);
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Connexion | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="account-page">
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><a class="nav-link" href="/inscription.php">Créer un compte</a></header>
    <main class="account-layout login-layout">
        <section class="account-intro"><p class="eyebrow">Bon retour</p><h1>Retrouve les annonces qui t'attendent.</h1><p>Connecte-toi pour publier, gérer ton profil et suivre tes échanges.</p></section>
        <section class="form-panel"><p class="eyebrow">Ton espace</p><h2>Se connecter</h2>
            <?php if ($error !== null): ?><div class="form-errors"><p><?= e($error) ?></p></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
                <label>Mot de passe<input type="password" name="mot_de_passe" required></label>
                <button class="primary-button" type="submit">Se connecter</button>
            </form>
            <p class="form-footnote">Pas encore de compte ? <a href="/inscription.php">Inscris-toi ici</a></p>
        </section>
    </main>
</body>
</html>
