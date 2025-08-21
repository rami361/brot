<?php
include_once '../config/db.inc.php';
include_once '../src/functions.inc.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Eingaben validieren
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $ingredients = isset($_GET['ingredients']) ? json_decode($_GET['ingredients'], true) : [];
    $logic = isset($_GET['logic']) && in_array($_GET['logic'], ['AND', 'OR']) ? $_GET['logic'] : 'OR';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 12;

    // Suchanfrage bereinigen
    $cleanQuery = cleanSearchQuery($query, 3);

    // SQL-Abfrage aufbauen
    $sql = "SELECT u.id, u.name, u.type, u.description_short, 
                   JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.images.title')) AS image
            FROM uebersicht u
            JOIN rezepte r ON u.id = r.id";
    
    $params = [];
    $conditions = [];

    // Volltextsuche
    if ($cleanQuery) {
        $conditions[] = "(MATCH(u.name, u.type, u.description_long, u.description_short) 
                         AGAINST(:query IN BOOLEAN MODE) 
                         OR MATCH(u.ingredients_text) AGAINST(:query IN BOOLEAN MODE))";
        $params[':query'] = $cleanQuery;
    }

    // Zutatenfilter
    if (!empty($ingredients)) {
        $ingredientConditions = [];
        foreach ($ingredients as $ingredient) {
            $ingredientConditions[] = "u.ingredients_text LIKE :ingredient_" . count($ingredientConditions);
            $params[':ingredient_' . count($ingredientConditions)] = '%' . $conn->quote($ingredient) . '%';
        }
        $conditions[] = '(' . implode(" $logic ", $ingredientConditions) . ')';
    }

    // Bedingungen hinzufügen
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    // Pagination
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT :offset, :perPage";
    $params[':offset'] = $offset;
    $params[':perPage'] = $perPage;

    // Abfrage ausführen
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Suchbegriffe hervorheben (falls nicht bereits implementiert)
    foreach ($results as &$row) {
        if ($cleanQuery) {
            foreach (explode(' ', $cleanQuery) as $word) {
                $row['description_short'] = str_ireplace($word, "<strong>$word</strong>", $row['description_short']);
            }
        }
    }

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler bei der Suche: ' . $e->getMessage()]);
}
