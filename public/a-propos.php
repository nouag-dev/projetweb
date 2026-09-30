<?php
require_once __DIR__ . '/../src/config/auth.php';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav">
            <?php if ($user !== null): ?>
                <a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
            <?php else: ?>
                <a href="/">Accueil</a><a href="/connexion.php">Se connecter</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="form-page">
        <div class="listing-form-panel" style="display: flex; flex-direction: column; gap: 18px;">
            <h1 style="font-size: 34px; font-weight: normal; margin: 0;">Qui sommes-nous ?</h1>
            <p style="color: var(--muted); font: 15px Arial, sans-serif; line-height: 1.7;">
                Campus est un projet étudiant : une marketplace pensée pour les étudiants, par des étudiants.
                L'idée est simple — faciliter la circulation des objets, des livres et des petits services entre
                camarades de campus, avec une découverte rapide façon swipe et une messagerie intégrée pour
                négocier directement avec le vendeur.
            </p>
            <p style="color: var(--muted); font: 15px Arial, sans-serif; line-height: 1.7;">
                Ce site a été développé dans le cadre d'un projet de groupe en cursus ingénieur informatique.
                Il n'a pas vocation commerciale : c'est un exercice pédagogique de conception et de
                développement web (conception de base de données, back-end PHP, front-end HTML/CSS/JS).
            </p>
        </div>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
