<?php
$__menuUser = currentUser();
if ($__menuUser === null) {
    return;
}
?>
<div class="user-menu">
    <button type="button" class="user-menu-trigger" id="user-menu-trigger" aria-haspopup="true" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
        <span><?= e($__menuUser['prenom']) ?></span>
    </button>
    <div class="user-menu-dropdown" id="user-menu-dropdown" hidden>
        <a href="/profil.php">Mon compte</a>
        <a href="/favoris.php">Mes favoris</a>
        <a href="/panier.php">Panier</a>
        <a href="/parametres.php">Paramètres</a>
        <hr>
        <a class="logout-link" href="/deconnexion.php">Se déconnecter</a>
    </div>
</div>
<script>
(function () {
    var trigger = document.getElementById('user-menu-trigger');
    var dropdown = document.getElementById('user-menu-dropdown');
    if (!trigger || !dropdown || trigger.dataset.bound) return;
    trigger.dataset.bound = '1';
    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = !dropdown.hidden;
        dropdown.hidden = isOpen;
        trigger.setAttribute('aria-expanded', String(!isOpen));
    });
    document.addEventListener('click', function (e) {
        if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== trigger) {
            dropdown.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
