<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Not authenticated.</p></div>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?navigate=addpart');
    exit();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo "<div class='content-box'><p style='color:red;'>Security validation failed.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'stats_helper.php';

parts_ensure_table($SNLDBConnection);

// Collect and validate input
$title       = trim($_POST['title'] ?? '');
$make_id     = intval($_POST['make_id'] ?? 0);
$model_id    = intval($_POST['model_id'] ?? 0) ?: null;
$year_from   = intval($_POST['year_from'] ?? 0);
$year_to     = intval($_POST['year_to'] ?? 0) ?: null;
$price       = round(floatval($_POST['price'] ?? 0), 2);
$condition   = max(0, min(5, intval($_POST['condition'] ?? 3)));
$stock       = max(1, intval($_POST['stock'] ?? 1));
$oem         = trim($_POST['oem_number'] ?? '') ?: null;
$replacement = trim($_POST['replacement_number'] ?? '') ?: null;
$description = trim($_POST['description'] ?? '') ?: null;
$visible_prv = (isset($_POST['visible_private']) && $_POST['visible_private'] == '1') ? 1 : 0;
$visible     = (isset($_POST['visible']) && $_POST['visible'] == '1') ? 1 : 0;
$for_sale    = (isset($_POST['for_sale']) && $_POST['for_sale'] == '1') ? 1 : 0;
$seller_id   = (int)$_SESSION['user_id'];

if (empty($title)) {
    echo "<div class='content-box'><p style='color:red;'>Title is required.</p><p><a href='index.php?navigate=addpart'>Back</a></p></div>";
    mysqli_close($SNLDBConnection);
    return;
}
if ($make_id <= 0) {
    echo "<div class='content-box'><p style='color:red;'>Car make is required.</p><p><a href='index.php?navigate=addpart'>Back</a></p></div>";
    mysqli_close($SNLDBConnection);
    return;
}
if ($year_from < 1940 || $year_from > (int)date('Y') + 1) {
    echo "<div class='content-box'><p style='color:red;'>Valid year (from) is required.</p><p><a href='index.php?navigate=addpart'>Back</a></p></div>";
    mysqli_close($SNLDBConnection);
    return;
}
if ($year_to !== null && $year_to < $year_from) {
    $year_to = $year_from; // silently fix
}

$stmt = $SNLDBConnection->prepare(
    "INSERT INTO `PARTS`
        (`seller_id`,`make_id`,`model_id`,`title`,`description`,`year_from`,`year_to`,
         `price`,`condition`,`stock`,`oem_number`,`replacement_number`,`visible`,`visible_private`,`for_sale`)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$stmt->bind_param(
    'iiissiidiissiii',
    $seller_id, $make_id, $model_id, $title, $description,
    $year_from, $year_to, $price, $condition, $stock,
    $oem, $replacement, $visible, $visible_prv, $for_sale
);

if ($stmt->execute()) {
    $new_id = $SNLDBConnection->insert_id;
    stats_day($SNLDBConnection, 'parts_added');
    $stmt->close();
    mysqli_close($SNLDBConnection);

    // Create the photo directory
    $dir = parts_photo_dir($new_id);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    header("Location: index.php?navigate=viewpart&id={$new_id}");
    exit();
} else {
    echo "<div class='content-box'><p style='color:red;'>Error saving part: "
         . htmlspecialchars($stmt->error) . "</p></div>";
    $stmt->close();
    mysqli_close($SNLDBConnection);
}
