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
$reviews = [];
$averageRating = null;
$sellerReviewCount = 0;
$myReview = null;
$reportMessage = null;

$pdo = getConnexion();

if ($announcementId) {
    try {
        $stmt = $pdo->prepare(
                'SELECT a.id_annonce, a.titre, a.description, a.prix, a.etat, a.date_publication, a.statut_vente,
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

            $reviewStmt = $pdo->prepare(
                'SELECT r.note, r.commentaire, r.date_creation, buyer.prenom
                 FROM avis r JOIN utilisateurs buyer ON buyer.id_utilisateur = r.id_acheteur
                 WHERE r.id_annonce = :id ORDER BY r.date_creation DESC'
            );
            $reviewStmt->execute(['id' => $announcementId]);
            $reviews = $reviewStmt->fetchAll();
            $sellerRatingStmt = $pdo->prepare(
                'SELECT AVG(r.note), COUNT(*)
                 FROM avis r JOIN annonces seller_listing ON seller_listing.id_annonce = r.id_annonce
                 WHERE seller_listing.id_utilisateur = :seller'
            );
            $sellerRatingStmt->execute(['seller' => $announcement['vendeur_id']]);
            [$averageRating, $sellerReviewCount] = $sellerRatingStmt->fetch(PDO::FETCH_NUM);
            if ($reviews !== []) {
                $averageRating = array_sum(array_column($reviews, 'note')) / count($reviews);
            }

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signaler_annonce') {
    if (currentUser() === null) {
        header('Location: /connexion.php?redirect=' . rawurlencode('/annonce.php?id=' . (int) $announcementId));
        exit;
    }
    $reason = trim((string) ($_POST['motif'] ?? ''));
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $offerError = 'La session du formulaire a expiré. Recommencez.';
    } elseif ($announcement === null || (int) currentUser()['id_utilisateur'] === (int) $announcement['vendeur_id']) {
        $offerError = 'Cette annonce ne peut pas être signalée depuis ce compte.';
    } elseif ($reason === '' || strlen($reason) > 1000) {
        $offerError = 'Explique brièvement le motif du signalement.';
    } else {
        $reportStmt = $pdo->prepare(
            'INSERT INTO signalements (id_annonce, id_utilisateur, motif)
             VALUES (:annonce, :user, :motif) ON CONFLICT (id_annonce, id_utilisateur) DO NOTHING'
        );
        $reportStmt->execute([
            'annonce' => $announcementId,
            'user' => currentUser()['id_utilisateur'],
            'motif' => $reason,
        ]);
        $reportMessage = $reportStmt->rowCount() > 0 ? 'Merci, ton signalement a été transmis.' : 'Tu as déjà signalé cette annonce.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposer_avis') {
    if (currentUser() === null) {
        header('Location: /connexion.php?redirect=' . rawurlencode('/annonce.php?id=' . (int) $announcementId));
        exit;
    }
    $rating = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_INT);
    $comment = trim((string) ($_POST['commentaire'] ?? ''));
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $offerError = 'La session du formulaire a expiré. Recommencez.';
    } elseif ($announcement === null || $announcement['statut_vente'] !== 'vendue' || $rating === false || $rating < 1 || $rating > 5 || strlen($comment) > 1000) {
        $offerError = 'Cet avis ne peut pas être enregistré.';
    } else {
        $eligibility = $pdo->prepare(
            "SELECT 1 FROM offres
             WHERE id_annonce = :annonce AND id_utilisateur = :user AND statut = 'acceptee'"
        );
        $eligibility->execute(['annonce' => $announcementId, 'user' => currentUser()['id_utilisateur']]);
        if (!$eligibility->fetchColumn()) {
            $offerError = 'Seul l’acheteur dont l’offre a été acceptée peut évaluer cet échange.';
        } else {
            $insertReview = $pdo->prepare(
                'INSERT INTO avis (id_annonce, id_acheteur, note, commentaire)
                 VALUES (:annonce, :user, :note, :comment)
                 ON CONFLICT (id_annonce, id_acheteur) DO UPDATE
                 SET note = EXCLUDED.note, commentaire = EXCLUDED.commentaire, date_creation = CURRENT_TIMESTAMP'
            );
            $insertReview->execute([
                'annonce' => $announcementId,
                'user' => currentUser()['id_utilisateur'],
                'note' => $rating,
                'comment' => $comment,
            ]);
            $offerMessage = 'Merci pour ton avis sur cet échange.';
        }
    }
}

if ($announcement !== null && currentUser() !== null) {
    $reviewStmt->execute(['id' => $announcementId]);
    $reviews = $reviewStmt->fetchAll();
    $sellerRatingStmt->execute(['seller' => $announcement['vendeur_id']]);
    [$averageRating, $sellerReviewCount] = $sellerRatingStmt->fetch(PDO::FETCH_NUM);
    $myReviewStmt = $pdo->prepare('SELECT note, commentaire FROM avis WHERE id_annonce = :annonce AND id_acheteur = :user');
    $myReviewStmt->execute(['annonce' => $announcementId, 'user' => currentUser()['id_utilisateur']]);
    $myReviewRow = $myReviewStmt->fetch();
    $myReview = $myReviewRow !== false ? $myReviewRow : null;
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
                             AND o.statut = \'en_attente\'
               AND a.id_annonce = o.id_annonce
                               AND a.id_annonce = :annonce_id
                             AND a.statut_vente = \'disponible\'
               AND a.id_utilisateur = :user_id'
        );
        $updateStmt->execute([
            'statut' => $decision,
            'offre_id' => $offreId,
            'annonce_id' => $announcementId,
            'user_id' => currentUser()['id_utilisateur'],
        ]);
        if ($updateStmt->rowCount() > 0) {
            $offerMessage = $decision === 'acceptee' ? 'L’offre a été acceptée.' : 'L’offre a été refusée.';
            if ($decision === 'acceptee') {
                $closeOffers = $pdo->prepare(
                    "UPDATE offres SET statut = 'refusee', date_reponse = NOW()
                     WHERE id_annonce = (SELECT id_annonce FROM offres WHERE id_offre = :offer_id)
                       AND id_offre <> :offer_id2 AND statut = 'en_attente'"
                );
                $closeOffers->execute(['offer_id' => $offreId, 'offer_id2' => $offreId]);
            }
        } else {
            $offerError = 'Cette offre a déjà été traitée ou l’annonce n’est plus disponible.';
        }

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
    } elseif ($announcement['statut_vente'] !== 'disponible') {
        $offerError = 'Cette annonce n’est plus disponible.';
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
                    <?php if ($photos === []): ?><div class="detail-placeholder"><?= e(strtoupper(substr($announcement['categorie'] ?? 'ANN', 0, 3))) ?></div><?php else: ?><div class="detail-main-photo"><img id="main-announcement-photo" src="<?= e($photos[0]) ?>" alt="Photo de <?= e($announcement['titre']) ?>"></div><?php if (count($photos) > 1): ?><div class="detail-thumbnails"><?php foreach ($photos as $photo): ?><button type="button" class="detail-thumbnail" data-photo="<?= e($photo) ?>" aria-label="Afficher une autre photo"><img src="<?= e($photo) ?>" alt=""></button><?php endforeach; ?></div><?php endif; ?><?php endif; ?>
                </section>
                <section class="detail-info"><div class="listing-meta"><span><?= e($announcement['categorie'] ?? 'Autre') ?></span><span><?= e($announcement['etat'] ?? 'Disponible') ?> · <?= $announcement['statut_vente'] === 'vendue' ? 'Vendue' : 'Disponible' ?></span></div><h1><?= e($announcement['titre']) ?></h1><p class="detail-price"><?= number_format((float) $announcement['prix'], 2, ',', ' ') ?> €</p><p class="detail-description"><?= nl2br(e($announcement['description'])) ?></p><div class="seller-box"><div class="avatar small-avatar"><?php if (!empty($announcement['photo_url'])): ?><img class="avatar-photo" src="<?= e($announcement['photo_url']) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($announcement['prenom'], 0, 1) . substr($announcement['nom'], 0, 1))) ?><?php endif; ?></div><div><small>Publié par</small><strong><?= e($announcement['prenom'] . ' ' . $announcement['nom']) ?></strong><?php if ($averageRating !== null): ?><span class="seller-rating">★ <?= number_format((float) $averageRating, 1, ',', ' ') ?> · <?= (int) $sellerReviewCount ?> avis vendeur</span><?php else: ?><span class="seller-rating">Pas encore d’avis</span><?php endif; ?></div></div>

                    <?php if ($announcement['statut_vente'] === 'vendue'): ?><div class="notice-box">Cette annonce a été vendue.</div><?php endif; ?>
                    <?php if ($reportMessage !== null): ?><div class="success-message"><?= e($reportMessage) ?></div><?php endif; ?>
                    <?php if ($offerError !== null): ?><div class="form-errors"><p><?= e($offerError) ?></p></div><?php endif; ?>

                    <?php if (currentUser() !== null && (int) currentUser()['id_utilisateur'] !== (int) $announcement['vendeur_id']): ?>
                        <details class="report-panel"><summary>Signaler cette annonce</summary><form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="signaler_annonce"><label>Motif<textarea name="motif" rows="3" maxlength="1000" required></textarea></label><button class="outline-button" type="submit">Envoyer le signalement</button></form></details>
                    <?php endif; ?>

                    <?php if (currentUser() !== null && $announcement['statut_vente'] === 'vendue' && $myReview === null): ?>
                        <?php $reviewEligibility = $pdo->prepare("SELECT 1 FROM offres WHERE id_annonce = :annonce AND id_utilisateur = :user AND statut = 'acceptee'"); $reviewEligibility->execute(['annonce' => $announcementId, 'user' => currentUser()['id_utilisateur']]); ?>
                        <?php if ($reviewEligibility->fetchColumn()): ?><div class="offer-panel"><h3>Évaluer cet échange</h3><form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="deposer_avis"><label>Note<select name="note" required><option value="">Choisir une note</option><?php for ($score = 5; $score >= 1; $score--): ?><option value="<?= $score ?>"><?= $score ?> / 5</option><?php endfor; ?></select></label><label>Commentaire<textarea name="commentaire" rows="3" maxlength="1000"></textarea></label><button class="primary-button" type="submit">Publier mon avis</button></form></div><?php endif; ?>
                    <?php endif; ?>

                    <?php if ($reviews !== []): ?><section class="review-list"><h2>Avis sur cet échange</h2><?php foreach ($reviews as $review): ?><article class="review-item"><strong><?= e($review['prenom']) ?> · <?= (int) $review['note'] ?>/5</strong><?php if ($review['commentaire'] !== ''): ?><p><?= e($review['commentaire']) ?></p><?php endif; ?><small><?= e(date('d/m/Y', strtotime($review['date_creation']))) ?></small></article><?php endforeach; ?></section><?php endif; ?>

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
                    <?php elseif ($announcement['statut_vente'] !== 'vendue'): ?>
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
    <script>
    document.querySelectorAll('.detail-thumbnail').forEach(function (button) {
        button.addEventListener('click', function () {
            var mainPhoto = document.getElementById('main-announcement-photo');
            mainPhoto.src = button.dataset.photo;
        });
    });
    </script>
</body>
</html>
