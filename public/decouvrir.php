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
        WHERE a.statut_vente = 'disponible'
            AND a.id_utilisateur <> :user_id
      AND NOT EXISTS (
          SELECT 1 FROM likes l
          WHERE l.id_annonce = a.id_annonce AND l.id_utilisateur = :user_id2
      )
    ORDER BY CASE
        WHEN :location_city = '' OR lower(trim(u.ville)) = lower(trim(:location_city_match)) THEN 0
        ELSE 1
    END, a.date_publication DESC
    LIMIT 30
    SQL
);
$userCity = trim((string) ($user['ville'] ?? ''));
$stmt->execute([
    'user_id' => $user['id_utilisateur'],
    'user_id2' => $user['id_utilisateur'],
    'location_city' => $userCity,
    'location_city_match' => $userCity,
]);
$annonces = $stmt->fetchAll();
foreach ($annonces as &$annonce) {
    $annonce['placeholder_url'] = announcementPlaceholderUrl($annonce['titre'], $annonce['categorie']);
}
unset($annonce);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e(currentTheme()) ?>">
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
            <?php include __DIR__ . '/../src/includes/notif-bell.php'; ?><?php include __DIR__ . '/../src/includes/user-menu.php'; ?>
        </nav>
    </header>

    <main class="swipe-page">
        <div class="swipe-heading">
            <p class="eyebrow">Un objet à la fois</p>
            <h1>Découvrir</h1>
            <p>À droite ou ♥ pour aimer ; à gauche ou ✕ pour indiquer que tu n’es pas intéressé.</p>
        </div>

        <?php if ($annonces === []): ?>
            <div class="swipe-empty">
                <span class="empty-icon">○</span>
                <h3>Tu as tout vu pour l'instant</h3>
                <p>Reviens plus tard, de nouvelles annonces arrivent régulièrement.</p>
                <a class="outline-button" href="/">Voir toutes les annonces</a>
            </div>
        <?php else: ?>
            <p class="swipe-feedback" id="swipe-feedback" role="status" aria-live="polite" hidden></p>
            <div class="swipe-stack" id="swipe-stack"></div>
            <div class="swipe-actions">
                <button type="button" class="swipe-btn swipe-btn-pass" id="btn-pass" aria-label="Pas intéressé" title="Pas intéressé">✕</button>
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
        var feedback = document.getElementById('swipe-feedback');
        var index = 0;
        var actionInProgress = false;

        function cardMarkup(annonce, position) {
            var priceLabel = Number(annonce.prix).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
            var el = document.createElement('div');
            el.className = 'swipe-card ' + (position === 'top' ? 'swipe-card-top' : 'swipe-card-behind');
            var photo = document.createElement('div');
            photo.className = 'swipe-photo';
            if (annonce.photo_url) {
                var image = document.createElement('img');
                image.src = annonce.photo_url;
                image.alt = '';
                photo.appendChild(image);
            } else {
                var placeholder = document.createElement('img');
                placeholder.className = 'listing-placeholder';
                placeholder.src = annonce.placeholder_url;
                placeholder.alt = 'Photo d’exemple pour ' + annonce.titre;
                photo.appendChild(placeholder);
            }
            [['swipe-badge swipe-badge-category', annonce.categorie || 'Autre'], ['swipe-badge swipe-badge-price', priceLabel], ['swipe-stamp swipe-stamp-like', 'J’aime'], ['swipe-stamp swipe-stamp-pass', 'Passe']].forEach(function (item) {
                var badge = document.createElement('span');
                badge.className = item[0];
                badge.textContent = item[1];
                photo.appendChild(badge);
            });
            var info = document.createElement('div');
            info.className = 'swipe-info';
            var title = document.createElement('h3');
            title.textContent = annonce.titre;
            var meta = document.createElement('div');
            meta.className = 'swipe-meta';
            meta.textContent = (annonce.etat || 'Disponible') + ' · proposé par ' + annonce.prenom;
            info.append(title, meta);
            el.append(photo, info);
            return el;
        }

        function render() {
            stack.innerHTML = '';
            feedback.hidden = true;
            feedback.textContent = '';
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
            return fetch('/api/like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_annonce: annonceId, statut: statut, csrf_token: csrfToken })
            }).then(function (response) {
                return response.json().catch(function () {
                    throw new Error('Le serveur a renvoyé une réponse invalide.');
                }).then(function (result) {
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || 'Impossible d’enregistrer ce choix.');
                    }
                });
            });
        }

        function swipeOut(card, direction) {
            var distance = direction === 'like' ? 600 : -600;
            var rotation = direction === 'like' ? 22 : -22;
            card.style.transition = 'transform .35s ease, opacity .35s ease';
            card.style.transform = 'translateX(' + distance + 'px) rotate(' + rotation + 'deg)';
            card.style.opacity = '0';
            return new Promise(function (resolve) {
                setTimeout(resolve, 350);
            });
        }

        function choose(direction) {
            var card = stack.querySelector('.swipe-card-top');
            if (!card || actionInProgress) return;
            actionInProgress = true;
            feedback.hidden = true;
            feedback.textContent = '';
            setButtonsDisabled(true);
            var annonce = data[index];
            card.style.pointerEvents = 'none';
            swipeOut(card, direction)
                .then(function () {
                    return sendChoice(annonce.id_annonce, direction === 'like' ? 'like' : 'passe');
                })
                .then(function () {
                    index += 1;
                    actionInProgress = false;
                    setButtonsDisabled(false);
                    render();
                })
                .catch(function (error) {
                    card.style.transition = 'transform .2s ease, opacity .2s ease';
                    card.style.transform = 'translate(0, 0) rotate(0)';
                    card.style.opacity = '1';
                    card.style.pointerEvents = '';
                    card.querySelector('.swipe-stamp-like').style.opacity = '0';
                    card.querySelector('.swipe-stamp-pass').style.opacity = '0';
                    feedback.textContent = error.message;
                    feedback.hidden = false;
                    actionInProgress = false;
                    setButtonsDisabled(false);
                });
        }

        function setButtonsDisabled(disabled) {
            var buttons = document.querySelectorAll('.swipe-actions button');
            buttons.forEach(function (button) {
                button.disabled = disabled;
            });
        }

        function attachDrag(card) {
            var startX = 0, currentX = 0, dragging = false, pointerId = null;

            function pointerDown(e) {
                if (!e.isPrimary || (e.pointerType === 'mouse' && e.button !== 0) || actionInProgress) return;
                dragging = true;
                pointerId = e.pointerId;
                startX = e.clientX;
                currentX = 0;
                card.setPointerCapture(pointerId);
                card.style.transition = 'none';
            }
            function pointerMove(e) {
                if (!dragging || e.pointerId !== pointerId) return;
                currentX = e.clientX - startX;
                var rotation = currentX / 14;
                card.style.transform = 'translate(' + currentX + 'px, 0) rotate(' + rotation + 'deg)';
                var likeStamp = card.querySelector('.swipe-stamp-like');
                var passStamp = card.querySelector('.swipe-stamp-pass');
                likeStamp.style.opacity = Math.max(0, Math.min(1, currentX / 100));
                passStamp.style.opacity = Math.max(0, Math.min(1, -currentX / 100));
            }
            function resetPosition() {
                card.style.transition = 'transform .25s ease';
                card.style.transform = 'translate(0, 0) rotate(0)';
                card.querySelector('.swipe-stamp-like').style.opacity = '0';
                card.querySelector('.swipe-stamp-pass').style.opacity = '0';
            }
            function pointerUp(e) {
                if (!dragging || e.pointerId !== pointerId) return;
                dragging = false;
                pointerId = null;
                var threshold = Math.max(90, card.offsetWidth * .25);
                if (currentX > threshold) {
                    choose('like');
                } else if (currentX < -threshold) {
                    choose('passe');
                } else {
                    resetPosition();
                }
                currentX = 0;
            }
            function pointerCancel(e) {
                if (!dragging || e.pointerId !== pointerId) return;
                dragging = false;
                pointerId = null;
                currentX = 0;
                resetPosition();
            }
            card.addEventListener('pointerdown', pointerDown);
            card.addEventListener('pointermove', pointerMove);
            card.addEventListener('pointerup', pointerUp);
            card.addEventListener('pointercancel', pointerCancel);
        }

        var btnLike = document.getElementById('btn-like');
        var btnPass = document.getElementById('btn-pass');
        if (btnLike) btnLike.addEventListener('click', function () { choose('like'); });
        if (btnPass) btnPass.addEventListener('click', function () { choose('passe'); });

        render();
    })();
    </script>
    <?php include __DIR__ . '/../src/includes/footer.php'; ?>
</body>
</html>
