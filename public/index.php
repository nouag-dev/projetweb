<?php
require_once __DIR__ . '/../src/config/database.php';

echo "<h1>Bienvenue sur Campus 🚀</h1>";

try {
    $pdo = getConnexion();
    $stmt = $pdo->query("SELECT COUNT(*) AS nb FROM categories");
    $row = $stmt->fetch();
    echo "<p>Connexion PostgreSQL OK — {$row['nb']} catégories en base.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
