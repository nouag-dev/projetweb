<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$conversationId = filter_input(INPUT_GET, 'conversation', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'conversation', FILTER_VALIDATE_INT);
$announcementId = filter_input(INPUT_GET, 'annonce', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'annonce', FILTER_VALIDATE_INT);
$user = currentUser();
$pdo = getConnexion();
$error = null;
$announcement = null;
$interlocutor = null;

if ($conversationId) {
    $conversationStmt = $pdo->prepare(
        'SELECT c.id_conversation, a.id_annonce, a.titre, a.id_utilisateur AS vendeur_id,
            u.prenom, u.nom, other_user.prenom AS interlocuteur_prenom,
            other_user.nom AS interlocuteur_nom
         FROM conversations c
         JOIN annonces a ON a.id_annonce = c.id_annonce
         JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
         JOIN participants access ON access.id_conversation = c.id_conversation
                                 AND access.id_utilisateur = :user_id
         JOIN participants other_access ON other_access.id_conversation = c.id_conversation
                                       AND other_access.id_utilisateur <> :user_id
         JOIN utilisateurs other_user ON other_user.id_utilisateur = other_access.id_utilisateur
         WHERE c.id_conversation = :conversation'
    );
    $conversationStmt->execute([
        'conversation' => $conversationId,
        'user_id' => $user['id_utilisateur'],
    ]);
    $conversation = $conversationStmt->fetch();
    if ($conversation !== false) {
        $announcementId = (int) $conversation['id_annonce'];
        $announcement = $conversation;
        $interlocutor = [
            'prenom' => $conversation['interlocuteur_prenom'],
            'nom' => $conversation['interlocuteur_nom'],
        ];
    }
} elseif ($announcementId) {
    $stmt = $pdo->prepare(
        'SELECT a.id_annonce, a.titre, a.id_utilisateur AS vendeur_id, u.prenom, u.nom
         FROM annonces a JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
         WHERE a.id_annonce = :id'
    );
    $stmt->execute(['id' => $announcementId]);
    $announcement = $stmt->fetch();
    if ($announcement !== false) {
        $interlocutor = ['prenom' => $announcement['prenom'], 'nom' => $announcement['nom']];
    }
}

if ($announcement === false || $announcement === null) {
    http_response_code(404);
    $error = 'Cette annonce est introuvable.';
} else {
    $conversationStmt = $pdo->prepare(
        'SELECT c.id_conversation
         FROM conversations c
         JOIN participants mine ON mine.id_conversation = c.id_conversation AND mine.id_utilisateur = :mine
         JOIN participants seller ON seller.id_conversation = c.id_conversation AND seller.id_utilisateur = :seller
         WHERE c.id_annonce = :annonce
         LIMIT 1'
    );
    $conversationStmt->execute([
        'mine' => $user['id_utilisateur'],
        'seller' => $announcement['vendeur_id'],
        'annonce' => $announcementId,
    ]);
    $conversationId = $conversationStmt->fetchColumn() ?: null;
    if ((int) $announcement['vendeur_id'] === (int) $user['id_utilisateur'] && $conversationId === null) {
        $error = 'Aucune conversation n’a encore été ouverte pour cette annonce.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
        $message = trim($_POST['message'] ?? '');
        if (!checkCsrf($_POST['csrf_token'] ?? null)) {
            $error = 'La session du formulaire a expiré. Recommencez.';
        } elseif ($message === '' || strlen($message) > 2000) {
            $error = 'Le message doit contenir entre 1 et 2 000 caractères.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($conversationId === null) {
                    $newConversation = $pdo->prepare('INSERT INTO conversations (id_annonce) VALUES (:annonce) RETURNING id_conversation');
                    $newConversation->execute(['annonce' => $announcementId]);
                    $conversationId = $newConversation->fetchColumn();
                    $participant = $pdo->prepare('INSERT INTO participants (id_conversation, id_utilisateur) VALUES (:conversation, :user)');
                    $participant->execute(['conversation' => $conversationId, 'user' => $user['id_utilisateur']]);
                    $participant->execute(['conversation' => $conversationId, 'user' => $announcement['vendeur_id']]);
                }
                $messageStmt = $pdo->prepare('INSERT INTO messages (contenu, id_conversation, id_utilisateur) VALUES (:contenu, :conversation, :user)');
                $messageStmt->execute([
                    'contenu' => $message,
                    'conversation' => $conversationId,
                    'user' => $user['id_utilisateur'],
                ]);
                $pdo->commit();
                header('Location: /chat.php?conversation=' . (int) $conversationId);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Impossible d’envoyer le message pour le moment.';
            }
        }
    }
}

$messages = [];
if ($conversationId !== null) {
    $messagesStmt = $pdo->prepare(
        'SELECT m.contenu, m.date_envoi, m.id_utilisateur, u.prenom
         FROM messages m JOIN utilisateurs u ON u.id_utilisateur = m.id_utilisateur
         WHERE m.id_conversation = :conversation ORDER BY m.date_envoi, m.id_message'
    );
    $messagesStmt->execute(['conversation' => $conversationId]);
    $messages = $messagesStmt->fetchAll();
    $readStmt = $pdo->prepare(
        'UPDATE messages SET lu = TRUE
         WHERE id_conversation = :conversation AND id_utilisateur <> :user_id'
    );
    $readStmt->execute([
        'conversation' => $conversationId,
        'user_id' => $user['id_utilisateur'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Conversation | Campus</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
    <header class="site-header"><a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a><nav class="account-nav"><a href="/">Accueil</a><a href="/profil.php">Mon profil</a><a class="logout-link" href="/deconnexion.php">Se déconnecter</a></nav></header>
    <main class="chat-page">
        <?php if ($error !== null && $announcement === null): ?><div class="empty-state"><h3><?= e($error) ?></h3><a class="outline-button" href="/">Retour aux annonces</a></div>
        <?php elseif ($error !== null && (int) $announcement['vendeur_id'] === (int) $user['id_utilisateur']): ?><div class="empty-state"><h3><?= e($error) ?></h3><a class="outline-button" href="/annonce.php?id=<?= (int) $announcementId ?>">Retour à l'annonce</a></div>
        <?php else: ?><a class="back-link" href="/annonce.php?id=<?= (int) $announcementId ?>">← Retour à l'annonce</a><section class="chat-shell"><header class="chat-header"><div class="avatar small-avatar"><?= e(strtoupper(substr($announcement['prenom'], 0, 1) . substr($announcement['nom'], 0, 1))) ?></div><div><p class="eyebrow">Conversation à propos de</p><h1><?= e($announcement['titre']) ?></h1><p>avec <?= e($announcement['prenom'] . ' ' . $announcement['nom']) ?></p></div></header><div class="messages-list"><?php if ($messages === []): ?><div class="chat-empty">Écrivez un premier message au vendeur.</div><?php else: ?><?php foreach ($messages as $message): ?><div class="message <?= (int) $message['id_utilisateur'] === (int) $user['id_utilisateur'] ? 'message-own' : 'message-other' ?>"><p><?= nl2br(e($message['contenu'])) ?></p><small><?= e($message['prenom']) ?> · <?= e(date('d/m/Y H:i', strtotime($message['date_envoi']))) ?></small></div><?php endforeach; ?><?php endif; ?></div><?php if ($error !== null): ?><div class="form-errors"><p><?= e($error) ?></p></div><?php endif; ?><form method="post" class="chat-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="annonce" value="<?= (int) $announcementId ?>"><textarea name="message" rows="3" maxlength="2000" placeholder="Écrivez votre message..." required></textarea><button class="primary-button" type="submit">Envoyer</button></form></section><?php endif; ?>
    </main>
</body>
</html>
