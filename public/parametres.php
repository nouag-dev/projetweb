<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="form-page">
        <section class="form-panel listing-form-panel">
            <p class="eyebrow">Ton compte</p>
            <h1>Paramètres</h1>
            <p class="form-lead">Prénom : <?= e($user['prenom']) ?> · Email : <?= e($user['email']) ?></p>
            <div class="empty-state" style="margin-top: 24px;">
                <h3>Bientôt disponible</h3>
                <p>La modification du profil, du mot de passe et des notifications arrivera ici.</p>
            </div>
        </section>
    </main>
</body>
</html>
