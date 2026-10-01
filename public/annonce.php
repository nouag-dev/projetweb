<?php
require_once __DIR__ . '/../src/config/auth.php';

$announcementId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$announcement = null;
$photos = [];
$error = null;
$offerMessage = null;
$offerError = null;
$myOffer = null;
$receivedOffers = [];

$pdo = getConnexion();

if ($announcementId) {
    try {
        $stmt = $pdo->prepare(
            'SELECT a.id_annonce, a.titre, a.description, a.prix, a.etat, a.date_publication,
                    c.nom AS categorie, u.id_utilisateur AS vendeur_id, u.prenom, u.nom, u.photo_url
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

            if (currentUser() !== null) {
                $offerStmt = $pdo->prepare(
                    'SELECT id_offre, montant, statut, message, date_creation
                     FROM offres
                     WHERE id_annonce = :id_annonce AND id_utilisateur = :user_id
                     ORDER BY date_creation DESC
                     LIMIT 1'
                );
                $offerStmt->execute([
                    'id_annonce' => $announcementId,
                    'user_id' => currentUser()['id_utilisateur'],
                ]);
                $myOfferRow = $offerStmt->fetch();
                $myOffer = $myOfferRow !== false ? $myOfferRow : null;
            }

            if (currentUser() !== null && (int) currentUser()['id_utilisateur'] === (int) $announcement['vendeur_id']) {
                $receivedStmt = $pdo->prepare(
                    'SELECT o.id_offre, o.montant, o.message, o.statut, o.date_creation,
                            u.prenom, u.nom
                     FROM offres o
                     JOIN utilisateurs u ON u.id_utilisateur = o.id_utilisateur
                     WHERE o.id_annonce = :id_annonce
                     ORDER BY o.date_creation DESC'
                );
                $receivedStmt->execute(['id_annonce' => $announcementId]);
                $receivedOffers = $receivedStmt->fetchAll();
            }
        }
    } catch (Exception $exception) {
        $error = 'Impossible de charger cette annonce.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repondre_offre') {
    $offreId = filter_input(INPUT_POST, 'offre_id', FILTER_VALIDATE_INT);
    $decision = trim((string) ($_POST['decision'] ?? ''));

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $offerError = 'Le jeton de sécurité est invalide. Merci de réessayer.';
    } elseif (currentUser() === null) {
        header('Location: /connexion.php?redirect=' . rawurlencode('/annonce.php?id=' . (int) $announcementId));
        exit;
    } elseif ($offreId === null || $offreId === false || !in_array($decision, ['acceptee', 'refusee'], true)) {
        $offerError = 'Réponse invalide.';
    } else {
        $updateStmt = $pdo->prepare(
            'UPDATE offres o
             SET statut = :statut,
                 date_reponse = NOW()
             FROM annonces a
             WHERE o.id_offre = :offre_id
               AND a.id_annonce = o.id_annonce
               AND a.id_utilisateur = :user_id'
        );
        $updateStmt->execute([
            'statut' => $decision,
            'offre_id' => $offreId,
            'user_id' => currentUser()['id_utilisateur'],
        ]);
        $offerMessage = $decision === 'acceptee' ? 'L’offre a été acceptée.' : 'L’offre a été refusée.';

        if ($announcementId !== null) {
            $receivedStmt = $pdo->prepare(
                'SELECT o.id_offre, o.montant, o.message, o.statut, o.date_creation,
                        u.prenom, u.nom
                 FROM offres o
                 JOIN utilisateurs u ON u.id_utilisateur = o.id_utilisateur
                 WHERE o.id_annonce = :id_annonce
                 ORDER BY o.date_creation DESC'
            );
            $receivedStmt->execute(['id_annonce' => $announcementId]);
            $receivedOffers = $receivedStmt->fetchAll();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'envoyer_offre') {
    if (currentUser() === null) {
        header('Location: /connexion.php?redirect=' . rawurlencode('/annonce.php?id=' . (int) $announcementId));
        exit;
    }

    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $offerError = 'Le jeton de sécurité est invalide. Merci de réessayer.';
    } elseif ($announcementId === null || $announcement === null) {
        $offerError = 'Cette annonce n’existe plus.';
    } elseif ((int) currentUser()['id_utilisateur'] === (int) $announcement['vendeur_id']) {
        $offerError = 'Vous ne pouvez pas faire une offre sur votre propre annonce.';
    } else {
        $montant = filter_input(INPUT_POST, 'montant', FILTER_VALIDATE_FLOAT);
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($montant === false || $montant <= 0) {
            $offerError = 'Le montant de l’offre doit être un nombre positif.';
        } elseif ($montant >= (float) $announcement['prix']) {
            $offerError = 'L’offre doit être inférieure au prix demandé.';
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO offres (id_annonce, id_utilisateur, montant, message, statut)
                 VALUES (:id_annonce, :id_utilisateur, :montant, :message, :statut)
                 ON CONFLICT (id_utilisateur, id_annonce)
                 DO UPDATE SET montant = EXCLUDED.montant,
                               message = EXCLUDED.message,
                               statut = EXCLUDED.statut,
                               date_reponse = NULL'
            );
            $insertStmt->execute([
                'id_annonce' => $announcementId,
                'id_utilisateur' => currentUser()['id_utilisateur'],
                'montant' => $montant,
                'message' => $message !== '' ? $message : null,
                'statut' => 'en_attente',
            ]);
            $offerMessage = 'Votre offre a bien été envoyée au vendeur.';

            $offerStmt = $pdo->prepare(
                'SELECT id_offre, montant, statut, message, date_creation
                 FROM offres
                 WHERE id_annonce = :id_annonce AND id_utilisateur = :user_id
                 ORDER BY date_creation DESC
                 LIMIT 1'
            );
            $offerStmt->execute([
                'id_annonce' => $announcementId,
                'user_id' => currentUser()['id_utilisateur'],
            ]);
            $myOfferRow = $offerStmt->fetch();
            $myOffer = $myOfferRow !== false ? $myOfferRow : null;
        }
    }
}

if ($announcement === false || $announcement === null) {
    http_response_code(404);
    $announcement = null;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $announcement ? e($announcement['titre']) . ' | Campus' : 'Annonce introuvable | Campus' ?></title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/">Accueil</a><?php if (currentUser()): ?><?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?><?php else: ?><a href="/connexion.php">Se connecter</a><?php endif; ?></nav></header>
    <main class="detail-page">
        <?php if ($announcement === null): ?>
            <div class="empty-state"><h3>Annonce introuvable</h3><p><?= e($error ?? 'Cette annonce n’existe plus ou a été supprimée.') ?></p><a class="outline-button" href="/">Retour aux annonces</a></div>
        <?php else: ?>
            <a class="back-link" href="/">← Retour aux annonces</a>
            <div class="detail-layout">
                <section class="detail-gallery">
                    <?php if ($photos === []): ?><div class="detail-placeholder"><?= e(strtoupper(substr($announcement['categorie'] ?? 'ANN', 0, 3))) ?></div><?php else: ?><div class="detail-main-photo"><img src="<?= e($photos[0]) ?>" alt="Photo de <?= e($announcement['titre']) ?>"></div><?php if (count($photos) > 1): ?><div class="detail-thumbnails"><?php foreach ($photos as $photo): ?><img src="<?= e($photo) ?>" alt="Photo de <?= e($announcement['titre']) ?>"><?php endforeach; ?></div><?php endif; ?><?php endif; ?>
                </section>
                <section class="detail-info"><div class="listing-meta"><span><?= e($announcement['categorie'] ?? 'Autre') ?></span><span><?= e($announcement['etat'] ?? 'Disponible') ?></span></div><h1><?= e($announcement['titre']) ?></h1><p class="detail-price"><?= number_format((float) $announcement['prix'], 2, ',', ' ') ?> €</p><p class="detail-description"><?= nl2br(e($announcement['description'])) ?></p><div class="seller-box"><div class="avatar small-avatar"><?php if (!empty($announcement['photo_url'])): ?><img class="avatar-photo" src="<?= e($announcement['photo_url']) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($announcement['prenom'], 0, 1) . substr($announcement['nom'], 0, 1))) ?><?php endif; ?></div><div><small>Publié par</small><strong><?= e($announcement['prenom'] . ' ' . $announcement['nom']) ?></strong></div></div>

                    <?php if (currentUser() !== null && (int) currentUser()['id_utilisateur'] === (int) $announcement['vendeur_id']): ?>
                        <div class="notice-box">C’est votre annonce.</div>
                        <?php if ($receivedOffers !== []): ?>
                            <div class="offer-panel">
                                <h3>Offres reçues</h3>
                                <?php foreach ($receivedOffers as $saleOffer): ?>
                                    <div class="offer-item">
                                        <div class="offer-topline">
                                            <strong><?= e($saleOffer['prenom'] . ' ' . $saleOffer['nom']) ?></strong>
                                            <span><?= number_format((float) $saleOffer['montant'], 2, ',', ' ') ?> €</span>
                                        </div>
                                        <?php if (!empty($saleOffer['message'])): ?><p><?= e($saleOffer['message']) ?></p><?php endif; ?>
                                        <small>Envoyée le <?= e(date('d/m/Y', strtotime($saleOffer['date_creation']))) ?></small>
                                        <?php if ($saleOffer['statut'] === 'en_attente'): ?>
                                            <form method="post" class="offer-decision">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="repondre_offre">
                                                <input type="hidden" name="id_annonce" value="<?= (int) $announcement['id_annonce'] ?>">
                                                <input type="hidden" name="offre_id" value="<?= (int) $saleOffer['id_offre'] ?>">
                                                <div class="offer-actions">
                                                    <button class="primary-button compact-inline" type="submit" name="decision" value="acceptee">Accepter</button>
                                                    <button class="outline-button compact-inline" type="submit" name="decision" value="refusee">Refuser</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span class="offer-status offer-status-<?= e($saleOffer['statut']) ?>"><?= $saleOffer['statut'] === 'acceptee' ? 'Acceptée' : 'Refusée' ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="offer-panel empty-offer-panel">
                                <h3>Offres reçues</h3>
                                <p>Aucune offre n’a été faite sur cette annonce pour le moment.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($offerMessage !== null): ?><div class="success-message"><?= e($offerMessage) ?></div><?php endif; ?>
                        <?php if ($offerError !== null): ?><div class="form-errors"><p><?= e($offerError) ?></p></div><?php endif; ?>
                        <div class="offer-panel">
                            <h3>Faire une offre</h3>
                            <?php if (currentUser() === null): ?>
                                <p>Connecte-toi pour proposer un prix au vendeur.</p>
                                <a class="primary-button contact-button" href="/connexion.php?redirect=<?= rawurlencode('/annonce.php?id=' . (int) $announcement['id_annonce']) ?>">Se connecter</a>
                            <?php elseif ($myOffer !== null): ?>
                                <p>Tu as déjà envoyé une offre de <strong><?= number_format((float) $myOffer['montant'], 2, ',', ' ') ?> €</strong>.</p>
                                <span class="offer-status offer-status-<?= e($myOffer['statut']) ?>"><?= $myOffer['statut'] === 'en_attente' ? 'En attente du vendeur' : ($myOffer['statut'] === 'acceptee' ? 'Acceptée' : 'Refusée') ?></span>
                                <?php if (!empty($myOffer['message'])): ?><p class="offer-note"><?= e($myOffer['message']) ?></p><?php endif; ?>
                            <?php else: ?>
                                <form method="post" class="stack-form offer-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="envoyer_offre">
                                    <label>
                                        Montant proposé
                                        <input type="number" name="montant" min="1" step="0.01" placeholder="Ex : 30" required>
                                    </label>
                                    <label>
                                        Message
                                        <textarea name="message" rows="3" maxlength="500" placeholder="Explique pourquoi tu es intéressé et à quel prix tu peux aller."></textarea>
                                    </label>
                                    <button class="primary-button" type="submit">Envoyer l'offre</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <a class="primary-button contact-button" href="<?= currentUser() ? '/chat.php?annonce=' . (int) $announcement['id_annonce'] : '/connexion.php?redirect=' . rawurlencode('/chat.php?annonce=' . (int) $announcement['id_annonce']) ?>">Contacter le vendeur</a>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
