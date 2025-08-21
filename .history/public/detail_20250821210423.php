<?php
include_once '../config/db.inc.php'; // MySQLi-Verbindung
include_once '../src/functions.inc.php'; // Helper-Funktionen

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Ungültige ID");
}

$sql = "SELECT r.rezept, u.name, u.type, u.description_long, u.description_short
        FROM rezepte r
        JOIN uebersicht u ON r.rezepte_id = u.rezepte_id
        WHERE u.uebersicht_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bindValue($id);
$stmt->execute();
$result = $stmt->fetchAll();

if (!$result) {
    die("Rezept nicht gefunden");
}

$recipe = json_decode($result['rezept'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo '<div class="alert alert-danger" role="alert">Fehler beim Parsen des Rezepts. Bitte versuche es später erneut.</div>';
    exit;
}

// Zutaten in lesbare Form umwandeln
$readableIngredients = mapIngredientsToReadable($recipe['ingredients']);
foreach ($recipe['instruction_steps'] as $step) {
    $readableStepIngredients[] = mapIngredientsToReadable($step['ingredients']);
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($result['name']); ?></title>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.5/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.5/dist/js/select2.min.js"></script>
    <!-- Select2 Custom Adapter https://github.com/andreivictor/select2-customSelectionAdapter -->
    <link rel="stylesheet" href="css/select2.customSelectionAdapter.css" />
    <script src="js/select2.customSelectionAdapter.js"></script>
    <!-- Select2 Theme https://github.com/apalfrey/select2-bootstrap-5-theme -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Typeahead -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js"></script>
    <link href="brot.css" rel="stylesheet">
</head>

<body class="bg-dark bg-gradient">
    <div class="container my-5">
        <div class="card">
                    <?php if (!empty($recipe['images']['title'])): ?>
                        <img src="<?php echo htmlspecialchars(preg_replace('/^(.*)\?.*/',"$1",$recipe['images']['title'])); ?>" alt="Rezeptbild"
                            class="card-img-top img-fluid mb-3 XXXfloat-end XXXms-3" style="max-height: 200px; object-fit: cover;">

                    <?php endif; ?>
            <div class="card-img-overlay">
                    <h1 class="card-title px-3"><?php echo htmlspecialchars($result['name']); ?></h1>
            </div>
            <div class="card-body">
                <div class="row px-3">
                    <h5 class="card-subtitle mb-2 text-muted">
                        <?php echo htmlspecialchars($result['type'] ?? 'Unbekannt'); ?>
                    </h5>
                    <p><?php echo nl2br(htmlspecialchars($result['description_short'])); ?></p>
                </div>
                <div class="row px-3">
                    <div class="col-6 pt-1">
                        <p><?php echo nl2br(htmlspecialchars($result['description_long'])); ?></p>
                    </div>
                    <div class="col-6">
                        <!-- <h3>Zutaten</h3> -->

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <!-- <thead>
                                    <tr>
                                        <th>Gewicht</th>
                                        <th>Zutat</th>
                                        <th>Prozent</th>
                                    </tr>
                                </thead> -->
                                <tbody>
                                    <?php foreach ($readableIngredients as $name => $opts): ?>
                                        <tr class="">
                                            <td
                                                class="<?php if ($opts === end($readableIngredients))
                                                    echo "border-bottom-0"; ?>">
                                                <?php echo isset($opts['weight']) ? htmlspecialchars(roundWeight($opts['weight'])) . "\u{202F}g" : '-'; ?>
                                            </td>
                                            <td class="w-100 <?php if ($opts === end($readableIngredients))
                                                    echo "border-bottom-0"; ?>">
                                                <?php echo htmlspecialchars($name); ?>
                                                <?php echo !empty($opts['ta']) ? "TA\u{202F}" . htmlspecialchars($opts['ta']) : ''; ?>
                                                <?php echo !empty($opts['info']) ? htmlspecialchars($name) : ''; ?>
                                            </td>
                                            <td
                                                class="text-end <?php if ($opts === end($readableIngredients))
                                                    echo "border-bottom-0"; ?>">
                                                <?php echo isset($opts['percentage']) ? htmlspecialchars($opts['percentage']) . "\u{202F}%" : '-'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        </div>

                    </div>
                </div>
                <h3 class="px-3">Zubereitung</h3>
                <div class="accordion accordion-flush" id="instructionsAccordion">
                    <?php foreach ($recipe['instruction_steps'] as $index => $step): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                <button class="accordion-button px-3 <?php echo $index !== 0 ? 'collapsed' : ''; ?>"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>"
                                    aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                    aria-controls="collapse<?php echo $index; ?>">
                                    <?php echo htmlspecialchars($step['title']); ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $index; ?>"
                                class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                aria-labelledby="heading<?php echo $index; ?>" data-bs-parent222="#instructionsAccordion">
                                <div class="accordion-body px-2">
                                    <div class="container px-0">
                                        <div class="row">
                                            <div class="col-4">
                                                <div class="table-responsive">
                                                    <table class="table" style="margin-top:1px;">
                                                        <!-- <thead>
                                                            <tr>
                                                                <th>Zutat</th>
                                                                <th>Gewicht</th>
                                                                <th>Prozent</th>
                                                                <th>Temperatur</th>
                                                            </tr>
                                                        </thead> -->
                                                        <tbody>
                                                            <?php
                                                            $thisStep = array_shift($readableStepIngredients);
                                                            $lastStep = count($thisStep);
                                                            $currentStep = 0;
                                                            foreach ($thisStep as $name => $opts):
                                                                $currentStep++;
                                                                ?>
                                                                <tr>
                                                                    <td
                                                                        class="w-100 <?php if ($currentStep === $lastStep)
                                                                            echo "border-bottom-0"; ?>">
                                                                        <?php echo htmlspecialchars($name); ?>
                                                                        <?php echo !empty($opts['ta']) ? "TA\u{202F}" . htmlspecialchars($opts['ta']) : ''; ?>
                                                                        <?php echo !empty($opts['info']) ? htmlspecialchars($name) : ''; ?>
                                                                    </td>
                                                                    <td class="text-end <?php if ($currentStep === $lastStep)
                                                                            echo "border-bottom-0"; ?>">
                                                                        <?php echo isset($opts['weight']) ? htmlspecialchars(roundWeight($opts['weight'])) . "\u{202F}g" : ''; ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-end text-secondary <?php if ($currentStep === $lastStep)
                                                                            echo "border-bottom-0"; ?>">
                                                                        <?php echo isset($opts['percentage']) ? htmlspecialchars($opts['percentage']) . "\u{202F}%" : ''; ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-end <?php if ($currentStep === $lastStep)
                                                                            echo "border-bottom-0"; ?>">
                                                                        <?php echo isset($opts['temperature']) ? htmlspecialchars($opts['temperature']) . "\u{202F}°C" : ''; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-8">
                                                <ol class="list-group list-group-numbered">
                                                    <?php foreach ($step['steps']['beginner'] as $subStep => $instruction): ?>
                                                        <li
                                                            class="list-group-item d-flex justify-content-between align-items-start">
                                                            <div class="container ms-2 pe-0">
                                                                <div class="row">
                                                                    <label class="col-form-label stretched-link col-sm-11 py-0"
                                                                        for="inst<?php echo $index.$subStep ?>">
                                                                        <?php echo htmlspecialchars($instruction); ?>
                                                                    </label>
                                                                    <div class="col-sm-1">
                                                                        <input class="form-check-input me-1" type="checkbox"
                                                                            value="" id="inst<?php echo $index.$subStep ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="brot.js"></script>
</body>

</html>