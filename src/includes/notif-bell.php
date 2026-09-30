<?php
$__notifUser = currentUser();
if ($__notifUser === null) {
    return;
}
$__notifCount = 0;
try {
    $__notifStmt = getConnexion()->prepare(
        'SELECT COUNT(*) FROM messages m
         JOIN participants p ON p.id_conversation = m.id_conversation AND p.id_utilisateur = :uid
         WHERE m.id_utilisateur <> :uid2 AND m.lu = FALSE'
    );
    $__notifStmt->execute(['uid' => $__notifUser['id_utilisateur'], 'uid2' => $__notifUser['id_utilisateur']]);
    $__notifCount = (int) $__notifStmt->fetchColumn();
} catch (Throwable $exception) {
    $__notifCount = 0;
}
?>
<a class="notif-bell" href="/profil.php#messages" aria-label="Notifications">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    <?php if ($__notifCount > 0): ?><span class="notif-badge"><?= $__notifCount > 9 ? '9+' : $__notifCount ?></span><?php endif; ?>
</a>
