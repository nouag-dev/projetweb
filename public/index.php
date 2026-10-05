<?php
require_once __DIR__ . '/../src/config/auth.php';

$search = trim($_GET['q'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;
$minimumPrice = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT);
$maximumPrice = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT);
$state = trim((string) ($_GET['state'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'recent');
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$sortOptions = [
    'recent' => 'a.date_publication DESC',
    'price_asc' => 'a.prix ASC, a.date_publication DESC',
    'price_desc' => 'a.prix DESC, a.date_publication DESC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'recent';
}
if ($minimumPrice === false || $minimumPrice < 0) {
    $minimumPrice = null;
}
if ($maximumPrice === false || $maximumPrice < 0) {
    $maximumPrice = null;
}
if ($minimumPrice !== null && $maximumPrice !== null && $minimumPrice > $maximumPrice) {
    [$minimumPrice, $maximumPrice] = [$maximumPrice, $minimumPrice];
}
$categories = [];
$annonces = [];
$resultCount = 0;
$pageSize = 24;
$pageCount = 1;
$error = null;
$user = currentUser();

try {
    $pdo = getConnexion();

    $categories = $pdo->query(
        'SELECT id_categorie, nom, icone FROM categories ORDER BY nom'
    )->fetchAll();

    $where = <<<'SQL'
                WHERE a.statut_vente = 'disponible'
                    AND (:search = '' OR a.titre ILIKE :pattern_title OR a.description ILIKE :pattern_description)
          AND (CAST(:category_id AS integer) IS NULL OR a.id_categorie = CAST(:category_id AS integer))
          AND (CAST(:minimum_price AS numeric) IS NULL OR a.prix >= CAST(:minimum_price AS numeric))
          AND (CAST(:maximum_price AS numeric) IS NULL OR a.prix <= CAST(:maximum_price AS numeric))
          AND (:state = '' OR a.etat = :state_filter)
    SQL;
    $parameters = [
        'search' => $search,
        'pattern_title' => '%' . $search . '%',
        'pattern_description' => '%' . $search . '%',
        'category_id' => $categoryId,
        'minimum_price' => $minimumPrice,
        'maximum_price' => $maximumPrice,
        'state' => $state,
        'state_filter' => $state,
    ];
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM annonces a ' . $where);
    $countStmt->execute($parameters);
    $resultCount = (int) $countStmt->fetchColumn();
    $pageCount = max(1, (int) ceil($resultCount / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

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
    SQL . $where . ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    $annonces = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Une erreur est survenue lors du chargement des annonces.';
}

$states = ['Neuf', 'Très bon état', 'Bon état', 'À restaurer', 'Service'];
$paginationParameters = array_filter([
    'q' => $search,
    'category' => $categoryId,
    'min_price' => $minimumPrice,
    'max_price' => $maximumPrice,
    'state' => $state,
    'sort' => $sort,
], static fn ($value): bool => $value !== null && $value !== '');

function iconForCategory(?string $icon): string
{
    return match ($icon) {
        'laptop' => '▣',
        'book' => '▤',
        'chair' => '▥',
        'shirt' => '◇',
        'hand' => '✦',
        'sport' => '◎',
        'computer' => '▧',
        'music' => '♫',
        'games' => '◫',
        'beauty' => '❊',
        'decor' => '◈',
        'bike' => '☖',
        'paper' => '▦',
        default => '•',
    };
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
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
                <a class="sell-button" href="/creer-annonce.php">+ Déposer une annonce</a>
                <?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
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
            <?php $categoryScrollDuration = max(16, count($categories) * 2.6); ?>
            <div class="category-scroll">
                <div class="category-scroll-track" style="animation-duration: <?= $categoryScrollDuration ?>s;">
                    <?php foreach (array_merge($categories, $categories) as $category): ?>
                        <a class="category-item" href="/categorie.php?id=<?= (int) $category['id_categorie'] ?>">
                            <span class="category-icon"><?= iconForCategory($category['icone']) ?></span>
                            <span><?= e($category['nom']) ?></span>
                            <span class="category-arrow">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="content-section listings-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Mis en ligne récemment</p>
                    <h2><?= $search !== '' ? 'Résultats pour « ' . e($search) . ' »' : 'Les dernières annonces' ?></h2>
                </div>
                <span class="section-count"><?= $resultCount ?> résultat<?= $resultCount > 1 ? 's' : '' ?></span>
            </div>

            <form class="listing-filters" method="get" action="/">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Mot-clé" aria-label="Mot-clé">
                <select name="category" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id_categorie'] ?>" <?= $categoryId === (int) $category['id_categorie'] ? 'selected' : '' ?>><?= e($category['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="min_price" min="0" step="0.01" value="<?= $minimumPrice !== null ? e((string) $minimumPrice) : '' ?>" placeholder="Prix min" aria-label="Prix minimum">
                <input type="number" name="max_price" min="0" step="0.01" value="<?= $maximumPrice !== null ? e((string) $maximumPrice) : '' ?>" placeholder="Prix max" aria-label="Prix maximum">
                <select name="state" aria-label="État">
                    <option value="">Tous les états</option>
                    <?php foreach ($states as $availableState): ?>
                        <option value="<?= e($availableState) ?>" <?= $state === $availableState ? 'selected' : '' ?>><?= e($availableState) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" aria-label="Trier par">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                </select>
                <button class="primary-button" type="submit">Filtrer</button>
            </form>

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
                <?php if ($pageCount > 1): ?>
                    <nav class="pagination" aria-label="Pagination des annonces">
                        <?php if ($page > 1): ?>
                            <a href="/?<?= e(http_build_query($paginationParameters + ['page' => $page - 1])) ?>" aria-label="Page précédente">← Précédent</a>
                        <?php endif; ?>
                        <span>Page <?= $page ?> sur <?= $pageCount ?></span>
                        <?php if ($page < $pageCount): ?>
                            <a href="/?<?= e(http_build_query($paginationParameters + ['page' => $page + 1])) ?>" aria-label="Page suivante">Suivant →</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
