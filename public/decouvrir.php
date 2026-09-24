<?php
require_once __DIR__ . '/../src/config/auth.php';
requireAuth();

$user = currentUser();
$pdo = getConnexion();

$stmt = $pdo->prepare(
    <<<'SQL'
    SELECT
        a.id_annonce,
        a.titre,
        a.prix,
        a.etat,
        c.nom AS categorie,
        u.prenom,
        (SELECT p.url FROM photos p WHERE p.id_annonce = a.id_annonce ORDER BY p.ordre LIMIT 1) AS photo_url
    FROM annonces a
    JOIN utilisateurs u ON u.id_utilisateur = a.id_utilisateur
    LEFT JOIN categories c ON c.id_categorie = a.id_categorie
    WHERE a.id_utilisateur <> :user_id
      AND NOT EXISTS (
          SELECT 1 FROM likes l
          WHERE l.id_annonce = a.id_annonce AND l.id_utilisateur = :user_id2
      )
    ORDER BY a.date_publication DESC
    LIMIT 30
    SQL
);
$stmt->execute(['user_id' => $user['id_utilisateur'], 'user_id2' => $user['id_utilisateur']]);
$annonces = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Découvrir | Campus</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><span class="brand-mark">C</span><span>campus<span class="brand-dot">.</span></span></a>
        <nav class="main-nav" aria-label="Navigation principale">
            <a class="nav-link" href="/">Accueil</a>
            <a class="nav-link active" href="/decouvrir.php">Explorer</a>
        </nav>
        <nav class="account-nav">
            <?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
        </nav>
    </header>

    <main class="swipe-page">
        <div class="swipe-heading">
            <p class="eyebrow">Un objet à la fois</p>
            <h1>Découvrir</h1>
            <p>Glisse à droite si ça t'intéresse, à gauche pour passer.</p>
        </div>

        <?php if ($annonces === []): ?>
            <div class="swipe-empty">
                <span class="empty-icon">○</span>
                <h3>Tu as tout vu pour l'instant</h3>
                <p>Reviens plus tard, de nouvelles annonces arrivent régulièrement.</p>
                <a class="outline-button" href="/">Voir toutes les annonces</a>
            </div>
        <?php else: ?>
            <div class="swipe-stack" id="swipe-stack"></div>
            <div class="swipe-actions">
                <button type="button" class="swipe-btn swipe-btn-pass" id="btn-pass" aria-label="Passer">✕</button>
                <button type="button" class="swipe-btn swipe-btn-like" id="btn-like" aria-label="J'aime">♥</button>
            </div>
        <?php endif; ?>
    </main>

    <script id="annonces-data" type="application/json"><?= json_encode($annonces, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <script>
    (function () {
        var stack = document.getElementById('swipe-stack');
        if (!stack) return;

        var data = JSON.parse(document.getElementById('annonces-data').textContent);
        var csrfToken = <?= json_encode(csrfToken()) ?>;
        var index = 0;

        function cardMarkup(annonce, position) {
            var photo = annonce.photo_url
                ? '<img src="' + annonce.photo_url + '" alt="">'
                : (annonce.categorie || 'Annonce').slice(0, 3).toUpperCase();
            var priceLabel = Number(annonce.prix).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
            var el = document.createElement('div');
            el.className = 'swipe-card ' + (position === 'top' ? 'swipe-card-top' : 'swipe-card-behind');
            el.innerHTML =
                '<div class="swipe-photo">' + photo +
                    '<span class="swipe-badge swipe-badge-category">' + (annonce.categorie || 'Autre') + '</span>' +
                    '<span class="swipe-badge swipe-badge-price">' + priceLabel + '</span>' +
                    '<span class="swipe-stamp swipe-stamp-like">J\'aime</span>' +
                    '<span class="swipe-stamp swipe-stamp-pass">Passe</span>' +
                '</div>' +
                '<div class="swipe-info">' +
                    '<h3>' + annonce.titre + '</h3>' +
                    '<div class="swipe-meta">' + (annonce.etat || 'Disponible') + ' · proposé par ' + annonce.prenom + '</div>' +
                '</div>';
            return el;
        }

        function render() {
            stack.innerHTML = '';
            if (index >= data.length) {
                stack.outerHTML = '<div class="swipe-empty"><span class="empty-icon">○</span>' +
                    '<h3>Tu as tout vu pour l\'instant</h3>' +
                    '<p>Reviens plus tard, de nouvelles annonces arrivent régulièrement.</p>' +
                    '<a class="outline-button" href="/">Voir toutes les annonces</a></div>';
                document.querySelector('.swipe-actions').style.display = 'none';
                return;
            }
            if (data[index + 1]) {
                stack.appendChild(cardMarkup(data[index + 1], 'behind'));
            }
            var top = cardMarkup(data[index], 'top');
            stack.appendChild(top);
            attachDrag(top);
        }

        function sendChoice(annonceId, statut) {
            fetch('/api/like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_annonce: annonceId, statut: statut, csrf_token: csrfToken })
            }).catch(function () { /* échec silencieux, on ne bloque pas l'UI */ });
        }

        function swipeOut(card, direction, callback) {
            var distance = direction === 'like' ? 600 : -600;
            var rotation = direction === 'like' ? 22 : -22;
            card.style.transition = 'transform .35s ease, opacity .35s ease';
            card.style.transform = 'translateX(' + distance + 'px) rotate(' + rotation + 'deg)';
            card.style.opacity = '0';
            setTimeout(callback, 300);
        }

        function choose(direction) {
            var card = stack.querySelector('.swipe-card-top');
            if (!card) return;
            var annonce = data[index];
            sendChoice(annonce.id_annonce, direction === 'like' ? 'like' : 'passe');
            swipeOut(card, direction, function () {
                index += 1;
                render();
            });
        }

        function attachDrag(card) {
            var startX = 0, startY = 0, currentX = 0, dragging = false;

            function pointerDown(e) {
                dragging = true;
                startX = (e.touches ? e.touches[0].clientX : e.clientX);
                startY = (e.touches ? e.touches[0].clientY : e.clientY);
                card.style.transition = 'none';
            }
            function pointerMove(e) {
                if (!dragging) return;
                var x = (e.touches ? e.touches[0].clientX : e.clientX);
                var y = (e.touches ? e.touches[0].clientY : e.clientY);
                currentX = x - startX;
                var rotation = currentX / 14;
                card.style.transform = 'translate(' + currentX + 'px, ' + (y - startY) + 'px) rotate(' + rotation + 'deg)';
                var likeStamp = card.querySelector('.swipe-stamp-like');
                var passStamp = card.querySelector('.swipe-stamp-pass');
                likeStamp.style.opacity = Math.max(0, Math.min(1, currentX / 100));
                passStamp.style.opacity = Math.max(0, Math.min(1, -currentX / 100));
            }
            function pointerUp() {
                if (!dragging) return;
                dragging = false;
                if (currentX > 120) {
                    choose('like');
                } else if (currentX < -120) {
                    choose('passe');
                } else {
                    card.style.transition = 'transform .25s ease';
                    card.style.transform = 'translate(0, 0) rotate(0)';
                }
                currentX = 0;
            }

            card.addEventListener('mousedown', pointerDown);
            window.addEventListener('mousemove', pointerMove);
            window.addEventListener('mouseup', pointerUp);
            card.addEventListener('touchstart', pointerDown, { passive: true });
            card.addEventListener('touchmove', pointerMove, { passive: true });
            card.addEventListener('touchend', pointerUp);
        }

        var btnLike = document.getElementById('btn-like');
        var btnPass = document.getElementById('btn-pass');
        if (btnLike) btnLike.addEventListener('click', function () { choose('like'); });
        if (btnPass) btnPass.addEventListener('click', function () { choose('passe'); });

        render();
    })();
    </script>
</body>
</html>
