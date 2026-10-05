<?php
$__notifUser = currentUser();
if ($__notifUser === null) {
    return;
}
$__notifCount = 0;
$__pendingOffers = 0;
try {
    $__notifStmt = getConnexion()->prepare(
        "SELECT
            (SELECT COUNT(*) FROM messages m
             JOIN participants p ON p.id_conversation = m.id_conversation AND p.id_utilisateur = :uid
             WHERE m.id_utilisateur <> :uid2 AND m.lu = FALSE) AS unread_messages,
            (SELECT COUNT(*) FROM offres o
             JOIN annonces a ON a.id_annonce = o.id_annonce
             WHERE a.id_utilisateur = :owner AND o.statut = 'en_attente') AS pending_offers"
    );
    $__notifStmt->execute([
        'uid' => $__notifUser['id_utilisateur'],
        'uid2' => $__notifUser['id_utilisateur'],
        'owner' => $__notifUser['id_utilisateur'],
    ]);
    $__notifCounts = $__notifStmt->fetch(PDO::FETCH_ASSOC);
    $__pendingOffers = (int) $__notifCounts['pending_offers'];
    $__notifCount = (int) $__notifCounts['unread_messages'] + $__pendingOffers;
} catch (Throwable $exception) {
    $__notifCount = 0;
}
?>
<a class="notif-bell" href="/profil.php#<?= $__pendingOffers > 0 ? 'offres' : 'messages' ?>" aria-label="Notifications">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    <?php if ($__notifCount > 0): ?><span class="notif-badge"><?= $__notifCount > 9 ? '9+' : $__notifCount ?></span><?php endif; ?>
</a>
