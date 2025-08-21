<?php
require_once '../vendor/autoload.php'; // Für phpdotenv, falls verwendet

try {
    // Lade Umgebungsvariablen (falls .env verwendet wird)
    // $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    // $dotenv->load();

    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'brot';
    $username = getenv('DB_USER') ?: 'brot';
    $password = getenv('DB_PASS') ?: 'brot';

    // PDO-Verbindung
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage()]);
    exit;
}

$scrapeBaseUrl = "https://www.ploetzblog.de";

