<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();
$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id_annonce', FILTER_VALIDATE_INT);
$errors = [];
$states = ['Neuf', 'Très bon état', 'Bon état', 'À restaurer', 'Service'];
$categories = $pdo->query('SELECT id_categorie, nom FROM categories ORDER BY nom')->fetchAll();

$stmt = $pdo->prepare(
    "SELECT id_annonce, titre, description, prix, etat, id_categorie
     FROM annonces
     WHERE id_annonce = :id AND id_utilisateur = :user_id AND statut_vente = 'disponible'"
);
$stmt->execute(['id' => $listingId, 'user_id' => $user['id_utilisateur']]);
$annonce = $stmt->fetch();

if ($annonce === false) {
    http_response_code(404);
}

$values = $annonce ?: ['titre' => '', 'description' => '', 'prix' => '', 'etat' => '', 'id_categorie' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $annonce !== false) {
    foreach (['titre', 'description', 'prix', 'etat', 'id_categorie'] as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $price = filter_var(str_replace(',', '.', $values['prix']), FILTER_VALIDATE_FLOAT);
    $categoryId = filter_var($values['id_categorie'], FILTER_VALIDATE_INT);
    $categoryIds = array_map('intval', array_column($categories, 'id_categorie'));

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Recommencez.';
    }
    if ($values['titre'] === '' || strlen($values['titre']) > 150) {
        $errors[] = 'Le titre est obligatoire et doit faire au maximum 150 caractères.';
    }
    if ($values['description'] === '') {
        $errors[] = 'La description est obligatoire.';
    }
    if ($price === false || $price < 0 || $price > 99999999.99) {
        $errors[] = 'Saisissez un prix valide.';
    }
    if (!in_array($values['etat'], $states, true)) {
        $errors[] = 'Sélectionnez un état.';
    }
    if ($categoryId === false || !in_array($categoryId, $categoryIds, true)) {
        $errors[] = 'Sélectionnez une catégorie.';
    }

    if ($errors === []) {
        $updateStmt = $pdo->prepare(
            "UPDATE annonces
             SET titre = :titre, description = :description, prix = :prix, etat = :etat, id_categorie = :category
             WHERE id_annonce = :id AND id_utilisateur = :user_id AND statut_vente = 'disponible'"
        );
        $updateStmt->execute([
            'titre' => $values['titre'],
            'description' => $values['description'],
            'prix' => $price,
            'etat' => $values['etat'],
            'category' => $categoryId,
            'id' => $listingId,
            'user_id' => $user['id_utilisateur'],
        ]);
        header('Location: /profil.php?updated=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une annonce | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/profil.php">Mon profil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="form-page">
        <section class="form-panel listing-form-panel">
            <p class="eyebrow">Mettre à jour</p>
            <h1>Modifier l’annonce</h1>
            <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <?php if ($annonce === false): ?>
                <div class="empty-state"><h3>Annonce indisponible</h3><p>Elle a peut-être été vendue, retirée ou ne t’appartient pas.</p><a class="outline-button" href="/profil.php">Retour au profil</a></div>
            <?php else: ?>
                <form method="post" class="stack-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id_annonce" value="<?= (int) $listingId ?>">
                    <label>Titre<input name="titre" maxlength="150" value="<?= e($values['titre']) ?>" required></label>
                    <label>Description<textarea name="description" rows="6" required><?= e($values['description']) ?></textarea></label>
                    <div class="form-row">
                        <label>Prix en euros<input type="text" name="prix" inputmode="decimal" value="<?= e((string) $values['prix']) ?>" required></label>
                        <label>Catégorie<select name="id_categorie" required><option value="">Choisir une catégorie</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>" <?= (int) $values['id_categorie'] === (int) $category['id_categorie'] ? 'selected' : '' ?>><?= e($category['nom']) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label>État<select name="etat" required><option value="">Choisir un état</option><?php foreach ($states as $state): ?><option value="<?= e($state) ?>" <?= $values['etat'] === $state ? 'selected' : '' ?>><?= e($state) ?></option><?php endforeach; ?></select></label>
                    <div class="form-actions"><a class="outline-button" href="/profil.php">Annuler</a><button class="primary-button" type="submit">Enregistrer</button></div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
