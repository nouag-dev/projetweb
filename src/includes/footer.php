<?php $__footerUser = currentUser(); ?>
<footer class="site-footer-full">
    <div class="footer-columns">
        <div class="footer-col">
            <span class="footer-brand">campus<span class="brand-dot">.</span></span>
            <p>La marketplace 100% étudiante : annonces, découverte façon swipe et messagerie intégrée.</p>
        </div>
        <div class="footer-col">
            <h3>Explorer</h3>
            <a href="/">Accueil</a>
            <a href="/decouvrir.php">Explorer</a>
            <a href="/#categories">Catégories</a>
            <a href="/favoris.php">Mes favoris</a>
        </div>
        <div class="footer-col">
            <h3>Mon compte</h3>
            <?php if ($__footerUser === null): ?>
                <a href="/inscription.php">Créer un compte</a>
                <a href="/connexion.php">Se connecter</a>
            <?php else: ?>
                <a href="/profil.php">Mon profil</a>
                <a href="/parametres.php">Paramètres</a>
                <a href="/creer-annonce.php">Déposer une annonce</a>
            <?php endif; ?>
        </div>
        <div class="footer-col">
            <h3>À propos</h3>
            <a href="/a-propos.php">Qui sommes-nous ?</a>
            <a href="/cgu.php">Conditions d'utilisation</a>
            <a href="/cgu.php#confidentialite">Confidentialité</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© <?= date('Y') ?> Campus — Projet étudiant</span>
    </div>
</footer>
