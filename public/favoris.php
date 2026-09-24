<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();

$stmt = $pdo->prepare(
    <<<'SQL'
    SELECT
        a.id_annonce, a.titre, a.description, a.prix, a.etat,
        c.nom AS categorie, u.prenom, u.nom,
        (SELECT p.url FROM photos p WHERE p.id_annonce = a.id_annonce ORDER BY p.ordre LIMIT 1) AS photo_url
    FROM likes l
    JOIN annonces a ON a.id_annonce = l.id_annonce
    JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
    LEFT JOIN categories c ON c.id_categorie = a.id_categorie
    WHERE l.id_utilisateur = :user_id AND l.statut = 'like'
    ORDER BY l.date_like DESC
    SQL
);
$stmt->execute(['user_id' => $user['id_utilisateur']]);
$favoris = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes favoris | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="content-section listings-section" style="padding-top: 50px; padding-bottom: 90px;">
        <div class="section-heading">
            <div><p class="eyebrow">Ce qui t'a plu</p><h2>Mes favoris</h2></div>
            <span class="section-count"><?= count($favoris) ?> annonce<?= count($favoris) > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($favoris === []): ?>
            <div class="empty-state">
                <span class="empty-icon">○</span>
                <h3>Aucun favori pour le moment</h3>
                <p>Les annonces que tu likes sur Explorer apparaîtront ici.</p>
                <a class="outline-button" href="/decouvrir.php">Découvrir des annonces</a>
            </div>
        <?php else: ?>
            <div class="listing-grid">
                <?php foreach ($favoris as $annonce): ?>
                    <a class="listing-card" href="/annonce.php?id=<?= (int) $annonce['id_annonce'] ?>">
                        <div class="listing-image">
                            <?php if ($annonce['photo_url'] !== null): ?>
                                <img src="<?= e($annonce['photo_url']) ?>" alt="Photo de <?= e($annonce['titre']) ?>">
                            <?php else: ?>
                                <span><?= e(strtoupper(substr($annonce['categorie'] ?? 'ANN', 0, 3))) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="listing-body">
                            <div class="listing-meta">
                                <span><?= e($annonce['categorie'] ?? 'Autre') ?></span>
                                <span><?= e($annonce['etat'] ?? 'Disponible') ?></span>
                            </div>
                            <h3><?= e($annonce['titre']) ?></h3>
                            <p><?= e($annonce['description']) ?></p>
                            <div class="listing-footer">
                                <strong><?= number_format((float) $annonce['prix'], 2, ',', ' ') ?> €</strong>
                                <span><?= e($annonce['prenom']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
