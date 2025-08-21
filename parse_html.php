<?php
ini_set('memory_limit', '4096M');
set_time_limit(600);
header('Content-Type: text/html; charset=UTF-8');

include_once 'db.inc.php';
include_once 'functions.inc.php';

function extractIngredient($xpath, $ingredientNode, $jsonData, $name, $namePaths, $regexPaths, $amount, $temp) {
    $col = [];
    $col["weight"] = 1;
    $col["name"] = 2;
    $col["percentage"] = 3;
    $col["temp"] = 0;
    if ($temp) {
        $col["temp"] = 3;
        $col["percentage"] = 4;
    }
    $ingredientWeight = $amount * getXPathValueOptional($xpath, ".//td[{$col["weight"]}]/@data-ingredient-base-weight", $ingredientNode);
    $ingredientName = getXPathValue($xpath, ".//td[{$col["name"]}]//span[1]", $ingredientNode);
    $ingredientNameInfo = getXPathValueOptional($xpath, ".//td[{$col["name"]}]//span[2]", $ingredientNode);
    $ingredientPercentageString = getXPathValueOptional($xpath, ".//td[{$col["percentage"]}]", $ingredientNode);
    $ingredientPercentage = $ingredientPercentageString ? floatval(preg_replace('/[^\d.]/u', '', str_replace(',', '.', $ingredientPercentageString))) : null;
    $ingredientTemperatureString = $col["temp"] ? getXPathValueOptional($xpath, ".//td[{$col["temp"]}]//span", $ingredientNode) : null;
    $ingredientTemperature = $ingredientTemperatureString ? floatval(preg_replace('/^(\d+)(.*)$/u', '$1', $ingredientTemperatureString)) : null;
    $ta = false;
    if (preg_match('/^([\w\s]+)\s*TA\s*(\d{3}).*$/u', $ingredientName, $matches)) {
        $ingredientName = trim($matches[1]);
        $ta = intval($matches[2]);
    }

    $ingredientPath = $namePaths[$ingredientName] ?? null;
    if (empty($ingredientPath)) {
        foreach ($regexPaths as $regex => $path) {
            if (preg_match($regex . 'u', $ingredientName)) {
                $ingredientPath = $path;
                break;
            }
        }
        if ($ingredientPath === null) {
            var_export($regexPaths);
        }
    }

    if ($ingredientPath === null) { // error handling for debug purposes
        var_export($namePaths);
        echo $name;
        echo "\nIngredient path not found for: `$ingredientName` ($ingredientWeight)\n";
        exit();
    }

    if ($ingredientPath === null) die("Ingredient path not found for: $ingredientName\n\n");

    $ingOpts = [];
    if ($ingredientWeight) $ingOpts['weight'] = $ingredientWeight;
    if ($ingredientPercentage) $ingOpts['percentage'] = $ingredientPercentage;
    if ($ingredientTemperature) $ingOpts['temperature'] = $ingredientTemperature;
    if ($ta !== false) $ingOpts['ta'] = $ta;
    if ($ingredientNameInfo) $ingOpts['info'] = $ingredientNameInfo;
     if ($ingredientPath === "referenz") $ingOpts['ref'] = $ingredientName;

    return [ $ingredientPath => $ingOpts ];
}

/* 
==============================
 GET paths from JSON
==============================
*/

// Read the JSON file
$jsonFilePath = 'brot_schema_v1.json';
if (!file_exists($jsonFilePath)) {
    die("Error: File '$jsonFilePath' not found.");
}

$jsonData = file_get_contents($jsonFilePath);
if ($jsonData === false) {
    die("Error: Failed to read file '$jsonFilePath'.");
}

// Decode JSON into a PHP array
$allNames = json_decode($jsonData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON Error: " . json_last_error_msg());
}

// Extract paths for "name" values
$namePaths = extractNamePaths($allNames['zutaten']);
$regexPaths = extractRegexPaths($allNames['zutaten']);


/* 
==============================
 GET html from DB
==============================
*/

$resultsHtml = selectSql(
    "SELECT u.uebersicht_id as uid, h.html as html FROM uebersicht AS u JOIN html AS h ON u.html_id = h.html_id WHERE u.rezepte_id IS NULL AND u.ignore = 0 ORDER BY uid"
);

$stmtInsert = prepareSql("INSERT INTO rezepte (authoren_id, name, datum, rezept) VALUES (1, ?, ?, ?)");
$stmtUpdate = prepareSql("UPDATE uebersicht SET rezepte_id = ? WHERE uebersicht_id =?");
$stmtUpdateIgnore = prepareSql("UPDATE uebersicht u SET `ignore` = 1 WHERE u.uebersicht_id = ?");

/* 
==============================
 PARSE html
==============================
*/

foreach ($resultsHtml as $row) {
    // Parse the HTML and extract relevant information
    // For example, using DOMDocument or a regex to find specific elements
    // Then bind the extracted values to the prepared statement

    $html = $row['html'];
    // $html = file_get_contents('rezept.html');
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $name = getXPathValue($xpath, '//h1', null);

    $noLink = getXPathValueOptional($xpath, './/div[contains(@class, "we2p-pb-recipe__ingredients")]', null);
    if ($noLink === null) {
        echo "No recipe found: $name";
        $stmtUpdateIgnore->bind_param("i", $row['uid']);
        $stmtUpdateIgnore->execute();
        continue;
    }

    $sorte = getXPathValueOptional($xpath, '//h2[contains(@class, "subheader")]', null);
    $titleImage = getXPathValueOptional($xpath, '//div[contains(@class, "module-mt-[7px]")]//img[contains(@class, "we2p-pb-recipe__thumbnail-image")]/@src', null);

    $description_short = getXPathValueOptional($xpath, '//div[contains(@class, "vg-wort-text") and contains(@class, "we2p-pb-recipe__description")]//div[1]', null);
    $description_long = getXPathValue($xpath, '//div[contains(@class, "vg-wort-text") and contains(@class, "we2p-pb-recipe__description")]//div[2]', null);

    $dateString = getXPathValue($xpath, '//div[contains(@class, "we2p-pb-recipe__description-container")]//div[contains(@class, "module-tracking-wider")]//div//span', null);
    if ($dateString === null) die("Date not found");
    $date = $dateString ? parseDate($dateString) : null;

    $amount = intval(getXPathValue($xpath, '//input[@id="recipePieceCount"]/@value', null));
    $weight = floatval(getXPathValue($xpath, '//input[@id="recipeDoughWeight"]/@value', null));

    $ingredientsNodes = getXPathNodes($xpath, '//div[contains(@class, "we2p-pb-recipe__ingredients")]//table[contains(@class, "module-font-bold")]//tr', null);

    $ingredients = [];
    $ingredientPathed = [];
    $totalWeightCalculated = 0;
    $totalPercentageMehl = 0;
    $totalWeightMehl = 0;

    // calc total weight mehl
    /*
    foreach ($ingredientsNodes as $ingredientNode) {
        $ingredientName = getXPathValue($xpath, './/td[2]//span[1]', $ingredientNode);
        $ingredientWeight = getXPathValueOptional($xpath, './/td[1]/@data-ingredient-base-weight', $ingredientNode);
        $ta = false;
        if (preg_match('/^([\w\s]+)\s*TA\s*(\d{3}).*$/u', $ingredientName, $matches)) {
            $ingredientName = trim($matches[1]);
            $ta = intval($matches[2]);
            // var_export($matches);
        }
        if (preg_match('/^grundzutaten\.(mehl|getreideprodukte)/u', $namePaths[$ingredientName])) {
            $totalWeightMehl += $ingredientWeight ?? 0;
        }
        if ($ta !== false && $ingredientWeight) {
            // if (!preg_match('/^alt/u', $ingredientName, $matches)) {
                $taFactor = 1 - ($ta - 100) / $ta;
                $totalWeightMehl += $ingredientWeight * $taFactor ?? 0;
            // }
        }
    }
    */
    foreach ($ingredientsNodes as $ingredientNode) {
        $ingredient = extractIngredient($xpath, $ingredientNode, $jsonData, $name, $namePaths, $regexPaths, $amount, false);
        $ingredientValues = reset($ingredient);
        $ingredientKey = key($ingredient);
        $totalWeightCalculated += $ingredientValues["weight"] ?? 0;
/*
        if (preg_match('/^grundzutaten\.(mehl|getreideprodukte)/u', $namePaths[$ingredientName])) {
            $totalPercentageMehl += floatval($ingredientValues["percentage"] ?? 0);
            $ingredient[$ingredientKey] = ($ingredientValues["weight"] ?? 0 / $totalWeightMehl) * 100;
        } elseif (!empty($ingredientValues["ta"]) && !empty($ingredientValues["weight"])) {
            // if (!preg_match('/^alt/u', $ingredientName, $matches)) {
                $taFactor = 1 - (($ingredientValues["ta"] - 100) / $ingredientValues["ta"]);
                $totalPercentageMehl += (floatval($ingredientValues["percentage"]) ?? 0) * $taFactor;
                $ingredient[$ingredientKey]['percentage_by_mehl'] = (($ingredientValues["weight"] * $taFactor) / $totalWeightMehl) * 100;
                echo "TA :: ";
            // }
        }
*/
        $ingredientPathed[] = $ingredient;

        // addIngredientToArray($ingredients, $ingredientPath, '', []);
        // if ($ingredientWeight) addIngredientToArray($ingredients, $ingredientPath, 'weight', $ingredientWeight);
        // if ($ingredientPercentage) addIngredientToArray($ingredients, $ingredientPath, 'percentage', $ingredientPercentage);
        // if ($ta !== false) addIngredientToArray($ingredients, $ingredientPath, 'TA', $ta);
        // if ($ingredientNameInfo) addIngredientToArray($ingredients, $ingredientPath, 'info', $ingredientNameInfo);

        // if (!$ingredientWeight && !$ingredientPercentage){
        //     var_export($ingredients); echo "$ingredientNameInfo xxxxx";
        //     exit();
        // }
    }

    // ================
    //   PREP TIME
    // ================

    $totalPrepTimeString = getXPathValueOptional($xpath, '//div[contains(@class, "module-col-span-12")]//p[contains(@class, "module-mt-4")]//span[2]', null);
    if (!preg_match('/^((\d+) Stunden)?\s*((\d+) Minuten)?$/u', $totalPrepTimeString, $prepTimes)) {
        exit("Unknown time string: `$totalPrepTimeString` ($name)");
    } else {
        $prepMinutes = intval($prepTimes[2] ?? 0) * 60 + intval($prepTimes[4] ?? 0);
        $prepTime = minutesToHHMM($prepMinutes);

    }

    $prepStepsNodes = getXPathNodes($xpath, '//div[contains(@class, "we2p-pb-page-breaker")]//div[2]//table[contains(@class, "module-w-full")]//tr', null);

    $checkIfDays = getXPathValueOptional($xpath, './/td[3]', $prepStepsNodes->item(0));
    if (empty($checkIfDays)) { // only 2 columns, no days
        $stepCol[1] = false;
        $stepCol[2] = 1;
        $stepCol[3] = 2;
    } else {
        $stepCol[1] = 1;
        $stepCol[2] = 2;
        $stepCol[3] = 3;
    }

    $currentDay = $lastDay = 1;
    $currentTime = $lastTime = $startTime = $stepDescription = '';  
    $totalDuration = 0;
    $steps = [];

    foreach ($prepStepsNodes as $prepStepNode) {

        if ($stepCol[1]) {
            $dayString = getXPathValueOptional($xpath, ".//td[{$stepCol[1]}]", $prepStepNode);
            if ($dayString && preg_match('/Tag (\d+)/u', $dayString, $dayMatches)) {
                $day = intval($dayMatches[1]);
                if ($day > 80 || $day < 1) die("Invalid day: `$day` ($name)");
                $currentDay = intval($dayMatches[1]);
            }
        } else {
            $currentDay = 1;
        }

        $timeString = getXPathValueOptional($xpath, ".//td[{$stepCol[2]}]//input/@value", $prepStepNode);
        if (empty($timeString)) $timeString = getXPathValue($xpath, ".//td[{$stepCol[2]}]", $prepStepNode);
        
        if (empty($startTime)) $startTime = preg_replace('/^(\d+:\d+).*$/u', "$1", $timeString); // start time of recipe

        if (preg_match('/(\d+:\d+)/u', $timeString, $timeMatches)) {
            $currentTime = $timeMatches[1];
            if (!empty($lastTime)) { // first entry
                $currentDuration = calculateMinuteDiff($lastDay, $lastTime, $currentDay, $currentTime);
                $totalDuration += $currentDuration;
            }
            $lastDay = $currentDay;
            $lastTime = $currentTime;
        }

        if ($stepDescription) {
            $steps []= [
                'duration' => $currentDuration,
                'hhmm' => minutesToHHMM($currentDuration),
                'description' => trimWhitespaces($stepDescription),
            ];
        }

        $stepDescription = getXPathValue($xpath, ".//td[{$stepCol[3]}]", $prepStepNode);
    }

    if ($prepMinutes !== $totalDuration) {
        die("Wrong prep minutes! html: `$prepMinutes`, calc: $totalDuration ($name)");
    }

    // ================
    //   INSTRUCTIONS
    // ================
    
    $instructionParts = [];
    $instPartsNodes = getXPathNodes($xpath, '//div[contains(@class, "module-break-inside-avoid") and contains(@class, "vg-wort-text")]', null);

    foreach ($instPartsNodes as $instPartNode) {
        $instPartTitle = getXPathValue($xpath, './/h4//span', $instPartNode);
        // $substractTA = in_array($instPartTitle, [
        //     'Füllung',
        //     'Mischung',
        //     'Streusel',
        // ]) ? true : false;
        $instPartIngredientsNodes = getXPathNodes($xpath, './/table//tr', $instPartNode);
        $instIngredientPathed = [];
        foreach ($instPartIngredientsNodes as $ingredientNode) {
            $ingredient = extractIngredient($xpath, $ingredientNode, $jsonData, $name, $namePaths, $regexPaths, $amount, true);
            $instIngredientPathed[] = $ingredient;
        }

        $instPartStepNodes = getXPathNodes($xpath, './/div//div[contains(@class, "module-flex")]', $instPartNode);
        $instSteps = ['beginner' => [], 'expert' => []];
        $beginnerNr = $beginnerNrLast = $expertNr = $expertNrLast = 0;

        foreach ($instPartStepNodes as $instStepNode) {
            $beginnerNr = getXPathValue($xpath, './/div[contains(@class, "we2p-step-count")]/@data-step-count-beginner', $instStepNode);
            $expertNr = getXPathValue($xpath, './/div[contains(@class, "we2p-step-count")]/@data-step-count-expert', $instStepNode);
            $instruction = getXPathValue($xpath, './/p[contains(@class, "we2p-autolinker")]', $instStepNode);
            if ($beginnerNr > $beginnerNrLast) $instSteps['beginner'] []= $instruction;
            if ($expertNr > $expertNrLast) $instSteps['expert'] []= $instruction;
            $beginnerNrLast = $beginnerNr;
            $expertNrLast = $expertNr;
        }
        
        $instructionParts []= [
            'title' => $instPartTitle,
            'ingredients' => $instIngredientPathed,
            'steps' => $instSteps,
        ];
    }
    // var_export($instructionParts); echo $name;exit();

    // if (round($totalPercentageMehl, 1) == 100) {
    //     echo "Mehl 100%";
    //     continue;
    // }

    $recipe = json_encode([
        'name' => $name,
        'type' => $sorte,
        'date' => $date,
        'description_short' => stripHtmlText($description_short),
        'description_long' => stripHtmlText($description_long),
        'images' => [
            'title' => $titleImage,
            'gallery' => []
        ],
        'weight_total' => $weight * $amount,
        'weight_total_calculated' => round($totalWeightCalculated, 4),
        // 'percentage_total_mehl' => $totalPercentageMehl,
        'amount_total' => $amount,
        'ingredients' => $ingredientPathed,
        'prep_time' => $prepTime,
        'prep_minutes' => $prepMinutes,
        'start_time' => $startTime,
        'preparation_times' => $steps,
        'instruction_steps' => $instructionParts,
    ]);
    // var_export($recipe);
    // var_export(json_decode($recipe, true));
    // exit();

    // $stmtInsert = prepareSql("INSERT INTO rezepte (authoren_id, name, datum, rezept) VALUES (1, ?, ?, ?)");
    // $stmtUpdate = prepareSql("UPDATE uebersicht SET rezepte_id = ? WHERE uebersicht_id =?");

    $stmtInsert->bind_param("sss", $name, $date, $recipe);
    $stmtInsert->execute();
    
    $lastId = lastInsertId();

    $stmtUpdate->bind_param("ii", $lastId, $row['uid']);
    $stmtUpdate->execute();

    // exit($row['uid']);
}
echo "\nDone!";
// Output the result
// print_r($namePaths);