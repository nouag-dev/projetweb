<?php
require_once __DIR__ . '/../src/config/auth.php';

$search = trim($_GET['q'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;
$categories = [];
$annonces = [];
$error = null;
$user = currentUser();

try {
    $pdo = getConnexion();

    $categories = $pdo->query(
        'SELECT id_categorie, nom, icone FROM categories ORDER BY nom'
    )->fetchAll();

    $sql = <<<'SQL'
        SELECT
            a.id_annonce,
            a.titre,
            a.description,
            a.prix,
            a.etat,
            a.date_publication,
            c.nom AS categorie,
            u.prenom,
            u.nom,
            (SELECT p.url FROM photos p WHERE p.id_annonce = a.id_annonce ORDER BY p.ordre LIMIT 1) AS photo_url
        FROM annonces a
        JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
        LEFT JOIN categories c ON c.id_categorie = a.id_categorie
                WHERE (:search = '' OR a.titre ILIKE :pattern_title OR a.description ILIKE :pattern_description)
                      AND (CAST(:category_id AS integer) IS NULL OR a.id_categorie = CAST(:category_id AS integer))
        ORDER BY a.date_publication DESC
        LIMIT 24
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'search' => $search,
        'pattern_title' => '%' . $search . '%',
        'pattern_description' => '%' . $search . '%',
        'category_id' => $categoryId,
    ]);
    $annonces = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

function iconForCategory(?string $icon): string
{
    return match ($icon) {
        'laptop' => '▣',
        'book' => '▤',
        'chair' => '▥',
        'shirt' => '◇',
        'hand' => '✦',
        default => '•',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus | Les bonnes trouvailles circulent</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">
            <span class="brand-mark">C</span>
            <span>campus<span class="brand-dot">.</span></span>
        </a>
        <nav class="main-nav" aria-label="Navigation principale">
            <a class="nav-link active" href="/">Accueil</a>
            <a class="nav-link" href="#categories">Catégories</a>
            <a class="nav-link" href="/decouvrir.php">Explorer</a>
        </nav>
        <div class="header-actions">
            <?php if ($user !== null): ?>
                <a class="profile-link" href="/profil.php"><?= e($user['prenom']) ?></a>
                <a class="sell-button" href="/creer-annonce.php">+ Déposer une annonce</a>
            <?php else: ?>
                <a class="nav-link" href="/connexion.php">Se connecter</a>
                <a class="sell-button" href="/connexion.php?redirect=/creer-annonce.php">+ Déposer une annonce</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">Le marché de ton campus</p>
                <h1>Ce dont tu as besoin est peut-être déjà <em>tout près.</em></h1>
                <p class="hero-text">Achète, vends et donne une seconde vie aux objets qui circulent entre étudiants.</p>
                <form class="search-form" method="get" action="/">
                    <label class="search-box">
                        <span aria-hidden="true">⌕</span>
                        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Que cherches-tu ?" aria-label="Rechercher une annonce">
                    </label>
                    <button type="submit">Rechercher</button>
                </form>
            </div>
            <div class="hero-note">
                <span class="note-line"></span>
                <p>Des objets utiles.<br><strong>Des échanges simples.</strong></p>
            </div>
        </section>

        <section class="content-section" id="categories">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Trouver rapidement</p>
                    <h2>Explorer par catégorie</h2>
                </div>
                <span class="section-count"><?= count($categories) ?> catégories</span>
            </div>
            <div class="category-list">
                <?php foreach ($categories as $category): ?>
                    <a class="category-item" href="/?category=<?= (int) $category['id_categorie'] ?>">
                        <span class="category-icon"><?= iconForCategory($category['icone']) ?></span>
                        <span><?= e($category['nom']) ?></span>
                        <span class="category-arrow">↗</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="content-section listings-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Mis en ligne récemment</p>
                    <h2><?= $search !== '' ? 'Résultats pour « ' . e($search) . ' »' : 'Les dernières annonces' ?></h2>
                </div>
                <span class="section-count"><?= count($annonces) ?> résultat<?= count($annonces) > 1 ? 's' : '' ?></span>
            </div>

            <?php if ($error !== null): ?>
                <div class="empty-state error-state">Impossible de charger les annonces : <?= e($error) ?></div>
            <?php elseif ($annonces === []): ?>
                <div class="empty-state">
                    <span class="empty-icon">○</span>
                    <h3>Aucune annonce pour le moment</h3>
                    <p>Les premières bonnes affaires arrivent bientôt.</p>
                    <a class="outline-button" href="<?= $user !== null ? '/creer-annonce.php' : '/connexion.php?redirect=/creer-annonce.php' ?>">Déposer la première annonce</a>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($annonces as $annonce): ?>
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
        </section>
    </main>

    <footer class="site-footer">
        <span>campus<span class="brand-dot">.</span></span>
        <span>La plateforme de proximité des étudiants.</span>
    </footer>
</body>
</html>
