<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();
$stmt = $pdo->prepare(
    'SELECT id_annonce, titre, prix, etat, date_publication
     FROM annonces
     WHERE id_utilisateur = :user_id
     ORDER BY date_publication DESC'
);
$stmt->execute(['user_id' => $user['id_utilisateur']]);
$annonces = $stmt->fetchAll();
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
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Mon profil | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/">Explorer</a><a class="nav-link active" href="/profil.php">Mon profil</a><a class="logout-link" href="/deconnexion.php">Se déconnecter</a></nav></header>
    <main class="profile-layout">
        <?php if (isset($_GET['created'])): ?><div class="success-message">Ton compte a bien été créé.</div><?php elseif (isset($_GET['published'])): ?><div class="success-message">Ton annonce a bien été publiée.</div><?php endif; ?>
        <section class="profile-header"><div class="avatar"><?= e(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1))) ?></div><div><p class="eyebrow">Mon espace</p><h1><?= e($user['prenom'] . ' ' . $user['nom']) ?></h1><p class="profile-email"><?= e($user['email']) ?></p></div><a class="primary-button compact-button" href="/creer-annonce.php">+ Déposer une annonce</a></section>
        <section class="profile-content"><div class="section-heading"><div><p class="eyebrow">Ton activité</p><h2>Mes annonces</h2></div><span class="section-count"><?= count($annonces) ?> annonce<?= count($annonces) > 1 ? 's' : '' ?></span></div>
            <?php if ($annonces === []): ?><div class="empty-state"><span class="empty-icon">○</span><h3>Tu n'as encore rien publié</h3><p>Commence par proposer un objet ou un service à ton campus.</p><a class="outline-button" href="/creer-annonce.php">Créer une annonce</a></div>
            <?php else: ?><div class="profile-list"><?php foreach ($annonces as $annonce): ?><article class="profile-list-item"><div><span class="listing-meta"><?= e($annonce['etat'] ?? 'Disponible') ?></span><h3><?= e($annonce['titre']) ?></h3><small>Publiée le <?= e(date('d/m/Y', strtotime($annonce['date_publication']))) ?></small></div><strong><?= number_format((float) $annonce['prix'], 2, ',', ' ') ?> €</strong></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="profile-content message-section"><div class="section-heading"><div><p class="eyebrow">Échanger avec les intéressés</p><h2>Messages</h2></div><span class="section-count"><?= count($conversations) ?> conversation<?= count($conversations) > 1 ? 's' : '' ?></span></div>
            <?php if ($conversations === []): ?><div class="empty-state compact-empty"><span class="empty-icon">○</span><h3>Aucun message pour le moment</h3><p>Les personnes intéressées par tes annonces apparaîtront ici.</p></div>
            <?php else: ?><div class="conversation-list"><?php foreach ($conversations as $conversation): ?><a class="conversation-item <?= (int) $conversation['non_lus'] > 0 ? 'conversation-unread' : '' ?>" href="/chat.php?conversation=<?= (int) $conversation['id_conversation'] ?>"><div class="avatar conversation-avatar"><?= e(strtoupper(substr($conversation['interlocuteur_prenom'], 0, 1) . substr($conversation['interlocuteur_nom'], 0, 1))) ?></div><div class="conversation-copy"><div class="conversation-topline"><strong><?= e($conversation['interlocuteur_prenom'] . ' ' . $conversation['interlocuteur_nom']) ?></strong><small><?= $conversation['date_envoi'] ? e(date('d/m/Y H:i', strtotime($conversation['date_envoi']))) : '' ?></small></div><h3><?= e($conversation['titre']) ?></h3><p><?= e($conversation['dernier_message'] ?? 'Nouvelle conversation') ?></p></div><?php if ((int) $conversation['non_lus'] > 0): ?><span class="unread-badge"><?= (int) $conversation['non_lus'] ?></span><?php endif; ?></a><?php endforeach; ?></div><?php endif; ?>
        </section>
    </main>
</body>
</html>
