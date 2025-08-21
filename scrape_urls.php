<?php

// Datenbankverbindungsinformationen
$servername = "127.0.0.1";
$username = "brot";
$password = "brot";
$dbname = "brot";

$pagesFromTo = [32,33];


// Erstelle eine Verbindung zur MySQL-Datenbank
$conn = new mysqli($servername, $username, $password, $dbname);

// Überprüfe die Verbindung
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// SQL-Insert-Statement vorbereiten
$sql = "REPLACE INTO uebersicht (websites_id, url) VALUES (1, ?)";

// Bereite das Statement vor
$stmt = $conn->prepare($sql);

// Überprüfe, ob das Statement erfolgreich vorbereitet wurde
if ($stmt === false) {
    die("Fehler beim Vorbereiten des Statements: " . $conn->error);
}

for ($i = $pagesFromTo[0]; $i <= $pagesFromTo[1]; $i++) {
    $scrapeUrlPage = str_replace('%page%', $i, $scrapeUrl);
    echo "\n\n== [$i/{$pagesFromTo[1]}] URL: $scrapeUrlPage ==\n\n";
    $htmlContent = @file_get_contents($scrapeUrlPage);
    $dom = new DOMDocument();
    @$dom->loadHTML($htmlContent);
    $xpath = new DOMXPath($dom);

    $rezepte = $xpath->query('//div[@class="we2p-result__content"]');

    foreach ($rezepte as $rezept) {
        $urls = $xpath->query('.//a/@href', $rezept);
        if ($urls->length === 0) continue; // Überspringe, wenn kein Link gefunden wurde
        $url = $urls->item(0)->nodeValue;
        // $names = $xpath->query('.//a/h4', $rezept);
        // if ($names->length === 0) continue; // Überspringe, wenn kein Name gefunden wurde
        // $name = trim($names->item(0)->textContent);
        // $dates = $xpath->query('.//div/p/span', $rezept);
        // if ($dates->length === 0) continue; // Überspringe, wenn kein Datum gefunden wurde
        // $datum = parseDate(trim($dates->item(0)->textContent));
        // Debug-Ausgabe
        echo "Verarbeite URL: $url -- ";
        $stmt->bind_param("s", $url);
        if ($stmt->execute() === true) {
            echo "OK\n";
        } else {
            exit("\n\nFehler: " . $stmt->error);
        }
    }
}

// Schließe das Statement und die Verbindung
$stmt->close();
$conn->close();

function parseDate($dateString) {
    // Datum mit DateTime parsen
    $date = DateTime::createFromFormat('d. F Y', $dateString);
    // Überprüfen, ob das Datum korrekt geparst wurde
    if ($date === false) exit("Fehler beim Parsen des Datums: ". $dateString . "\n");
    // In ISO-Format (YYYY-MM-DD) umwandeln
    return $date->format('Y-m-d');
}