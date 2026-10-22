<?php

require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(): void
{
    if (currentUser() === null) {
        header('Location: /connexion.php?redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function checkCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function announcementPlaceholderUrl(?string $title, ?string $category): string
{
    $titleImages = [
        '/calculatrice|calculator/iu' => 'calculatrice',
        '/écran|ecran|moniteur|monitor|tablette/iu' => 'ecran',
        '/clavier/iu' => 'clavier',
        '/sac|cartable|sac à dos|sac à main/iu' => 'sac',
        '/cours de|soutien|tutorat|aide aux devoirs/iu' => 'cours',
        '/manuel|livre|roman|cours/iu' => 'livre',
        '/\b(?:bureau|table)\b/iu' => 'bureau',
        '/chaise|fauteuil/iu' => 'chaise',
        '/pull|sweat|gilet/iu' => 'pull',
        '/veste|manteau|blouson/iu' => 'veste',
        '/déménagement|demenagement|carton/iu' => 'demenagement',
        '/aide|soutien|particulier|service/iu' => 'cours',
        '/vélo|velo|bicyclette|vtt/iu' => 'velo',
        '/console|manette|jeu vidéo|jeu video/iu' => 'jeux-video',
        '/guitare|piano|instrument|musique/iu' => 'musique',
        '/maquillage|beauté|beaute|cosmétique|cosmetique/iu' => 'beaute',
        '/plante|lampe|déco|deco|décoration|decoration/iu' => 'decoration',
        '/ballon|raquette|haltère|haltere|sport/iu' => 'sport',
        '/stylo|cahier|carnet|papeterie/iu' => 'papeterie',
    ];

    foreach ($titleImages as $pattern => $image) {
        if (preg_match($pattern, (string) $title) === 1) {
            return '/assets/illustrations/' . $image . '.jpg';
        }
    }

    $categoryImages = [
        '/électronique|electronique/iu' => 'calculatrice',
        '/informatique/iu' => 'ecran',
        '/papeterie|fournitures/iu' => 'papeterie',
        '/livres|cours/iu' => 'livre',
        '/mobilier/iu' => 'bureau',
        '/vêtements|vetements/iu' => 'pull',
        '/services/iu' => 'cours',
        '/sport|loisirs/iu' => 'sport',
        '/vélo|velo|mobilité|mobilite/iu' => 'velo',
        '/musique|instrument/iu' => 'musique',
        '/jeux vidéo|jeux video/iu' => 'jeux-video',
        '/beauté|beaute/iu' => 'beaute',
        '/décoration|decoration/iu' => 'decoration',
    ];
    $image = 'livre';
    foreach ($categoryImages as $pattern => $categoryImage) {
        if (preg_match($pattern, (string) $category) === 1) {
            $image = $categoryImage;
            break;
        }
    }

    return '/assets/illustrations/' . $image . '.jpg';
}

/**
 * Thème à appliquer (clair/sombre/auto) pour l'utilisateur courant, 'clair' par défaut.
 */
function currentTheme(): string
{
    $user = currentUser();
    $theme = $user['theme'] ?? 'clair';
    return in_array($theme, ['clair', 'sombre', 'auto'], true) ? $theme : 'clair';
}
