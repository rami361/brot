<?php
include_once 'db.inc.php';
header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$suggestions = [];

if (strlen($query) >= 1) {
    $cleanQuery = cleanSearchQuery($query);
    if (empty($cleanQuery)) {
        echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $sql = "SELECT DISTINCT name FROM uebersicht WHERE MATCH(name, type) AGAINST(? IN BOOLEAN MODE) LIMIT 10";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param('s', $cleanQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = ['name' => $row['name']];
    }
    $stmt->close();
}

$conn->close();
echo json_encode($suggestions, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
?>