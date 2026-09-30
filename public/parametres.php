<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();

$profileErrors = [];
$passwordErrors = [];
$profileValues = [
    'prenom' => $user['prenom'], 'nom' => $user['nom'], 'email' => $user['email'],
    'telephone' => $user['telephone'] ?? '', 'adresse' => $user['adresse'] ?? '',
    'ville' => $user['ville'] ?? '', 'pays' => $user['pays'] ?? 'France',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profil') {
    $profileValues['prenom'] = trim($_POST['prenom'] ?? '');
    $profileValues['nom'] = trim($_POST['nom'] ?? '');
    $profileValues['email'] = strtolower(trim($_POST['email'] ?? ''));
    $profileValues['telephone'] = trim($_POST['telephone'] ?? '');
    $profileValues['adresse'] = trim($_POST['adresse'] ?? '');
    $profileValues['ville'] = trim($_POST['ville'] ?? '');
    $profileValues['pays'] = trim($_POST['pays'] ?? '');

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $profileErrors[] = 'La session du formulaire a expiré. Recommencez.';
    }
    if ($profileValues['prenom'] === '' || $profileValues['nom'] === '') {
        $profileErrors[] = 'Le prénom et le nom sont obligatoires.';
    }
    if (!filter_var($profileValues['email'], FILTER_VALIDATE_EMAIL)) {
        $profileErrors[] = 'Saisissez une adresse email valide.';
    }
    if (!preg_match('/^[0-9+\s.-]{6,20}$/', $profileValues['telephone'])) {
        $profileErrors[] = 'Saisis un numéro de téléphone valide.';
    }
    if ($profileValues['adresse'] === '' || $profileValues['ville'] === '' || $profileValues['pays'] === '') {
        $profileErrors[] = 'L\'adresse, la ville et le pays sont obligatoires.';
    }

    if ($profileErrors === []) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE utilisateurs SET prenom = :prenom, nom = :nom, email = :email,
                    telephone = :telephone, adresse = :adresse, ville = :ville, pays = :pays
                 WHERE id_utilisateur = :id
                 RETURNING id_utilisateur, prenom, nom, email, photo_url, theme,
                           telephone, adresse, ville, pays'
            );
            $stmt->execute([
                'prenom' => $profileValues['prenom'],
                'nom' => $profileValues['nom'],
                'email' => $profileValues['email'],
                'telephone' => $profileValues['telephone'],
                'adresse' => $profileValues['adresse'],
                'ville' => $profileValues['ville'],
                'pays' => $profileValues['pays'],
                'id' => $user['id_utilisateur'],
            ]);
            $_SESSION['user'] = $stmt->fetch();
            header('Location: /parametres.php?profil=1');
            exit;
        } catch (PDOException $exception) {
            $profileErrors[] = $exception->getCode() === '23505'
                ? 'Cette adresse email est déjà utilisée par un autre compte.'
                : 'Impossible de mettre à jour le profil pour le moment.';
        }
    }
}

$photoErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'photo') {
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $photoErrors[] = 'La session du formulaire a expiré. Recommencez.';
    } else {
        $file = $_FILES['photo'] ?? null;
        $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $photoErrors[] = 'Choisis une image.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
            $photoErrors[] = 'L\'image doit faire au maximum 3 Mo.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!isset($allowedMimeTypes[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
                $photoErrors[] = 'Seules les photos JPG, PNG ou WebP sont acceptées.';
            } else {
                $uploadDirectory = __DIR__ . '/uploads/avatars';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $photoErrors[] = 'Le dossier de stockage est indisponible.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
                    $destination = $uploadDirectory . '/' . $fileName;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $photoErrors[] = 'Impossible d\'enregistrer la photo.';
                    } else {
                        $oldPhoto = $user['photo_url'] ?? null;
                        $stmt = $pdo->prepare(
                            'UPDATE utilisateurs SET photo_url = :url WHERE id_utilisateur = :id
                             RETURNING id_utilisateur, prenom, nom, email, photo_url, theme,
                                      telephone, adresse, ville, pays'
                        );
                        $stmt->execute(['url' => '/uploads/avatars/' . $fileName, 'id' => $user['id_utilisateur']]);
                        $_SESSION['user'] = $stmt->fetch();
                        if ($oldPhoto) {
                            @unlink(__DIR__ . $oldPhoto);
                        }
                        header('Location: /parametres.php?photo=1');
                        exit;
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'theme') {
    $theme = $_POST['theme'] ?? '';
    if (checkCsrf($_POST['csrf_token'] ?? null) && in_array($theme, ['clair', 'sombre', 'auto'], true)) {
        $stmt = $pdo->prepare(
            'UPDATE utilisateurs SET theme = :theme WHERE id_utilisateur = :id
             RETURNING id_utilisateur, prenom, nom, email, photo_url, theme,
                       telephone, adresse, ville, pays'
        );
        $stmt->execute(['theme' => $theme, 'id' => $user['id_utilisateur']]);
        $_SESSION['user'] = $stmt->fetch();
    }
    header('Location: /parametres.php?theme=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mot_de_passe') {
    $currentPassword = $_POST['mot_de_passe_actuel'] ?? '';
    $newPassword = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $passwordErrors[] = 'La session du formulaire a expiré. Recommencez.';
    } else {
        $stmt = $pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id_utilisateur = :id');
        $stmt->execute(['id' => $user['id_utilisateur']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $passwordErrors[] = 'Le mot de passe actuel est incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $passwordErrors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($newPassword !== $confirmation) {
            $passwordErrors[] = 'Les deux mots de passe ne correspondent pas.';
        }

        if ($passwordErrors === []) {
            $update = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = :hash WHERE id_utilisateur = :id');
            $update->execute([
                'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $user['id_utilisateur'],
            ]);
            header('Location: /parametres.php?motdepasse=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav>
    </header>
    <main class="form-page">
        <div class="listing-form-panel" style="display: flex; flex-direction: column; gap: 24px;">
            <div>
                <p class="eyebrow">Ton compte</p>
                <h1 style="font-size: 34px; font-weight: normal; margin: 6px 0 0;">Paramètres</h1>
            </div>

            <?php if (isset($_GET['profil'])): ?><div class="success-message">Ton profil a bien été mis à jour.</div><?php endif; ?>
            <?php if (isset($_GET['motdepasse'])): ?><div class="success-message">Ton mot de passe a bien été changé.</div><?php endif; ?>
            <?php if (isset($_GET['photo'])): ?><div class="success-message">Ta photo de profil a bien été mise à jour.</div><?php endif; ?>
            <?php if (isset($_GET['theme'])): ?><div class="success-message">Ton thème a bien été enregistré.</div><?php endif; ?>

            <section style="text-align: center;">
                <h2 style="font-size: 26px; font-weight: normal; margin: 0 0 18px;">Photo de profil</h2>
                <?php if ($photoErrors !== []): ?><div class="form-errors" style="text-align: left;"><?php foreach ($photoErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data" id="photo-form">
                    <input type="hidden" name="action" value="photo">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <label class="avatar-upload" for="photo-input">
                        <div class="avatar avatar-xl" style="margin: 0 auto;">
                            <?php if (!empty($user['photo_url'])): ?>
                                <img class="avatar-photo" src="<?= e($user['photo_url']) ?>" alt="">
                            <?php else: ?>
                                <?= e(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1))) ?>
                            <?php endif; ?>
                        </div>
                        <span class="avatar-upload-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </span>
                    </label>
                    <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="document.getElementById('photo-form').submit()">
                </form>
            </section>

            <section style="text-align: center;">
                <h2 style="font-size: 26px; font-weight: normal; margin: 0 0 14px;">Thème du site</h2>
                <form method="post" style="display: flex; justify-content: center;">
                    <input type="hidden" name="action" value="theme">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="theme-toggle">
                        <label class="theme-pill <?= currentTheme() === 'clair' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" value="clair" style="display:none;" onchange="this.form.submit()" <?= currentTheme() === 'clair' ? 'checked' : '' ?>>
                            Clair
                        </label>
                        <label class="theme-pill <?= currentTheme() === 'sombre' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" value="sombre" style="display:none;" onchange="this.form.submit()" <?= currentTheme() === 'sombre' ? 'checked' : '' ?>>
                            Sombre
                        </label>
                        <label class="theme-pill <?= currentTheme() === 'auto' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" value="auto" style="display:none;" onchange="this.form.submit()" <?= currentTheme() === 'auto' ? 'checked' : '' ?>>
                            Appareil
                        </label>
                    </div>
                    <noscript><button class="primary-button" type="submit" style="margin-left:10px;">Appliquer</button></noscript>
                </form>
            </section>

            <section class="form-panel">
                <h2>Informations du profil</h2>
                <?php if ($profileErrors !== []): ?><div class="form-errors"><?php foreach ($profileErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
                <form method="post" class="stack-form">
                    <input type="hidden" name="action" value="profil">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="form-row">
                        <label>Prénom<input name="prenom" value="<?= e($profileValues['prenom']) ?>" required></label>
                        <label>Nom<input name="nom" value="<?= e($profileValues['nom']) ?>" required></label>
                    </div>
                    <label>Email<input type="email" name="email" value="<?= e($profileValues['email']) ?>" required></label>
                    <div class="form-row">
                        <label>Téléphone<input type="tel" name="telephone" value="<?= e($profileValues['telephone']) ?>" placeholder="06 12 34 56 78" required></label>
                        <label>Ville<input name="ville" value="<?= e($profileValues['ville']) ?>" required></label>
                    </div>
                    <div class="form-row">
                        <label>Adresse<input name="adresse" value="<?= e($profileValues['adresse']) ?>" required></label>
                        <label>Pays<input name="pays" value="<?= e($profileValues['pays']) ?>" required></label>
                    </div>
                    <div class="form-actions" style="justify-content: flex-start;">
                        <button class="primary-button" type="submit">Enregistrer les modifications</button>
                    </div>
                </form>
            </section>

            <section class="form-panel">
                <h2>Changer le mot de passe</h2>
                <?php if ($passwordErrors !== []): ?><div class="form-errors"><?php foreach ($passwordErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
                <form method="post" class="stack-form">
                    <input type="hidden" name="action" value="mot_de_passe">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <label>Mot de passe actuel<input type="password" name="mot_de_passe_actuel" required></label>
                    <label>Nouveau mot de passe <small>8 caractères minimum</small><input type="password" name="nouveau_mot_de_passe" required></label>
                    <label>Confirmation<input type="password" name="confirmation" required></label>
                    <div class="form-actions" style="justify-content: flex-start;">
                        <button class="primary-button" type="submit">Changer le mot de passe</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
