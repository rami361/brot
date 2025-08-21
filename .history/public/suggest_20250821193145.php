<?php
include_once '../config/db.inc.php';
include_once '../src/functions.inc.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $cleanQuery = cleanSearchQuery($query, 3);

    if (!$cleanQuery) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT DISTINCT name
            FROM uebersicht
            WHERE MATCH(name, type, description_short) AGAINST(:query IN BOOLEAN MODE)
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':query', $cleanQuery, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler bei der Autovervollständigung: ' . $e->getMessage()]);
}
