<?php
require_once __DIR__ . '/../src/config/auth.php';

if (currentUser() !== null) {
    header('Location: /profil.php');
    exit;
}

$pdo = getConnexion();
$ecoles = $pdo->query('SELECT id_ecole, nom, domaine_email FROM ecoles ORDER BY nom')->fetchAll();
$domainesParEcole = $pdo->query(
    'SELECT id_ecole, domaine_email FROM ecole_domaines ORDER BY domaine_email'
)->fetchAll();
foreach ($ecoles as &$ecole) {
    $ecole['domaines_email'] = [];
    foreach ($domainesParEcole as $domaine) {
        if ((int) $domaine['id_ecole'] === (int) $ecole['id_ecole']) {
            $ecole['domaines_email'][] = $domaine['domaine_email'];
        }
    }
    if ($ecole['domaines_email'] === []) {
        $ecole['domaines_email'][] = $ecole['domaine_email'];
    }
}
unset($ecole);

$errors = [];
$values = [
    'prenom' => '', 'nom' => '', 'email' => '', 'date_naissance' => '',
    'ville' => '', 'adresse' => '', 'telephone' => '', 'sexe' => '', 'id_ecole' => '',
    'ecole_autre' => '', 'pays' => 'France',
];

$ageMinDate = (new DateTime('-17 years'))->format('Y-m-d');
$ageMaxDate = (new DateTime('-25 years'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $values['email'] = strtolower($values['email']);
    $password = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Recommencez.';
    }
    if ($values['prenom'] === '' || $values['nom'] === '') {
        $errors[] = 'Le prénom et le nom sont obligatoires.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Saisissez une adresse email valide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    $birthDate = DateTime::createFromFormat('Y-m-d', $values['date_naissance']);
    if ($birthDate === false) {
        $errors[] = 'Indique une date de naissance valide.';
    } else {
        $age = (new DateTime())->diff($birthDate)->y;
        if ($age < 17 || $age > 25) {
            $errors[] = 'L\'inscription est réservée aux 17-25 ans.';
        }
    }

    if ($values['ville'] === '') {
        $errors[] = 'La ville est obligatoire.';
    }
    if ($values['adresse'] === '') {
        $errors[] = 'L\'adresse est obligatoire.';
    }
    if ($values['pays'] === '') {
        $errors[] = 'Le pays est obligatoire.';
    }
    if (!preg_match('/^[0-9+\s.-]{6,20}$/', $values['telephone'])) {
        $errors[] = 'Saisis un numéro de téléphone valide.';
    }
    if (!in_array($values['sexe'], ['Femme', 'Homme', 'Autre'], true)) {
        $errors[] = 'Sélectionne une option pour le sexe.';
    }

    $ecoleChoisie = null;
    foreach ($ecoles as $ecole) {
        if ((string) $ecole['id_ecole'] === $values['id_ecole']) {
            $ecoleChoisie = $ecole;
            break;
        }
    }
    $ecoleAutre = null;
    $ecoleId = null;
    if ($values['id_ecole'] === 'autre') {
        $ecoleAutre = $values['ecole_autre'];
        if ($ecoleAutre === '' || strlen($ecoleAutre) > 150) {
            $errors[] = 'Indique le nom de ton établissement (150 caractères maximum).';
        }
    } elseif ($ecoleChoisie !== null) {
        $ecoleId = $ecoleChoisie['id_ecole'];
        $emailDomain = substr(strrchr($values['email'], '@') ?: '', 1);
        $allowedDomains = array_map('strtolower', $ecoleChoisie['domaines_email']);
        if ($emailDomain === '' || !in_array($emailDomain, $allowedDomains, true)) {
            $errors[] = 'L’adresse email doit utiliser un domaine officiel de l’établissement choisi : '
                . implode(', ', $ecoleChoisie['domaines_email']) . '.';
        }
    } else {
        $errors[] = 'Sélectionne ton établissement dans la liste.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs
                    (nom, prenom, email, mot_de_passe, email_verifie, id_ecole, ecole_autre,
                     date_naissance, ville, adresse, telephone, sexe, pays)
                 VALUES
                    (:nom, :prenom, :email, :mot_de_passe, TRUE, :id_ecole, :ecole_autre,
                     :date_naissance, :ville, :adresse, :telephone, :sexe, :pays)
                 RETURNING id_utilisateur, nom, prenom, email, photo_url, theme,
                           telephone, adresse, ville, pays'
            );
            $stmt->execute([
                'nom' => $values['nom'],
                'prenom' => $values['prenom'],
                'email' => $values['email'],
                'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT),
                'id_ecole' => $ecoleId,
                'ecole_autre' => $ecoleAutre,
                'date_naissance' => $values['date_naissance'],
                'ville' => $values['ville'],
                'adresse' => $values['adresse'],
                'telephone' => $values['telephone'],
                'sexe' => $values['sexe'],
                'pays' => $values['pays'],
            ]);
            $newUser = $stmt->fetch();
            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user'] = $newUser;
            header('Location: /profil.php?created=1');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getCode() === '23505'
                ? 'Cette adresse email est déjà utilisée.'
                : 'Une erreur technique est survenue pendant la création du compte. Merci de réessayer.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Campus account creation failed: ' . $exception->getMessage());
            $errors[] = 'Impossible de créer le compte. Merci de réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="account-page">
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><a class="nav-link" href="/connexion.php">Déjà inscrit ? Se connecter</a></header>
    <main class="account-layout">
        <section class="account-intro"><p class="eyebrow">Rejoins la communauté</p><h1>Ton campus, tes échanges, ton espace.</h1><p>Réservé aux étudiants de 17 à 25 ans, avec un email de ton établissement.</p></section>
        <section class="form-panel"><p class="eyebrow">Ton espace étudiant</p><h2>Créer un compte</h2>
            <?php if ($errors !== []): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="form-row">
                    <label>Prénom<input name="prenom" value="<?= e($values['prenom']) ?>" required></label>
                    <label>Nom<input name="nom" value="<?= e($values['nom']) ?>" required></label>
                </div>

                <label>Établissement
                    <select id="school-select" name="id_ecole" required>
                        <option value="">Choisir ton établissement</option>
                        <?php foreach ($ecoles as $ecole): ?>
                            <option value="<?= (int) $ecole['id_ecole'] ?>" data-domain="<?= e($ecole['domaine_email']) ?>" data-domains="<?= e(json_encode($ecole['domaines_email'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>" <?= $values['id_ecole'] == $ecole['id_ecole'] ? 'selected' : '' ?>>
                                <?= e($ecole['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="autre" <?= $values['id_ecole'] === 'autre' ? 'selected' : '' ?>>Autre établissement</option>
                    </select>
                    <small id="student-email-example">Modèle d’email à saisir : prenom.nom@ecole.fr.</small>
                </label>
                <label id="other-school-field" <?= $values['id_ecole'] === 'autre' ? '' : 'hidden' ?>>
                    Nom de ton établissement
                    <input id="other-school-name" name="ecole_autre" value="<?= e($values['ecole_autre']) ?>" maxlength="150" <?= $values['id_ecole'] === 'autre' ? 'required' : '' ?>>
                </label>

                <label>Email étudiant<input id="student-email" type="email" name="email" value="<?= e($values['email']) ?>" placeholder="prenom.nom@ecole.fr" required></label>

                <div class="form-row">
                    <label>Date de naissance
                        <input type="date" name="date_naissance" value="<?= e($values['date_naissance']) ?>" min="<?= e($ageMaxDate) ?>" max="<?= e($ageMinDate) ?>" required>
                        <small>Réservé aux 17-25 ans</small>
                    </label>
                    <label>Sexe
                        <select name="sexe" required>
                            <option value="">Choisir</option>
                            <option value="Femme" <?= $values['sexe'] === 'Femme' ? 'selected' : '' ?>>Femme</option>
                            <option value="Homme" <?= $values['sexe'] === 'Homme' ? 'selected' : '' ?>>Homme</option>
                            <option value="Autre" <?= $values['sexe'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </label>
                </div>

                <div class="form-row">
                    <label>Ville<input name="ville" value="<?= e($values['ville']) ?>" placeholder="Limoges" required></label>
                    <label>Téléphone<input type="tel" name="telephone" value="<?= e($values['telephone']) ?>" placeholder="06 12 34 56 78" required></label>
                </div>

                <div class="form-row">
                    <label>Adresse<input name="adresse" value="<?= e($values['adresse']) ?>" placeholder="12 rue des Étudiants" required></label>
                    <label>Pays<input name="pays" value="<?= e($values['pays']) ?>" required></label>
                </div>

                <label>Mot de passe <small>8 caractères minimum</small><input type="password" name="mot_de_passe" required></label>
                <label>Confirmation<input type="password" name="confirmation" required></label>
                <button class="primary-button" type="submit">Créer mon compte</button>
            </form>
        </section>
    </main>
    <script>
    (function () {
        var schoolSelect = document.getElementById('school-select');
        var otherSchoolField = document.getElementById('other-school-field');
        var otherSchoolName = document.getElementById('other-school-name');
        var studentEmail = document.getElementById('student-email');
        var studentEmailExample = document.getElementById('student-email-example');
        function updateOtherSchoolField() {
            var isOther = schoolSelect.value === 'autre';
            otherSchoolField.hidden = !isOther;
            otherSchoolName.required = isOther;
            var selectedOption = schoolSelect.options[schoolSelect.selectedIndex];
            var domain = selectedOption ? selectedOption.getAttribute('data-domain') : '';
            var emailPlaceholder = domain ? 'prenom.nom@' + domain : 'prenom.nom@ecole.fr';
            var emailExample = emailPlaceholder;
            if (selectedOption && selectedOption.value !== 'autre') {
                var domains = JSON.parse(selectedOption.getAttribute('data-domains') || '[]');
                var alternativeDomains = domains.filter(function (allowedDomain) {
                    return allowedDomain !== domain;
                });
                if (alternativeDomains.length > 0) {
                    emailExample += ' (ou prenom.nom@' + alternativeDomains.join(', prenom.nom@') + ')';
                }
            }
            studentEmail.placeholder = emailPlaceholder;
            studentEmailExample.textContent = 'Modèle d’email à saisir : ' + emailExample + '.';
        }
        schoolSelect.addEventListener('change', updateOtherSchoolField);
        updateOtherSchoolField();
    })();
    </script>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
