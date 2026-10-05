<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();
$offersStmt = $pdo->prepare(
    'SELECT o.id_offre, o.montant, o.statut, o.date_creation, o.message,
            a.id_annonce, a.titre, a.prix, a.statut_vente,
            seller.prenom AS vendeur_prenom
     FROM offres o
     JOIN annonces a ON a.id_annonce = o.id_annonce
     JOIN utilisateurs seller ON seller.id_utilisateur = a.id_utilisateur
     WHERE o.id_utilisateur = :user_id
     ORDER BY o.date_creation DESC'
);
$offersStmt->execute(['user_id' => $user['id_utilisateur']]);
$offers = $offersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="content-section" style="padding-bottom: 90px;">
        <div class="section-heading">
            <div><p class="eyebrow">Suivre tes négociations</p><h2>Mes offres</h2></div>
        </div>
        <?php if ($offers === []): ?>
            <div class="empty-state">
                <span class="empty-icon">○</span>
                <h3>Tu n’as pas encore envoyé d’offre</h3>
                <p>Retrouve ici les propositions de prix envoyées aux vendeurs et leur réponse.</p>
                <a class="outline-button" href="/decouvrir.php">Découvrir des annonces</a>
            </div>
        <?php else: ?>
            <div class="offer-list">
                <?php foreach ($offers as $offer): ?>
                    <article class="offer-item full-offer-item">
                        <div class="offer-topline"><strong><a href="/annonce.php?id=<?= (int) $offer['id_annonce'] ?>"><?= e($offer['titre']) ?></a></strong><span><?= number_format((float) $offer['montant'], 2, ',', ' ') ?> €</span></div>
                        <p class="offer-annonce">Prix demandé : <?= number_format((float) $offer['prix'], 2, ',', ' ') ?> € · Vendeur : <?= e($offer['vendeur_prenom']) ?></p>
                        <?php if (!empty($offer['message'])): ?><p><?= e($offer['message']) ?></p><?php endif; ?>
                        <small>Envoyée le <?= e(date('d/m/Y', strtotime($offer['date_creation']))) ?></small>
                        <span class="offer-status offer-status-<?= e($offer['statut']) ?>"><?= $offer['statut'] === 'en_attente' ? 'En attente' : ($offer['statut'] === 'acceptee' ? 'Acceptée' : 'Refusée') ?></span>
                        <?php if ($offer['statut'] === 'acceptee' && $offer['statut_vente'] === 'vendue'): ?><p><a class="outline-button compact-inline" href="/annonce.php?id=<?= (int) $offer['id_annonce'] ?>">Évaluer l’échange</a></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
