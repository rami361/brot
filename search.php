<?php
include_once 'db.inc.php';
header('Content-Type: application/json; charset=utf-8');

function createSnippet($text, $query, $maxLength = 150) {
    if (!$text || !$query) return substr($text, 0, $maxLength) . '...';
    $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    $pattern = implode('|', array_map('preg_quote', $words));
    $pos = stripos($text, $words[0]);
    $start = max(0, $pos - $maxLength / 2);
    $snippet = substr($text, $start, $maxLength);
    if ($start > 0) $snippet = '...' . $snippet;
    if ($start + $maxLength < strlen($text)) $snippet .= '...';
    return $snippet;
}

$query = trim($_POST['q'] ?? '');
$ingredients = isset($_POST['ingredients']) && is_array($_POST['ingredients']) ? array_map('trim', $_POST['ingredients']) : [];
$logic = $_POST['logic'] ?? 'OR';
$page = max(1, (int) ($_POST['page'] ?? 1));
$perPage = (int) ($_POST['per_page'] ?? 12);
$offset = ($page - 1) * $perPage;

$response = ['results' => [], 'total' => 0, 'error' => null];

try {
    if (empty($query) && empty($ingredients)) {
        $response['error'] = 'Bitte mindestens 4 Zeichen oder eine Zutat angeben.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cleanQuery = cleanSearchQuery($query);
    if (empty($cleanQuery) && empty($ingredients)) {
        $response['error'] = 'Ungültige Suchanfrage.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "SELECT u.uebersicht_id, u.name, u.type, u.description_short, 
                   MATCH(u.name, u.type, u.ingredients_text, u.description_long, u.description_short) 
                   AGAINST(? IN BOOLEAN MODE) AS score, 
                   JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.images.title')) AS image
            FROM uebersicht u
            JOIN rezepte r ON u.rezepte_id = r.rezepte_id
            WHERE MATCH(u.name, u.type, u.ingredients_text, u.description_long, u.description_short) 
                  AGAINST(? IN BOOLEAN MODE)";
    $params = [$cleanQuery, $cleanQuery];
    $types = 'ss';

    if (!empty($ingredients)) {
        $sql .= " AND (";
        $placeholders = array_fill(0, count($ingredients), 'u.ingredients_text LIKE ?');
        $sql .= implode(" $logic ", $placeholders);
        $sql .= ")";
        foreach ($ingredients as $ingredient) {
            $params[] = "%$ingredient%";
            $types .= 's';
        }
    }

    $sql .= " ORDER BY score DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';

    error_log('SQL Query: ' . $sql);
    error_log('SQL Params: ' . json_encode($params));

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response['results'][] = [
            'uebersicht_id' => $row['uebersicht_id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'snippet' => $row['description_short'],
            'image' => $row['image']
        ];
    }

    // Gesamtzahl der Ergebnisse
    $countSql = "SELECT COUNT(*) AS total 
                 FROM uebersicht u 
                 JOIN rezepte r ON u.rezepte_id = r.rezepte_id 
                 WHERE MATCH(u.name, u.type, u.ingredients_text, u.description_long, u.description_short) 
                       AGAINST(? IN BOOLEAN MODE)";
    $countParams = [$cleanQuery];
    $countTypes = 's';

    if (!empty($ingredients)) {
        $countSql .= " AND (";
        $placeholders = array_fill(0, count($ingredients), 'u.ingredients_text LIKE ?');
        $countSql .= implode(" $logic ", $placeholders);
        $countSql .= ")";
        foreach ($ingredients as $ingredient) {
            $countParams[] = "%$ingredient%";
            $countTypes .= 's';
        }
    }

    error_log('Count SQL Query: ' . $countSql);
    error_log('Count SQL Params: ' . json_encode($countParams));

    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $response['total'] = $countResult->fetch_assoc()['total'];

    $stmt->close();
    $countStmt->close();
} catch (Exception $e) {
    $response['error'] = 'Fehler bei der Suche: ' . $e->getMessage();
    error_log('Search Error: ' . $e->getMessage());
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
?>