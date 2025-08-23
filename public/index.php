<?php
include_once '../config/db.inc.php';
include_once '../src/functions.inc.php';
$perPage = 12;

// Funktion zum rekursiven Erstellen von Dropdown-Optionen
function listIngredients($schema, $prefix = '')
{
    $options = [];
    foreach ($schema as $key => $value) {
        $path = $prefix ? "$prefix.$key" : $key;
        if (isset($value['name'])) {
            $options[] = ['path' => $path, 'name' => $value['name']];
        }
        if (is_array($value) && !isset($value['name'])) {
            $options = array_merge($options, listIngredients($value, $path));
        }
    }
    return $options;
}

$schema = json_decode(file_get_contents('../schema/brot_schema_v1.json'), true);
$ingredients = listIngredients($schema['zutaten']);

/**
 * Rekursive Funktion zum Traversieren
 */
function buildArray($node, array $keys = []): array
{
    $result = [];
    // Wenn "name" existiert → ersten Namen verwenden
    if (isset($node['name']) && is_array($node['name']) && count($node['name']) > 0) {
        $name = $node['name'][0];
        $pathStr = ltrim(implode('.', $keys), '.');
        $ref = &$result; // Pfad in Result-Array abbilden
        foreach ($keys as $k) {
            if (!isset($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
        $ref = ['name' => $name, 'path' => $pathStr];
    }
    // Rekursiv durch children
    if (isset($node) && is_array($node)) {
        foreach ($node as $key => $child) {
            $childResult = buildArray($child, array_merge($keys, [$key]));
            $result = array_replace_recursive($result, $childResult);// merge ins Ergebnis
        }
    }
    return $result;
}
$ingredientsArray = buildArray($schema['zutaten']);
// echo "<pre>";
// print_r($ingredientsArray['grundzutaten']['mehl']);
// echo "</pre>";
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brotrezepte Suche</title>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- Typeahead.js -->
    <script src="js/typeahead.bundle.min.js"></script>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.5/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.5/dist/js/select2.min.js"></script>
    <!-- Select2 Custom Adapter https://github.com/andreivictor/select2-customSelectionAdapter -->
    <link rel="stylesheet" href="css/select2.customSelectionAdapter.css" />
    <script src="js/select2.customSelectionAdapter.js"></script>
    <!-- Select2 Theme https://github.com/apalfrey/select2-bootstrap-5-theme -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/brot.css" />
</head>

<body>
    <div class="w-100 py-3">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h1 id="header" class="my-4 text-center">BROT REZEPTE</h1>
                </div>
            </div>
        </div>
    </div>
    <div class="w-100 bg-dark bg-gradient py-3">
        <div class="container pt-4">
            <div class="row">
                <div class="col col-6">
                    <div class="position-relative mb-4">
                        <input type="text" id="searchInput" class="form-control rounded-pill"
                            placeholder="Suche nach Rezepten (z.B. 'Weizenbrot' oder 'Roggen')..." aria-label="Suche">
                        <div id="alert-condition" class="position-absolute top-0 end-0 h-100 d-flex align-items-center pe-2 me-1 d-none" role="alert">
                            Bitte mindestens 4 Zeichen oder eine Zutat auswählen.
                        </div>
                        <div class="position-absolute top-0 end-0 h-100 d-flex align-items-center pe-2 me-1">
                            <div id="alert-searching" class="spinner-border spinner-border-sm" role="status"
                                style="display:none;">
                                <span class="visually-hidden">Suche läuft...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col col-6">
                    <select id="ingredientFilter" class="form-select rounded-pill" multiple
                        aria-label="Zutaten auswählen">
                        <?php foreach ($ingredientsArray['grundzutaten']['mehl'] as $key => $mehlCategory): ?>
                            <optgroup label="<?php echo $key ?>">
                                <?php foreach ($mehlCategory as $ingredient): ?>
                                    <option value="<?php echo htmlspecialchars($ingredient['path']); ?>">
                                        <?php echo htmlspecialchars($ingredient['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div id="ingredientLogic" class="col-12 mt-3 d-flex d-none">
                        <!-- <div class="form-check form-check-inline"> -->
                        <input type="radio" name="ingredientLogic" value="AND" class="btn-check" id="logicAnd" checked>
                        <label class="btn btn-left" for="logicAnd">Alle Zutaten</label>
                        <!-- </div>
                        <div class="form-check form-check-inline"> -->
                        <input type="radio" name="ingredientLogic" value="OR" class="btn-check" id="logicOr">
                        <label class="btn btn-right" for="logicOr">Mindestens eine Zutat</label>
                        <!-- </div> -->
                    </div>
                    <div id="selectedIngredients" class="col-12 mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="container container-fluid my-5">

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <div class="w-100 position-relative">

                    <!-- <button class="btn btn-primary" type="button" id="searchBtn">Suchen</button> -->
                </div>

            </div>
            <div class="col-12 col-md-6">
            </div>
        </div>
        <div id="results" class="row g-3"></div>
        <div id="pagination" class="mt-4"></div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
    <!-- jQuery für AJAX -->
    <script src="js/brot.js"></script>
    <script>indexReady();</script>
</body>

</html>