<?php
require_once __DIR__ . '/../../src/config/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$csrf = is_array($payload) ? ($payload['csrf_token'] ?? null) : null;
$annonceId = is_array($payload) ? filter_var($payload['id_annonce'] ?? null, FILTER_VALIDATE_INT) : false;
$statut = is_array($payload) ? ($payload['statut'] ?? null) : null;

if (!checkCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jeton de sécurité invalide.']);
    exit;
}

if ($annonceId === false || $annonceId === null || !in_array($statut, ['like', 'passe'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Requête invalide.']);
    exit;
}

$user = currentUser();

try {
    $pdo = getConnexion();
    $stmt = $pdo->prepare(
        'INSERT INTO likes (id_utilisateur, id_annonce, statut, date_like)
         VALUES (:user, :annonce, :statut, CURRENT_TIMESTAMP)
         ON CONFLICT (id_utilisateur, id_annonce)
         DO UPDATE SET statut = EXCLUDED.statut, date_like = EXCLUDED.date_like'
    );
    $stmt->execute([
        'user' => $user['id_utilisateur'],
        'annonce' => $annonceId,
        'statut' => $statut,
    ]);
    echo json_encode(['ok' => true]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible d’enregistrer votre choix.']);
}
