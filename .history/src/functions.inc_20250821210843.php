<?php
setlocale(LC_ALL, 'de_DE.UTF-8') ?: exit("Error: Could not set locale de_DE.UTF-8\n");
mb_internal_encoding("UTF-8");


function prepareSql($sql) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error: " . $conn->error);
    }
    return $stmt;
}

function selectSql($sql) {
    global $conn;
    $result = $conn->query($sql);
    if ($result === false) {
        die("Error: " . $conn->error);
    }
    return $result;
}

function lastInsertId() {
    global $conn;
    return $conn->insert_id;
}

/**
 * Bereinigt eine Suchanfrage von Sonderzeichen und filtert Wörter nach Mindestlänge.
 *
 * @param string $query Die rohe Suchanfrage.
 * @param int $minLength Mindestlänge der Wörter (Standard: 3).
 * @return string Die bereinigte Suchanfrage.
 */
function cleanSearchQuery($query, $minLength = 3) {
    // Entferne Sonderzeichen
    $query = preg_replace('/[^a-zA-Z0-9\s]/', '', trim($query));
    // Filtere Wörter nach Mindestlänge
    $words = array_filter(explode(' ', $query), function($word) use ($minLength) {
        return strlen($word) >= $minLength;
    });
    return implode(' ', $words);
}

/**
 * Escaped einen Wert sicher für PDO-Abfragen.
 *
 * @param PDO $conn Die PDO-Datenbankverbindung.
 * @param string $value Der zu escapende Wert.
 * @return string Der escapte Wert.
 */
function safeSqlValue($conn, $value) {
    return $conn->quote(trim($value));
}

// Cache für das Schema (vermeidet mehrfaches Laden)
$schemaCache = null;

/**
 * Wandelt einen normalisierten Zutaten-Pfad in lesbaren Text um.
 * @param string $normalizedPath Normalisierter Pfad (z.B. 'grundzutaten.mehl.weizen.550')
 * @param string $schemaFile Pfad zur Schema-Datei (z.B. 'brot_schema_v1.json')
 * @return string Lesbarer Name (z.B. 'Weizenmehl 550') oder Fallback
 */
function mapNormalizedToReadable(string $normalizedPath, $schemaFile = 'brot_schema_v1.json') {
    global $schemaCache;
    $cacheKey = 'brot_schema_v1';
    if ($schemaCache === null) {
        // if (extension_loaded('apcu') && apcu_exists($cacheKey)) {
            // $schemaCache = apcu_fetch($cacheKey);
        // } else {
            if (!file_exists($schemaFile)) {
                error_log("Schema-Datei nicht gefunden: $schemaFile");
                return $normalizedPath;
            }
            $schemaContent = file_get_contents($schemaFile);
            $schemaCache = json_decode($schemaContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Fehler beim Parsen des Schemas: " . json_last_error_msg());
                return $normalizedPath;
            }
            // if (extension_loaded('apcu')) {
            //     apcu_store($cacheKey, $schemaCache, 3600); // Cache für 1 Stunde
            // }
        // }
    }
    // Pfad in Segmente aufteilen
    $segments = explode('.', $normalizedPath);
    $current = $schemaCache['zutaten'];

    // Rekursiv durch das Schema navigieren
    foreach ($segments as $segment) {
        if (!isset($current[$segment])) {
            error_log("Ungültiger Pfad-Segment: $segment in $normalizedPath");
            return $normalizedPath; // Fallback: Original-Pfad
        }
        $current = $current[$segment];

    }

    // Lesbaren Namen zurückgeben
    return isset($current['name'][0]) ? $current['name'][0] : $normalizedPath;
}

/**
 * Wandelt ein Array von Zutaten (aus rezepte.rezept) in lesbare Form um.
 * @param array $ingredients Array von Zutaten (z.B. [{'mehl.weizen.550': {...}}, ...])
 * @param string $schemaFile Pfad zur Schema-Datei
 * @return array Lesbare Zutaten (z.B. ['Weizenmehl 550' => [...], ...])
 */
function mapIngredientsToReadable(array $ingredients, $schemaFile = '../schema/brot_schema_v1.json') {
    $readable = [];
    foreach ($ingredients as $ingredient) {
        $key = key($ingredient);
        if ($key === "referenz") {
            $normalized = $ingredient[$key]['ref']; // For "referenz" use ref (ingredient matched by regex)
        } else {
            $normalized = mapNormalizedToReadable($key, $schemaFile);
        }
        $readable[$normalized] = $ingredient[$key];
    }
    return $readable;
}

// Function to convert dot-notation to nested array using the last key as value
function addToArray(string $path, array $array): array {
    $keys = explode('.', $path);
    $value = array_pop($keys); // Use the last key as the value
    $array = array_merge_recursive($array, array_reduce(
        array_reverse($keys),
        fn($carry, $key) => [$key => $carry],
        $value
    ));
    return $array;
}

// Function to convert dot-notation path with key and value to nested array
function addIngredientToArray(array &$arr, string $path, string $key, $value): void {
    $keys = explode('.', $path); // Split dot-notation into array
    if (!empty($key)) $keys[] = $key; // Append the final key
    $arr = array_merge_recursive($arr,  array_reduce(
        array_reverse($keys),
        fn($carry, $k) => [$k => $carry],
        $value
    ));
}

// Function to recursively traverse the JSON and collect paths for "name" values
function extractNamePaths($data, $currentPath = '', &$result = []) {
    // If $data is an array or object
    if (is_array($data) || is_object($data)) {
        foreach ($data as $key => $value) {
            // If the key is "name" and value is an array, process its values
            if ($key === 'name' && is_array($value)) {
                foreach ($value as $name) {
                    $result[$name] = $currentPath;
                }
            } else {
                $newPath = $currentPath ? "$currentPath.$key" : $key;
                // Recursively process nested arrays or objects
                extractNamePaths($value, $newPath, $result);
            }
        }
    }
    return $result;
}

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

function extractRegexPaths($data, $currentPath = '', &$result = []) {
    // If $data is an array or object
    if (is_array($data) || is_object($data)) {
        foreach ($data as $key => $value) {
            // If the key is "name" and value is an array, process its values
            if ($key === 'regex' && is_array($value)) {
                foreach ($value as $regex) {
                    $result[$regex] = $currentPath;
                }
            } else {
                $newPath = $currentPath ? "$currentPath.$key" : $key;
                // Recursively process nested arrays or objects
                extractRegexPaths($value, $newPath, $result);
            }
        }
    }
    return $result;
}


function getXPathValue(DOMXPath $xpath, string $query, DOMNode|null $node): ?string {
    $resultString = getXPathValueOptional($xpath, $query, $node);
    if ($resultString === null || strlen($resultString) === 0) {
        $name = getXPathValue($xpath, '//h1', null);
        die("XPath value query failed or no elements found. Query: $query\n\nName: $name");
    }
    return $resultString;
}

function getXPathValueOptional(DOMXPath $xpath, string $query, DOMNode|null $node): ?string {
    $result = $xpath->query($query, $node);
    if ($result === false || $result->length === 0) {
        return null; // Return null if the query fails or no elements are found
    }
    $nodeValue = $result->item(0)->nodeValue;
    // var_export(trimWhitespaces($nodeValue)); echo "\n";
    $nodeValue = html_entity_decode($nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $nodeValue = str_replace("\xc2\xa0", " ", $nodeValue); // Ensure &nbsp; becomes a space
    return trim(trimWhitespaces($nodeValue)) ?? null;
}

function getTotalMinutesFromTime($str) {
    $totalMinutes = 0;
    if (preg_match('/(\d+):(\d+)\s/u', $str, $matches)) {
        $totalMinutes = ($matches[1] * 60) + $matches[2];
    }
    return $totalMinutes;
}

function minutesToHHMM($minutes) {
    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $minutes);
}

// function getXPathValues(DOMXPath $xpath, string $query, DOMNode $node): ?string {
//     $result = $xpath->query($query, $node);
//     if ($result === false || $result->length === 0) {
//         die("XPath values query failed or no elements found");
//     }
//     return $result->item(0)->nodeValue;
// }

function getXPathNodes(DOMXPath $xpath, string $query, DOMNode|null $node): ?DOMNodeList {
    $result = $xpath->query($query, $node);
    if ($result === false || $result->length === 0) {
        die("XPath query failed or no elements found");
    }
    return $result;
}

function parseDate($dateString) {
    $formatter = new IntlDateFormatter(
        'de_DE',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        'UTC',
        IntlDateFormatter::GREGORIAN,
        'd. MMMM y'
    );
    // setlocale(LC_ALL, locales: 'de_DE.UTF-8');
    // preg_replace('/[^\d\.\sA-Za-z]/', '#', $dateString);
    // Datum mit DateTime parsen
    $timestamp = $formatter->parse($dateString);
    //DateTime::createFromFormat('d. F Y', $dateString);
    // Überprüfen, ob das Datum korrekt geparst wurde
    if ($timestamp === false) exit("Fehler beim Parsen des Datums: `". $dateString . "`\n");
    // In ISO-Format (YYYY-MM-DD) umwandeln
    $date = new DateTime("@$timestamp");
    return $date->format('Y-m-d');
}

function stripHtmlText(string $html): string {
    // Replace </p> with double newlines
    $html = str_replace('</p>', "\n\n", $html);
    // Replace <br> (and variations) with single newline
    $html = preg_replace('/<br\s*\/?>/iu', "\n", $html);
    // Remove all remaining HTML tags
    $html = strip_tags($html);
    // Normalize multiple newlines (optional, to clean up extra newlines)
    $html = preg_replace('/\n{3,}/u', "\n\n", $html);   
    // Trim leading/trailing whitespace
    return trim($html);
}

function trimWhitespaces($str) {
    return preg_replace('/[\t\s]+/u',' ', $str);
}

function calculateMinuteDiff($lastDay, $lastTime, $currentDay, $currentTime) {
    // Basisdatum für Tag 1 und Tag 2 (beliebig, nur für Differenzberechnung)
    $start = new DateTime("2023-01-01 $lastTime")->modify("+ $lastDay days");
    $end = new DateTime("2023-01-01 $currentTime")->modify("+ $currentDay days");
    
    $interval = $start->diff($end);
    $minutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
    
    // // Korrigiere für negativen Wert (Tagesübergang)
    // if ($minutes < 0) {
    //     exit("Fehler: Negative Minutenberechnung: days:". $interval->days * 24 * 60 . " hours:".  $interval->h * 60 . " minutes:". $interval->i);
    //     $minutes += 24 * 60;
    // }
    
    return $minutes;
}

function roundWeight($weight) {
    $weight = floatval($weight);
    if ($weight < 1) return round($weight, 2);
    if ($weight < 10) return round($weight, 1);
    return round($weight);
} 