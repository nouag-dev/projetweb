<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repondre_offre') {
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['profile_error'] = 'Le jeton de sécurité est invalide.';
    } else {
        $offreId = filter_input(INPUT_POST, 'offre_id', FILTER_VALIDATE_INT);
        $decision = trim((string) ($_POST['decision'] ?? ''));
        if ($offreId === null || $offreId === false || !in_array($decision, ['acceptee', 'refusee'], true)) {
            $_SESSION['profile_error'] = 'Réponse invalide.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE offres o
                 SET statut = :statut,
                     date_reponse = NOW()
                 FROM annonces a
                 WHERE o.id_offre = :offre_id
                                     AND o.statut = \'en_attente\'
                   AND a.id_annonce = o.id_annonce
                                     AND a.statut_vente = \'disponible\'
                   AND a.id_utilisateur = :user_id'
            );
            $stmt->execute([
                'statut' => $decision,
                'offre_id' => $offreId,
                'user_id' => $user['id_utilisateur'],
            ]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['profile_success'] = $decision === 'acceptee' ? 'L’offre a été acceptée.' : 'L’offre a été refusée.';
                if ($decision === 'acceptee') {
                    $closeOffers = $pdo->prepare(
                        "UPDATE offres SET statut = 'refusee', date_reponse = NOW()
                         WHERE id_annonce = (SELECT id_annonce FROM offres WHERE id_offre = :offer_id)
                           AND id_offre <> :offer_id2 AND statut = 'en_attente'"
                    );
                    $closeOffers->execute(['offer_id' => $offreId, 'offer_id2' => $offreId]);
                }
            } else {
                $_SESSION['profile_error'] = 'Cette offre a déjà été traitée ou l’annonce n’est plus disponible.';
            }
        }
    }
    header('Location: /profil.php#offres');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['changer_statut_annonce', 'retirer_annonce'], true)) {
    if (!checkCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['profile_error'] = 'La session du formulaire a expiré.';
    } else {
        $listingId = filter_input(INPUT_POST, 'id_annonce', FILTER_VALIDATE_INT);
        if ($listingId === false || $listingId === null) {
            $_SESSION['profile_error'] = 'Annonce invalide.';
        } elseif (($_POST['action'] ?? '') === 'retirer_annonce') {
            $removeStmt = $pdo->prepare(
                "UPDATE annonces SET statut_vente = 'retiree'
                 WHERE id_annonce = :id AND id_utilisateur = :user_id"
            );
            $removeStmt->execute(['id' => $listingId, 'user_id' => $user['id_utilisateur']]);
            $_SESSION[$removeStmt->rowCount() > 0 ? 'profile_success' : 'profile_error'] = $removeStmt->rowCount() > 0
                ? 'L’annonce a été retirée.'
                : 'Cette annonce ne peut pas être modifiée.';
        } else {
            $newStatus = (string) ($_POST['statut_vente'] ?? '');
            if (!in_array($newStatus, ['disponible', 'vendue'], true)) {
                $_SESSION['profile_error'] = 'Statut invalide.';
            } else {
                $statusStmt = $pdo->prepare(
                    'UPDATE annonces SET statut_vente = :status
                     WHERE id_annonce = :id AND id_utilisateur = :user_id AND statut_vente <> \'retiree\''
                );
                $statusStmt->execute([
                    'status' => $newStatus,
                    'id' => $listingId,
                    'user_id' => $user['id_utilisateur'],
                ]);
                if ($newStatus === 'vendue' && $statusStmt->rowCount() > 0) {
                    $closeOffers = $pdo->prepare(
                        "UPDATE offres SET statut = 'refusee', date_reponse = NOW()
                         WHERE id_annonce = :id AND statut = 'en_attente'"
                    );
                    $closeOffers->execute(['id' => $listingId]);
                }
                $_SESSION[$statusStmt->rowCount() > 0 ? 'profile_success' : 'profile_error'] = $statusStmt->rowCount() > 0
                    ? ($newStatus === 'vendue' ? 'L’annonce est marquée comme vendue.' : 'L’annonce est de nouveau disponible.')
                    : 'Cette annonce ne peut pas être modifiée.';
            }
        }
    }
    header('Location: /profil.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id_annonce, titre, prix, etat, date_publication, statut_vente
     FROM annonces
     WHERE id_utilisateur = :user_id
       AND statut_vente <> \'retiree\'
     ORDER BY date_publication DESC'
);
$stmt->execute(['user_id' => $user['id_utilisateur']]);
$annonces = $stmt->fetchAll();
$offresStmt = $pdo->prepare(
    'SELECT o.id_offre, o.id_annonce, o.montant, o.message, o.statut, o.date_creation,
            a.titre AS annonce_titre,
            u.prenom, u.nom
     FROM offres o
     JOIN annonces a ON a.id_annonce = o.id_annonce
     JOIN utilisateurs u ON u.id_utilisateur = o.id_utilisateur
     WHERE a.id_utilisateur = :user_id
     ORDER BY o.date_creation DESC'
);
$offresStmt->execute(['user_id' => $user['id_utilisateur']]);
$offresRecues = $offresStmt->fetchAll();
$conversationStmt = $pdo->prepare(
    'SELECT c.id_conversation, c.id_annonce, a.titre,
            other.prenom AS interlocuteur_prenom, other.nom AS interlocuteur_nom,
            latest.contenu AS dernier_message, latest.date_envoi,
            (SELECT COUNT(*) FROM messages unread
             WHERE unread.id_conversation = c.id_conversation
               AND unread.id_utilisateur <> :user_id
               AND unread.lu = FALSE) AS non_lus
     FROM conversations c
     JOIN annonces a ON a.id_annonce = c.id_annonce
     JOIN participants mine ON mine.id_conversation = c.id_conversation
                            AND mine.id_utilisateur = :user_id
     JOIN participants other_participant ON other_participant.id_conversation = c.id_conversation
                                         AND other_participant.id_utilisateur <> :user_id
     JOIN utilisateurs other ON other.id_utilisateur = other_participant.id_utilisateur
     LEFT JOIN LATERAL (
         SELECT contenu, date_envoi
         FROM messages
         WHERE id_conversation = c.id_conversation
         ORDER BY date_envoi DESC, id_message DESC
         LIMIT 1
     ) latest ON TRUE
     ORDER BY latest.date_envoi DESC NULLS LAST, c.date_creation DESC'
);
$conversationStmt->execute(['user_id' => $user['id_utilisateur']]);
$conversations = $conversationStmt->fetchAll();
$dashboardStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM annonces
         WHERE id_utilisateur = :active_listings_user AND statut_vente = 'disponible') AS annonces_actives,
        (SELECT COUNT(*) FROM annonces
         WHERE id_utilisateur = :sold_listings_user AND statut_vente = 'vendue') AS annonces_vendues,
        (SELECT COUNT(*) FROM offres o
         JOIN annonces a ON a.id_annonce = o.id_annonce
         WHERE a.id_utilisateur = :received_offers_user AND o.statut = 'en_attente') AS offres_recues_attente,
        (SELECT COUNT(*) FROM offres
         WHERE id_utilisateur = :sent_offers_user AND statut = 'en_attente') AS offres_envoyees_attente,
        (SELECT COUNT(*) FROM likes
         WHERE id_utilisateur = :favorites_user AND statut = 'like') AS favoris,
        (SELECT COUNT(*) FROM messages m
         JOIN participants p ON p.id_conversation = m.id_conversation
         WHERE p.id_utilisateur = :unread_recipient
           AND m.id_utilisateur <> :unread_sender
           AND m.lu = FALSE) AS messages_non_lus,
        (SELECT AVG(av.note)
         FROM avis av
         JOIN annonces a ON a.id_annonce = av.id_annonce
         WHERE a.id_utilisateur = :rating_user) AS note_moyenne,
        (SELECT COUNT(*)
         FROM avis av
         JOIN annonces a ON a.id_annonce = av.id_annonce
         WHERE a.id_utilisateur = :review_count_user) AS nombre_avis"
);
$dashboardStmt->execute([
    'active_listings_user' => $user['id_utilisateur'],
    'sold_listings_user' => $user['id_utilisateur'],
    'received_offers_user' => $user['id_utilisateur'],
    'sent_offers_user' => $user['id_utilisateur'],
    'favorites_user' => $user['id_utilisateur'],
    'unread_recipient' => $user['id_utilisateur'],
    'unread_sender' => $user['id_utilisateur'],
    'rating_user' => $user['id_utilisateur'],
    'review_count_user' => $user['id_utilisateur'],
]);
$dashboard = $dashboardStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Mon profil | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/">Accueil</a><?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?></nav></header>
    <main class="profile-layout">
        <?php if (isset($_GET['created'])): ?><div class="success-message">Ton compte a bien été créé.</div><?php elseif (isset($_GET['published'])): ?><div class="success-message">Ton annonce a bien été publiée.</div><?php elseif (isset($_GET['updated'])): ?><div class="success-message">Ton annonce a bien été mise à jour.</div><?php endif; ?>
        <?php if (!empty($_SESSION['profile_success'])): ?><div class="success-message"><?= e($_SESSION['profile_success']) ?></div><?php unset($_SESSION['profile_success']); endif; ?>
        <?php if (!empty($_SESSION['profile_error'])): ?><div class="form-errors"><p><?= e($_SESSION['profile_error']) ?></p></div><?php unset($_SESSION['profile_error']); endif; ?>
        <section class="profile-header"><div class="avatar"><?php if (!empty($user['photo_url'])): ?><img class="avatar-photo" src="<?= e($user['photo_url']) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1))) ?><?php endif; ?></div><div><p class="eyebrow">Mon espace</p><h1><?= e($user['prenom'] . ' ' . $user['nom']) ?></h1><p class="profile-email"><?= e($user['email']) ?></p></div><a class="primary-button compact-button" href="/creer-annonce.php">+ Déposer une annonce</a></section>
        <section class="profile-content dashboard-section" id="dashboard" aria-labelledby="dashboard-title">
            <div class="section-heading"><div><p class="eyebrow">Vue d’ensemble</p><h2 id="dashboard-title">Mon activité</h2></div><span class="section-count">Tes indicateurs personnels</span></div>
            <div class="dashboard-grid">
                <a class="dashboard-card" href="#annonces"><span class="dashboard-label">Annonces en ligne</span><strong><?= (int) $dashboard['annonces_actives'] ?></strong><span class="dashboard-detail">Disponibles à la vente</span></a>
                <a class="dashboard-card" href="#annonces"><span class="dashboard-label">Annonces vendues</span><strong><?= (int) $dashboard['annonces_vendues'] ?></strong><span class="dashboard-detail">Échanges terminés</span></a>
                <a class="dashboard-card <?= (int) $dashboard['offres_recues_attente'] > 0 ? 'dashboard-card-highlight' : '' ?>" href="#offres"><span class="dashboard-label">Offres à traiter</span><strong><?= (int) $dashboard['offres_recues_attente'] ?></strong><span class="dashboard-detail">Propositions reçues en attente</span></a>
                <a class="dashboard-card" href="/panier.php"><span class="dashboard-label">Mes offres en attente</span><strong><?= (int) $dashboard['offres_envoyees_attente'] ?></strong><span class="dashboard-detail">Propositions envoyées</span></a>
                <a class="dashboard-card" href="/favoris.php"><span class="dashboard-label">Mes favoris</span><strong><?= (int) $dashboard['favoris'] ?></strong><span class="dashboard-detail">Annonces enregistrées</span></a>
                <a class="dashboard-card <?= (int) $dashboard['messages_non_lus'] > 0 ? 'dashboard-card-highlight' : '' ?>" href="#messages"><span class="dashboard-label">Messages non lus</span><strong><?= (int) $dashboard['messages_non_lus'] ?></strong><span class="dashboard-detail">À consulter dans tes conversations</span></a>
            </div>
            <p class="dashboard-rating"><?php if ($dashboard['note_moyenne'] !== null): ?>Note moyenne de tes annonces : <strong><?= number_format((float) $dashboard['note_moyenne'], 1, ',', ' ') ?>/5</strong> · <?= (int) $dashboard['nombre_avis'] ?> avis<?php else: ?>Pas encore d’avis sur tes annonces.<?php endif; ?></p>
        </section>
        <section id="annonces" class="profile-content"><div class="section-heading"><div><p class="eyebrow">Ton activité</p><h2>Mes annonces</h2></div><span class="section-count"><?= count($annonces) ?> annonce<?= count($annonces) > 1 ? 's' : '' ?></span></div>
            <?php if ($annonces === []): ?><div class="empty-state"><span class="empty-icon">○</span><h3>Tu n'as encore rien publié</h3><p>Commence par proposer un objet ou un service à ton campus.</p><a class="outline-button" href="/creer-annonce.php">Créer une annonce</a></div>
            <?php else: ?><div class="profile-list"><?php foreach ($annonces as $annonce): ?><article class="profile-list-item"><div class="profile-list-copy"><span class="listing-meta"><?= e($annonce['etat'] ?? 'Disponible') ?> · <?= $annonce['statut_vente'] === 'vendue' ? 'Vendue' : 'En ligne' ?></span><h3><?= e($annonce['titre']) ?></h3><small>Publiée le <?= e(date('d/m/Y', strtotime($annonce['date_publication']))) ?></small></div><div class="profile-list-management"><strong><?= number_format((float) $annonce['prix'], 2, ',', ' ') ?> €</strong><div class="offer-actions"><a class="outline-button compact-inline" href="/annonce.php?id=<?= (int) $annonce['id_annonce'] ?>">Voir</a><?php if ($annonce['statut_vente'] !== 'vendue'): ?><a class="outline-button compact-inline" href="/modifier-annonce.php?id=<?= (int) $annonce['id_annonce'] ?>">Modifier</a><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="changer_statut_annonce"><input type="hidden" name="id_annonce" value="<?= (int) $annonce['id_annonce'] ?>"><input type="hidden" name="statut_vente" value="<?= $annonce['statut_vente'] === 'vendue' ? 'disponible' : 'vendue' ?>"><button class="outline-button compact-inline" type="submit"><?= $annonce['statut_vente'] === 'vendue' ? 'Remettre en ligne' : 'Marquer vendue' ?></button></form><form method="post" onsubmit="return confirm('Retirer cette annonce ?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="retirer_annonce"><input type="hidden" name="id_annonce" value="<?= (int) $annonce['id_annonce'] ?>"><button class="outline-button compact-inline" type="submit">Retirer</button></form></div></div></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <div class="profile-activity-grid">
            <section id="offres" class="profile-content activity-panel offer-section" aria-labelledby="offers-title">
                <div class="activity-panel-heading">
                    <div><p class="eyebrow">Négociations</p><h2 id="offers-title">Offres reçues</h2><p>Les propositions faites sur tes annonces.</p></div>
                    <span class="activity-count <?= (int) $dashboard['offres_recues_attente'] > 0 ? 'activity-count-pending' : '' ?>">
                        <?= (int) $dashboard['offres_recues_attente'] ?> en attente
                    </span>
                </div>
                <?php if ($offresRecues === []): ?>
                    <div class="activity-empty"><span class="activity-empty-icon" aria-hidden="true">↗</span><h3>Pas encore d’offre</h3><p>Les propositions des acheteurs apparaîtront ici.</p></div>
                <?php else: ?>
                    <div class="offer-list activity-card-list">
                        <?php foreach ($offresRecues as $offre): ?>
                            <article class="received-offer-card <?= $offre['statut'] === 'en_attente' ? 'received-offer-pending' : '' ?>">
                                <div class="received-offer-top">
                                    <div class="conversation-person">
                                        <div class="avatar offer-avatar"><?= e(strtoupper(substr($offre['prenom'], 0, 1) . substr($offre['nom'], 0, 1))) ?></div>
                                        <div><strong><?= e($offre['prenom'] . ' ' . $offre['nom']) ?></strong><small>Offre envoyée le <?= e(date('d/m/Y', strtotime($offre['date_creation']))) ?></small></div>
                                    </div>
                                    <strong class="received-offer-amount"><?= number_format((float) $offre['montant'], 2, ',', ' ') ?> €</strong>
                                </div>
                                <a class="received-offer-listing" href="/annonce.php?id=<?= (int) $offre['id_annonce'] ?>"><?= e($offre['annonce_titre']) ?></a>
                                <?php if (!empty($offre['message'])): ?><p class="received-offer-message"><?= e($offre['message']) ?></p><?php endif; ?>
                                <?php if ($offre['statut'] === 'en_attente'): ?>
                                    <form method="post" class="offer-decision">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="repondre_offre">
                                        <input type="hidden" name="offre_id" value="<?= (int) $offre['id_offre'] ?>">
                                        <div class="offer-actions">
                                            <button class="primary-button compact-inline" type="submit" name="decision" value="acceptee">Accepter l’offre</button>
                                            <button class="outline-button compact-inline" type="submit" name="decision" value="refusee">Refuser</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <span class="offer-status offer-status-<?= e($offre['statut']) ?>"><?= $offre['statut'] === 'acceptee' ? 'Acceptée' : 'Refusée' ?></span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section id="messages" class="profile-content activity-panel message-section" aria-labelledby="messages-title">
                <div class="activity-panel-heading">
                    <div><p class="eyebrow">Discussions</p><h2 id="messages-title">Messages</h2><p>Reprends tes conversations avec les autres étudiants.</p></div>
                    <span class="activity-count <?= (int) $dashboard['messages_non_lus'] > 0 ? 'activity-count-pending' : '' ?>">
                        <?= (int) $dashboard['messages_non_lus'] ?> non lu<?= (int) $dashboard['messages_non_lus'] === 1 ? '' : 's' ?>
                    </span>
                </div>
                <?php if ($conversations === []): ?>
                    <div class="activity-empty"><span class="activity-empty-icon" aria-hidden="true">…</span><h3>Aucune conversation</h3><p>Quand un étudiant t’écrira au sujet d’une annonce, la discussion apparaîtra ici.</p></div>
                <?php else: ?>
                    <div class="conversation-list activity-card-list">
                        <?php foreach ($conversations as $conversation): ?>
                            <a class="conversation-item <?= (int) $conversation['non_lus'] > 0 ? 'conversation-unread' : '' ?>" href="/chat.php?conversation=<?= (int) $conversation['id_conversation'] ?>">
                                <div class="avatar conversation-avatar"><?= e(strtoupper(substr($conversation['interlocuteur_prenom'], 0, 1) . substr($conversation['interlocuteur_nom'], 0, 1))) ?></div>
                                <div class="conversation-copy">
                                    <div class="conversation-topline"><strong><?= e($conversation['interlocuteur_prenom'] . ' ' . $conversation['interlocuteur_nom']) ?></strong><small><?= $conversation['date_envoi'] ? e(date('d/m/Y H:i', strtotime($conversation['date_envoi']))) : '' ?></small></div>
                                    <h3><?= e($conversation['titre']) ?></h3>
                                    <p><?= e($conversation['dernier_message'] ?? 'Nouvelle conversation') ?></p>
                                </div>
                                <?php if ((int) $conversation['non_lus'] > 0): ?><span class="unread-badge"><?= (int) $conversation['non_lus'] ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
