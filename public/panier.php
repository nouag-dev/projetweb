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
    <title>Panier | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="content-section" style="padding-bottom: 90px;">
        <div class="section-heading">
            <div><p class="eyebrow">Tes objets en attente</p><h2>Panier</h2></div>
        </div>
        <div class="empty-state">
            <span class="empty-icon">○</span>
            <h3>Ton panier est vide</h3>
            <p>Sur Campus, chaque échange se négocie directement avec le vendeur via la messagerie.</p>
            <a class="outline-button" href="/decouvrir.php">Découvrir des annonces</a>
        </div>
    </main>
</body>
</html>
