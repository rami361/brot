<?php
setlocale(LC_ALL, 'de_DE.UTF-8') ?: exit("Error: Could not set locale de_DE.UTF-8\n");
mb_internal_encoding("UTF-8");

// Datenbankverbindungsinformationen
$servername = "127.0.0.1";
$username = "brot";
$password = "brot";
$dbname = "brot";

$scrapeBaseUrl = "https://www.ploetzblog.de";
$scrapeUrl = "https://www.ploetzblog.de/rezepte/alle-rezepte?sort=newest,1&sheet=%page%";

