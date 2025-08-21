<?php
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $dbname = getenv('DB_NAME') ?: 'brot';
    $username = getenv('DB_USER') ?: 'brot';
    $password = getenv('DB_PASS') ?: 'brot';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage()]);
    exit;
}

$scrapeBaseUrl = "https://www.ploetzblog.de";
$scrapeUrl = "https://www.ploetzblog.de/rezepte/alle-rezepte?sort=newest,1&sheet=%page%";

