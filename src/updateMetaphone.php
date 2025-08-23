<?php
include_once '{SRC_DIR}/../config/db.inc.php';
include_once 'functions.inc.php'; // Optional für eigene Funktionen

// Berechnet Metaphone für relevante Spalten
function computeMetaphoneColumns(array $recipe): array {
    $meta = [];
    $meta['name_meta'] = metaphone($recipe['name'] ?? '');
    $meta['type_meta'] = metaphone($recipe['type'] ?? '');
    $meta['ingredients_meta'] = metaphone(normalizeIngredientsForMetaphone($recipe['ingredients_text'] ?? ''));
    return $meta;
}

function normalizeIngredientsForMetaphone(string $ingredients): string {
    // Präfix 'grundzutaten.' entfernen
    $ingredients = str_replace('grundzutaten.', '', $ingredients);
    // Punkte durch Leerzeichen ersetzen
    $ingredients = str_replace('.', ' ', $ingredients);
    // In einzelne Wörter aufteilen, Duplikate entfernen
    $words = array_unique(array_filter(explode(' ', $ingredients), fn($w) => $w !== ''));
    return implode(' ', $words);
}

try {
    // Beispiel: neue Rezepte laden oder aktualisieren
    $stmt = $conn->query("SELECT uebersicht_id, name, type, ingredients_text FROM uebersicht WHERE metaphone_name IS NULL");
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $conn->prepare("
        UPDATE uebersicht
        SET metaphone_name = :name_meta,
            metaphone_type = :type_meta,
            metaphone_ingredients_text = :ingredients_meta
        WHERE uebersicht_id = :uebersicht_id
    ");

    foreach ($recipes as $recipe) {
        $meta = computeMetaphoneColumns($recipe);
        $updateStmt->execute([
            ':name_meta' => $meta['name_meta'],
            ':type_meta' => $meta['type_meta'],
            ':ingredients_meta' => $meta['ingredients_meta'],
            ':uebersicht_id' => $recipe['uebersicht_id']
        ]);
    }

    echo "Metaphone-Spalten erfolgreich aktualisiert.";

} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
