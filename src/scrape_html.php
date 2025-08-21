<?php
ini_set('memory_limit', '4096M');
set_time_limit(600);

setlocale(LC_TIME, 'de_DE.UTF-8');

// Datenbankverbindungsinformationen
$servername = "127.0.0.1";
$username = "brot";
$password = "brot";
$dbname = "brot";


// Erstelle eine Verbindung zur MySQL-Datenbank
$conn = new mysqli($servername, $username, $password, $dbname);

// Überprüfe die Verbindung
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}
$max = rand(36, 127);
$sql = "SELECT uebersicht_id, url FROM uebersicht u WHERE u.html_id IS NULL ORDER BY RAND() LIMIT $max";
$result = $conn->query($sql);

$updateSql = "UPDATE uebersicht SET html_id = ? WHERE uebersicht_id = ?";
$stmtUpdate = $conn->prepare($updateSql);

$insertSql = "INSERT INTO html SET html = ?";
$stmtInsert = $conn->prepare($insertSql);

// Überprüfe, ob das Statement erfolgreich vorbereitet wurde
if ($stmtUpdate === false) {
    die("Fehler beim Vorbereiten des Update-Statements: " . $conn->error);
}
if ($stmtInsert === false) {
    die("Fehler beim Vorbereiten des Insert-Statements: " . $conn->error);
}
// Verarbeite jede URL
$i=0;
while ($row = $result->fetch_assoc()) {
    $uebersichtId = $row['uebersicht_id'];
    $url = $row['url'];

    // 2. Den HTML-Inhalt der URL herunterladen
    $htmlContent = @file_get_contents($scrapeBaseUrl . $url);

    // Überprüfe, ob der Inhalt erfolgreich heruntergeladen wurde
    if ($htmlContent === false) {
        echo "Fehler beim Herunterladen des HTML-Inhalts von der URL: $url \n";
        continue; // Fahre mit der nächsten URL fort
    }

    // 3. Den HTML-Inhalt in die MySQL-Datenbank speichern
    $stmtInsert->bind_param("s", $htmlContent);

    if ($stmtInsert->execute() === true) {
        $htmlId = $stmtInsert->insert_id;
        $stmtUpdate->bind_param("ii", $htmlId, $uebersichtId);
        if ($stmtUpdate->execute() === true) {
            // echo "HTML-Inhalt erfolgreich gespeichert für ID: $uebersichtId\n";
        } else {
            echo "Fehler beim Speichern der HTML-ID für ID $uebersichtId: " . $stmtUpdate->error . "\n";
        }
    } else {
        echo "Fehler beim Speichern des HTML-Inhalts für ID $uebersichtId: " . $stmtInsert->error . "\n";
    }
    if ($i++) echo ".";
    if ($i % 10 == 0) echo "$htmlId\n";
    usleep(rand(1000, 2000000)); // Schlaf für 1-2000 ms
}

echo " done!\n\n";
// Schließe das Resultat, das Statement und die Verbindung
$result->free();
$stmtUpdate->close();
$conn->close();
