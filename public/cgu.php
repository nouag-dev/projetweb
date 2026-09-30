<?php
require_once __DIR__ . '/../src/config/auth.php';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions & confidentialité | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav">
            <?php if ($user !== null): ?>
                <a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
            <?php else: ?>
                <a href="/">Accueil</a><a href="/connexion.php">Se connecter</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="form-page">
        <div class="listing-form-panel" style="display: flex; flex-direction: column; gap: 28px;">
            <div>
                <h1 style="font-size: 34px; font-weight: normal; margin: 0 0 14px;">Conditions d'utilisation</h1>
                <p style="color: var(--muted); font: 15px Arial, sans-serif; line-height: 1.7;">
                    L'inscription est réservée aux étudiants de 17 à 25 ans disposant d'une adresse email
                    rattachée à un établissement partenaire. Chaque utilisateur est responsable du contenu
                    de ses annonces et des échanges menés via la messagerie. Les transactions (paiement,
                    remise en main propre) se négocient directement entre étudiants ; Campus ne prend part
                    à aucune transaction financière.
                </p>
            </div>
            <div>
                <h2 id="confidentialite" style="font-size: 24px; font-weight: normal; margin: 0 0 14px;">Confidentialité des données</h2>
                <p style="color: var(--muted); font: 15px Arial, sans-serif; line-height: 1.7;">
                    Les informations fournies à l'inscription (nom, email, coordonnées) servent uniquement
                    au fonctionnement du site : identification, mise en relation entre étudiants, et
                    vérification de l'appartenance à un établissement partenaire. Ce projet est un exercice
                    pédagogique et n'a pas vocation à exploiter ces données à des fins commerciales.
                </p>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
