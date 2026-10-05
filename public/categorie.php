<?php
require_once __DIR__ . '/../src/config/auth.php';

$user = currentUser();
$categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$categorie = null;
$annonces = [];
$error = null;

if ($categoryId) {
    try {
        $pdo = getConnexion();

        $stmt = $pdo->prepare('SELECT id_categorie, nom FROM categories WHERE id_categorie = :id');
        $stmt->execute(['id' => $categoryId]);
        $categorie = $stmt->fetch();

        if ($categorie !== false) {
            $stmt = $pdo->prepare(
                <<<'SQL'
                SELECT
                    a.id_annonce, a.titre, a.description, a.prix, a.etat,
                    u.prenom,
                    (SELECT p.url FROM photos p WHERE p.id_annonce = a.id_annonce ORDER BY p.ordre LIMIT 1) AS photo_url
                FROM annonces a
                JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
                WHERE a.id_categorie = :id
                  AND a.statut_vente = 'disponible'
                ORDER BY a.date_publication DESC
                SQL
            );
            $stmt->execute(['id' => $categoryId]);
            $annonces = $stmt->fetchAll();
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (!$categorie) {
    http_response_code(404);
    $categorie = null;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $categorie ? e($categorie['nom']) . ' | Campus' : 'Catégorie introuvable | Campus' ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav">
            <a href="/">Accueil</a>
            <?php if ($user !== null): ?>
                <?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
            <?php else: ?>
                <a href="/connexion.php">Se connecter</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="content-section listings-section" style="padding-top: 50px;">
        <?php if ($categorie === null): ?>
            <div class="empty-state">
                <h3>Catégorie introuvable</h3>
                <p><?= e($error ?? 'Cette catégorie n’existe pas ou plus.') ?></p>
                <a class="outline-button" href="/#categories">Retour aux catégories</a>
            </div>
        <?php else: ?>
            <a class="back-link" href="/#categories">← Toutes les catégories</a>
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Catégorie</p>
                    <h2><?= e($categorie['nom']) ?></h2>
                </div>
                <span class="section-count"><?= count($annonces) ?> annonce<?= count($annonces) > 1 ? 's' : '' ?></span>
            </div>

            <?php if ($error !== null): ?>
                <div class="empty-state error-state">Impossible de charger les annonces : <?= e($error) ?></div>
            <?php elseif ($annonces === []): ?>
                <div class="empty-state">
                    <span class="empty-icon">○</span>
                    <h3>Aucune annonce dans cette catégorie</h3>
                    <p>Sois le premier à en publier une.</p>
                    <a class="outline-button" href="<?= $user !== null ? '/creer-annonce.php' : '/connexion.php?redirect=/creer-annonce.php' ?>">Déposer une annonce</a>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($annonces as $annonce): ?>
                        <a class="listing-card" href="/annonce.php?id=<?= (int) $annonce['id_annonce'] ?>">
                            <div class="listing-image">
                                <?php if ($annonce['photo_url'] !== null): ?>
                                    <img src="<?= e($annonce['photo_url']) ?>" alt="Photo de <?= e($annonce['titre']) ?>">
                                <?php else: ?>
                                    <span><?= e(strtoupper(substr($categorie['nom'], 0, 3))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="listing-body">
                                <div class="listing-meta">
                                    <span><?= e($categorie['nom']) ?></span>
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
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
