<?php
include_once '../config/db.inc.php';
include_once '../src/functions.inc.php';

header('Content-Type: application/json; charset=utf-8');

function cleanBooleanSearchQueryUTF8($query, $minLength = 4) {
    $query = preg_replace('/[^\p{L}\p{N}\s\+\-\*"]+/u', ' ', $query);
    $query = trim(preg_replace('/\s+/', ' ', $query));
    $words = array_filter(explode(' ', $query), fn($w) => mb_strlen($w, 'UTF-8') >= $minLength);
    if (empty($words)) return '';
    $firstWord = array_shift($words);
    $searchQuery = '+' . $firstWord . '*';
    if (!empty($words)) {
        $searchQuery .= ' ' . implode('* ', $words) . '*';
    }
    return $searchQuery;
}

function escapeLike($str) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $str);
}

try {
    $query = trim($_POST['q'] ?? '');
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_POST['per_page'] ?? 12)));
    $offset = ($page - 1) * $perPage;
    $ingredients = $_POST['ingredients'] ?? [];
    $logic = in_array(strtoupper($_POST['logic'] ?? 'OR'), ['AND','OR']) ? strtoupper($_POST['logic']) : 'OR';

    $cleanQuery = cleanBooleanSearchQueryUTF8($query, 4);
    $words = array_filter(explode(' ', $query), fn($w) => mb_strlen($w, 'UTF-8') >= 1);

    if (!$cleanQuery && empty($ingredients)) {
        $results = [];
    } else {
        // Fulltext-Gewichte
        $fulltextWeights = [
            'name' => 3,
            'type' => 2,
            'ingredients_text' => 2,
            'description_short' => 1,
            'description_long' => 0.5
        ];

        // LIKE-Gewichte
        $likeWeights = [
            'name' => 1.5,
            'type' => 1,
            'ingredients_text' => 1,
            'description_short' => 0.5,
            'description_long' => 0.25
        ];

        // Metaphone-Gewichte
        $metaphoneWeights = [
            'metaphone_name' => 0.5,
            'metaphone_type' => 0.25,
            'metaphone_ingredients_text' => 0.25
        ];

        $relevanceParts = [];
        $params = [];
        $likeWhereParts = [];

        // Fulltext
        if ($cleanQuery) {
            $relevanceParts[] = "MATCH(u." . implode(',', array_keys($fulltextWeights)) . ") AGAINST(:query IN BOOLEAN MODE)";
            $params[':query'] = $cleanQuery;
        }

        // LIKE-Bedingungen für Relevanz
        foreach ($words as $i => $word) {
            $likeSubParts = [];
            // Normale Spalten
            foreach ($likeWeights as $col => $weight) {
                $paramName = ":like_{$col}_{$i}";
                $likeSubParts[] = "u.$col LIKE $paramName";
                $relevanceParts[] = "(CASE WHEN u.$col LIKE $paramName THEN 1 ELSE 0 END) * $weight";
                $params[$paramName] = '%' . escapeLike($word) . '%';
            }
            // Metaphone-Spalten
            foreach ($metaphoneWeights as $col => $weight) {
                $paramName = ":meta_{$col}_{$i}";
                $likeSubParts[] = "u.$col LIKE $paramName";
                $relevanceParts[] = "(CASE WHEN u.$col LIKE $paramName THEN 1 ELSE 0 END) * $weight";
                // Metaphone: einfach die einzelnen Buchstaben wie in DB vergleichen
                $params[$paramName] = '%' . escapeLike(metaphone($word)) . '%';
            }
            if (!empty($likeSubParts)) $likeWhereParts[] = '(' . implode(' OR ', $likeSubParts) . ')';
        }

        // Zutaten-Bedingungen
        $ingredientParts = [];
        foreach ($ingredients as $i => $ingredient) {
            $paramName = ":ingredient_$i";
            $ingredientParts[] = "u.ingredients_text LIKE $paramName";
            $params[$paramName] = '%' . escapeLike($ingredient) . '%';
        }

        // WHERE-Bedingungen kombinieren
        $whereParts = [];

        // Freitext
        $fulltextWhere = [];
        if ($cleanQuery) $fulltextWhere[] = "MATCH(u." . implode(',', array_keys($fulltextWeights)) . ") AGAINST(:query IN BOOLEAN MODE)";
        if ($likeWhereParts) $fulltextWhere[] = '(' . implode(' AND ', $likeWhereParts) . ')';
        if ($fulltextWhere) $whereParts[] = '(' . implode(' OR ', $fulltextWhere) . ')';

        // Zutaten
        if ($ingredientParts) $whereParts[] = '(' . implode(" $logic ", $ingredientParts) . ')';

        $whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        // $relevanceParts = array_filter($relevanceParts, fn($v) => trim($v) !== '');
        // $likeWhereParts = array_filter($likeWhereParts, fn($v) => trim($v) !== '');
        $relevanceExpr = $relevanceParts ? implode(' + ', $relevanceParts) : '0';

        $sql = "
            SELECT u.rezepte_id, u.uebersicht_id, u.name, u.type, u.description_short, u.prep_minutes,
                   JSON_UNQUOTE(JSON_EXTRACT(r.rezept, '$.images.title')) AS image,
                   ($relevanceExpr) AS relevance
            FROM uebersicht u
            JOIN rezepte r ON u.rezepte_id = r.rezepte_id
            $whereSql
            ORDER BY relevance DESC
            LIMIT :offset, :perPage
        ";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val, PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler bei der Suche: ' . $e->getMessage()]);
}
?>
