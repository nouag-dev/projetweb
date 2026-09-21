<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$categories = getConnexion()->query('SELECT id_categorie, nom FROM categories ORDER BY nom')->fetchAll();
$errors = [];
$values = ['titre' => '', 'description' => '', 'prix' => '', 'etat' => '', 'id_categorie' => ''];
$states = ['Neuf', 'Très bon état', 'Bon état', 'À restaurer', 'Service'];
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$validatedPhotos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $price = filter_var(str_replace(',', '.', $values['prix']), FILTER_VALIDATE_FLOAT);
    $categoryId = filter_var($values['id_categorie'], FILTER_VALIDATE_INT);

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
    $categoryIds = array_column($categories, 'id_categorie');
    if ($categoryId === false || !in_array($categoryId, $categoryIds, true)) {
        $errors[] = 'Sélectionnez une catégorie.';
    }

    $uploadedPhotos = $_FILES['photos'] ?? null;
    if (is_array($uploadedPhotos) && isset($uploadedPhotos['error']) && is_array($uploadedPhotos['error'])) {
        $photoCount = count(array_filter($uploadedPhotos['error'], static fn (int $error): bool => $error !== UPLOAD_ERR_NO_FILE));
        if ($photoCount > 5) {
            $errors[] = 'Vous pouvez ajouter au maximum 5 photos.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($uploadedPhotos['error'] as $index => $uploadError) {
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($uploadError !== UPLOAD_ERR_OK || $uploadedPhotos['size'][$index] > 5 * 1024 * 1024) {
                $errors[] = 'Chaque photo doit faire au maximum 5 Mo.';
                continue;
            }
            $mimeType = $finfo->file($uploadedPhotos['tmp_name'][$index]);
            if (!isset($allowedMimeTypes[$mimeType]) || @getimagesize($uploadedPhotos['tmp_name'][$index]) === false) {
                $errors[] = 'Seules les photos JPG, PNG ou WebP sont acceptées.';
                continue;
            }
            $validatedPhotos[] = [
                'tmp_name' => $uploadedPhotos['tmp_name'][$index],
                'extension' => $allowedMimeTypes[$mimeType],
            ];
        }
    }

    if ($errors === []) {
        $pdo = getConnexion();
        $uploadDirectory = __DIR__ . '/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $errors[] = 'Le dossier de stockage des photos est indisponible.';
        } else {
            $storedFiles = [];
            try {
                foreach ($validatedPhotos as $photo) {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $photo['extension'];
                    $destination = $uploadDirectory . '/' . $fileName;
                    if (!move_uploaded_file($photo['tmp_name'], $destination)) {
                        throw new RuntimeException('Impossible de stocker une photo.');
                    }
                    $storedFiles[] = ['path' => $destination, 'url' => '/uploads/' . $fileName];
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO annonces (titre, description, prix, etat, id_utilisateur, id_categorie)
                     VALUES (:titre, :description, :prix, :etat, :id_utilisateur, :id_categorie)
                     RETURNING id_annonce'
                );
                $stmt->execute([
                    'titre' => $values['titre'],
                    'description' => $values['description'],
                    'prix' => $price,
                    'etat' => $values['etat'],
                    'id_utilisateur' => currentUser()['id_utilisateur'],
                    'id_categorie' => $categoryId,
                ]);
                $announcementId = $stmt->fetchColumn();

                $photoStmt = $pdo->prepare(
                    'INSERT INTO photos (url, ordre, id_annonce) VALUES (:url, :ordre, :id_annonce)'
                );
                foreach ($storedFiles as $order => $file) {
                    $photoStmt->execute([
                        'url' => $file['url'],
                        'ordre' => $order,
                        'id_annonce' => $announcementId,
                    ]);
                }
                $pdo->commit();
                header('Location: /profil.php?published=1');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($storedFiles as $file) {
                    @unlink($file['path']);
                }
                $errors[] = 'Impossible de publier l’annonce pour le moment.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Déposer une annonce | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/profil.php">Mon profil</a><a class="logout-link" href="/deconnexion.php">Se déconnecter</a></nav></header>
    <main class="form-page"><section class="form-panel listing-form-panel"><p class="eyebrow">Faire circuler un objet</p><h1>Déposer une annonce</h1><p class="form-lead">Donne quelques détails pour aider les autres étudiants à se décider.</p>
        <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <label>Titre de l'annonce<input name="titre" maxlength="150" value="<?= e($values['titre']) ?>" placeholder="Ex. Lampe de bureau" required></label>
            <label>Description<textarea name="description" rows="6" placeholder="Décris l'objet, son état et les informations utiles..." required><?= e($values['description']) ?></textarea></label>
            <div class="form-row"><label>Prix en euros<input type="text" name="prix" inputmode="decimal" value="<?= e($values['prix']) ?>" placeholder="0,00" required></label><label>Catégorie<select name="id_categorie" required><option value="">Choisir une catégorie</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id_categorie'] ?>" <?= $values['id_categorie'] == $category['id_categorie'] ? 'selected' : '' ?>><?= e($category['nom']) ?></option><?php endforeach; ?></select></label></div>
            <label>État<select name="etat" required><option value="">Choisir un état</option><?php foreach ($states as $state): ?><option <?= $values['etat'] === $state ? 'selected' : '' ?>><?= e($state) ?></option><?php endforeach; ?></select></label>
            <label>Photos de l'article <small>JPG, PNG ou WebP, 5 Mo maximum par photo, 5 photos maximum</small><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
            <div class="form-actions"><a class="outline-button" href="/profil.php">Annuler</a><button class="primary-button" type="submit">Publier l'annonce</button></div>
        </form>
    </section></main>
</body>
</html>
