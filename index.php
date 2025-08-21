<?php
include_once 'db.inc.php';
include_once 'functions.inc.php';
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

$schema = json_decode(file_get_contents('brot_schema_v1.json'), true);
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
<html lang="de" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brotrezepte-Suche</title>
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
    <style>
        .loading {
            display: none;
        }

        .card {
            transition: transform 0.2s;
            height: 100%;
        }

        .card:hover {
            transform: scale(1.02);
        }

        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Select2 bootstrap-5 theme override for dark mode */
        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection {
            background-color: transparent !important;
            border: 1px solid #495057;
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dee2e6' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #dee2e6 !important;
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice {
            color: #dee2e6 !important;
            border: 1px solid var(--bs-gray-600);
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered .select2-selection__placeholder {
            color: #dee2e6 !important;
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field {
            background-color: transparent !important;
            color: #dee2e6 !important;
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown {
            color: #dee2e6 !important;
            background-color: #212529 !important;
            border: 1px solid #495057;
        }

        html[data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option[role=group] .select2-results__group {
            color: var(--bs-secondary-color) !important;
        }

        .select2-selection__choice__remove {
            margin-right: .65em !important
        }

        .select2-selection--multiple--custom.select2-selection--custom {
            border: 0 !important;
            padding: 0 !important;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice:hover {
            cursor: pointer;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice:hover .select2-selection__choice__remove {
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") 50%/.75rem auto no-repeat !important
        }

        .select2-results__group {
            padding: .375rem .75rem;
            display: inline-block;
            width: 100%;
            background: var(--bs-highlight-bg);
            text-transform: uppercase;
            color: var(--bs-highlight-color) !important;
        }

        /* Typeahead.js styling */
        .tt-menu {
            background: #212529;
            border: 1px solid #495057;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            z-index: 1000;
        }

        .tt-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            color: #dee2e6;
        }

        .tt-suggestion:hover,
        .tt-suggestion.tt-cursor {
            background: #343a40;
        }

        .twitter-typeahead {
            width: 100%
        }
    </style>
</head>

<body>
    <div class="container my-5">
        <h1 class="mb-4">Brotrezepte suchen</h1>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control"
                        placeholder="Suche nach Rezepten (z.B. 'Weizenbrot' oder 'Roggen')..." aria-label="Suche">
                    <!-- <button class="btn btn-primary" type="button" id="searchBtn">Suchen</button> -->
                </div>
            </div>
            <div class="col-12 col-md-6">
                <select id="ingredientFilter" class="form-select" multiple aria-label="Zutaten auswählen">
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
                <div id="ingredientLogic" class="col-12 mt-3 invisible">
                    <div class="form-check form-check-inline">
                        <input type="radio" name="ingredientLogic" value="AND" class="form-check-input" id="logicAnd"
                            checked>
                        <label class="form-check-label" for="logicAnd">Alle Zutaten</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="ingredientLogic" value="OR" class="form-check-input" id="logicOr">
                        <label class="form-check-label" for="logicOr">Mindestens eine Zutat</label>
                    </div>
                </div>
                <div id="selectedIngredients" class="col-12 mt-3"></div>
            </div>
        </div>
        <div id="loading" class="loading text-muted text-center mb-3">🔄 Suche läuft...</div>
        <div id="results" class="row g-3"></div>
        <div id="pagination" class="mt-4"></div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
    <!-- jQuery für AJAX -->
    <script src="brot.js"></script>
    <script>
        $(document).ready(function () {
            let debounceTimer;
            let page = 1;
            const perPage = <?php echo $perPage; ?>;

            // Select2 initialisieren
            $('#ingredientFilter').select2({
                theme: 'bootstrap-5',
                placeholder: "Zutaten auswählen...",
                allowClear: true,
                selectionAdapter: $.fn.select2.amd.require("select2/selection/customSelectionAdapter"),
                selectionContainer: $('#selectedIngredients')
                // dropdownParent: $('.container')
            });

            // Bloodhound für Typeahead.js
            var recipes = new Bloodhound({
                datumTokenizer: function (d) {
                    return Bloodhound.tokenizers.whitespace(d.name);
                },
                queryTokenizer: Bloodhound.tokenizers.whitespace,
                remote: {
                    url: 'suggest.php?q=%QUERY',
                    wildcard: '%QUERY',
                    transform: function (response) {
                        console.log('Bloodhound response:', response);

                        // Optional: auf 50 Vorschläge begrenzen
                        return Array.isArray(response) ? response.slice(0, 50) : [];
                    }
                }
            });

            // Initialisieren (async nötig bei Bloodhound 0.11+)
            recipes.initialize();

            // Typeahead initialisieren
            $('#searchInput').typeahead({
                hint: true,
                highlight: true,
                minLength: 1
            }, {
                name: 'recipes',
                display: 'name',      // Feld für die Anzeige
                source: recipes,      // Bloodhound direkt als Source
                limit: 50,            // Limit größer setzen als maximal erwartete Ergebnisse
                templates: {
                    empty: '<div class="tt-suggestion p-2">Keine Vorschläge gefunden</div>',
                    suggestion: function (data) {
                        return '<div class="tt-suggestion p-2">' + data.name + '</div>';
                    }
                }
            }).on('typeahead:select', function (event, suggestion) {
                console.log('Ausgewählt:', suggestion);
                $('#searchInput').val(suggestion.name); // nur den String ins Feld
                performSearch(); // Deine Suchfunktion
            });

            $('#ingredientFilter').on('change.select2', function (e) {
                if ($('#ingredientFilter').val().length > 1) {
                    console.log('Multiple ingredients selected');
                    $('#ingredientLogic').removeClass("invisible");
                } else {
                    console.log('Single ingredient selected');
                    $('#ingredientLogic').addClass("invisible");
                }
            });
            $('#searchInput, #ingredientFilter, input[name="ingredientLogic"]').on('input change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(performSearch, 300);
            });

            $('#searchBtn').click(performSearch);

            $('#pagination').on('click', 'a.page-link', function (e) {
                e.preventDefault();
                page = parseInt($(this).data('page'));
                performSearch();
            });

            function escapeRegExp(str) {
                // aus MDN
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }
            function markTerms(text, query) {
                if (!text || !query) return text;
                const words = query.trim().split(/\s+/);
                const escaped = words
                    .filter(w => w && w.length)      // leere raus
                    .map(escapeRegExp)
                    .join('|');
                const regex = new RegExp(`(${escaped})`, 'giu'); // g=global, i=case-insens, u=Unicode
                return text.replace(regex, '<mark>$1</mark>');
            }

            function createSnippet(text, query, maxLength = 100) {
                if (!text || !query) return text.substring(0, maxLength) + '...';
                const words = query.trim().split(/\s+/);
                const escaped = words
                    .filter(w => w && w.length)      // leere raus
                    .map(escapeRegExp)
                    .join('|');
                const regex = new RegExp(`(${escaped})`, 'giu'); // g=global, i=case-insens, u=Unicode
                const match = text.match(regex);
                if (!match) return text.substring(0, maxLength) + '...';
                const pos = text.toLowerCase().indexOf(match[0].toLowerCase());
                const start = Math.max(0, pos - maxLength / 2);
                let snippet = text.substring(start, start + maxLength);
                if (start > 0) snippet = '...' + snippet;
                if (start + maxLength < text.length) snippet += '...';
                return snippet.replace(regex, '<mark>$1</mark>');
            }

            function performSearch() {
                const query = $('#searchInput').val().trim();
                const ingredients = $('#ingredientFilter').val() || [];
                const logic = $('input[name="ingredientLogic"]:checked').val() || 'OR';
                if (query.length < 4 && ingredients.length === 0) {
                    $('#results').html('<div class="alert alert-info" role="alert">Bitte mindestens 4 Zeichen oder eine Zutat auswählen.</div>');
                    $('#loading').hide();
                    $('#pagination').empty();
                    return;
                }

                $('#loading').show();
                $('#results').empty();
                $('#pagination').empty();

                console.log('Sending AJAX:', { q: query, ingredients: ingredients, logic: logic, page: page, per_page: perPage });

                $.ajax({
                    url: 'search.php',
                    type: 'POST',
                    data: { q: query, ingredients: ingredients, logic: logic, page: page, per_page: perPage },
                    dataType: 'json',
                    success: function (response) {
                        $('#loading').hide();
                        if (response.error) {
                            $('#results').html('<div class="alert alert-danger" role="alert">' + response.error + '</div>');
                            return;
                        }
                        if (response.total === 0) {
                            $('#results').html('<div class="alert alert-info" role="alert">Keine Ergebnisse gefunden.</div>');
                            return;
                        }

                        response.results.forEach(function (item) {
                            const card = `
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                    <div class="card shadow-sm" aria-labelledby="card-title-${item.uebersicht_id}">
                                        ${item.image ? `<img src="${item.image}" class="card-img-top" alt="${item.name}" style="max-height: 150px; object-fit: cover;">` : ''}
                                        <div class="card-body">
                                            <h5 class="card-title" id="card-title-${item.uebersicht_id}">${markTerms(item.name, query)}</h5>
                                            <h6 class="card-subtitle mb-2 text-muted">${markTerms(item.type, query) || 'Unbekannt'}</h6>
                                            <p class="card-text flex-grow-1">${item.snippet ? createSnippet(item.snippet, query) : ''}</p>
                                            <div class="d-flex justify-content-end">
                                                <a href="detail.php?id=${item.uebersicht_id}" class="btn btn-outline-primary btn-sm">Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                            $('#results').append(card);
                        });

                        const totalPages = Math.ceil(response.total / perPage);
                        let pagination = '<nav aria-label="Seitennavigation der Suchergebnisse"><ul class="pagination justify-content-center">';
                        if (page > 1) {
                            pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Vorherige</a></li>`;
                        }
                        for (let i = 1; i <= totalPages; i++) {
                            pagination += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                        }
                        if (page < totalPages) {
                            pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Nächste</a></li>`;
                        }
                        pagination += '</ul></nav>';
                        $('#pagination').html(pagination);
                    },
                    error: function (xhr, status, error) {
                        $('#loading').hide();
                        $('#results').html('<div class="alert alert-danger" role="alert">Fehler bei der Suche: ' + (xhr.statusText || 'Unbekannter Fehler') + '</div>');
                        console.error('AJAX error:', status, error, xhr.responseText);
                    }
                });
            }
        });
    </script>
</body>

</html>