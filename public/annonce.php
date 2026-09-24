<?php
require_once __DIR__ . '/../src/config/auth.php';

$announcementId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$announcement = null;
$photos = [];
$error = null;

if ($announcementId) {
    try {
        $pdo = getConnexion();
        $stmt = $pdo->prepare(
            'SELECT a.id_annonce, a.titre, a.description, a.prix, a.etat, a.date_publication,
                    c.nom AS categorie, u.id_utilisateur AS vendeur_id, u.prenom, u.nom
             FROM annonces a
             JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
             LEFT JOIN categories c ON c.id_categorie = a.id_categorie
             WHERE a.id_annonce = :id'
        );
        $stmt->execute(['id' => $announcementId]);
        $announcement = $stmt->fetch();
        if ($announcement !== false) {
            $photoStmt = $pdo->prepare('SELECT url FROM photos WHERE id_annonce = :id ORDER BY ordre, id_photo');
            $photoStmt->execute(['id' => $announcementId]);
            $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $exception) {
        $error = 'Impossible de charger cette annonce.';
    }
}

if ($announcement === false || $announcement === null) {
    http_response_code(404);
    $announcement = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $announcement ? e($announcement['titre']) . ' | Campus' : 'Annonce introuvable | Campus' ?></title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/">Accueil</a><?php if (currentUser()): ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?><?php else: ?><a href="/connexion.php">Se connecter</a><?php endif; ?></nav></header>
    <main class="detail-page">
        <?php if ($announcement === null): ?>
            <div class="empty-state"><h3>Annonce introuvable</h3><p><?= e($error ?? 'Cette annonce n’existe plus ou a été supprimée.') ?></p><a class="outline-button" href="/">Retour aux annonces</a></div>
        <?php else: ?>
            <a class="back-link" href="/">← Retour aux annonces</a>
            <div class="detail-layout">
                <section class="detail-gallery">
                    <?php if ($photos === []): ?><div class="detail-placeholder"><?= e(strtoupper(substr($announcement['categorie'] ?? 'ANN', 0, 3))) ?></div><?php else: ?><div class="detail-main-photo"><img src="<?= e($photos[0]) ?>" alt="Photo de <?= e($announcement['titre']) ?>"></div><?php if (count($photos) > 1): ?><div class="detail-thumbnails"><?php foreach ($photos as $photo): ?><img src="<?= e($photo) ?>" alt="Photo de <?= e($announcement['titre']) ?>"><?php endforeach; ?></div><?php endif; ?><?php endif; ?>
                </section>
                <section class="detail-info"><div class="listing-meta"><span><?= e($announcement['categorie'] ?? 'Autre') ?></span><span><?= e($announcement['etat'] ?? 'Disponible') ?></span></div><h1><?= e($announcement['titre']) ?></h1><p class="detail-price"><?= number_format((float) $announcement['prix'], 2, ',', ' ') ?> €</p><p class="detail-description"><?= nl2br(e($announcement['description'])) ?></p><div class="seller-box"><div class="avatar small-avatar"><?= e(strtoupper(substr($announcement['prenom'], 0, 1) . substr($announcement['nom'], 0, 1))) ?></div><div><small>Publié par</small><strong><?= e($announcement['prenom'] . ' ' . $announcement['nom']) ?></strong></div></div><?php if (currentUser() !== null && (int) currentUser()['id_utilisateur'] === (int) $announcement['vendeur_id']): ?><div class="notice-box">C’est votre annonce.</div><?php else: ?><a class="primary-button contact-button" href="<?= currentUser() ? '/chat.php?annonce=' . (int) $announcement['id_annonce'] : '/connexion.php?redirect=' . rawurlencode('/chat.php?annonce=' . (int) $announcement['id_annonce']) ?>">Contacter le vendeur</a><?php endif; ?></section>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
