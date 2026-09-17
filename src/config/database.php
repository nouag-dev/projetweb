<?php
// src/config/database.php

function getConnexion() {
    $host = getenv('DB_HOST') ?: 'db';
    $dbname = getenv('DB_NAME') ?: 'campus_app';
    $user = getenv('DB_USER') ?: 'campus';
    $password = getenv('DB_PASSWORD') ?: 'campus_password';

    try {
        $pdo = new PDO("pgsql:host=$host;port=5432;dbname=$dbname", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion à la base : " . $e->getMessage());
    }
}
