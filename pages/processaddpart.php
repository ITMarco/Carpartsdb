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

parts_ensure_table($CarpartsConnection);

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
    mysqli_close($CarpartsConnection);
    return;
}
if ($make_id <= 0) {
    echo "<div class='content-box'><p style='color:red;'>Car make is required.</p><p><a href='index.php?navigate=addpart'>Back</a></p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

// Auto-fill year_from from model if not provided
if ($year_from < 1940 && $model_id) {
    $yr = $CarpartsConnection->prepare("SELECT `year_from`,`year_to` FROM `CAR_MODELS` WHERE `id`=? LIMIT 1");
    $yr->bind_param('i', $model_id);
    $yr->execute();
    $yr->bind_result($mf, $mt);
    $yr->fetch();
    $yr->close();
    if ($mf) { $year_from = (int)$mf; }
    if (!$year_to && $mt)  { $year_to  = (int)$mt; }
}
if ($year_from < 1940) {
    $year_from = (int)date('Y'); // fallback: current year
}
if ($year_to !== null && $year_to < $year_from) {
    $year_to = null;
}

$stmt = $CarpartsConnection->prepare(
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
    $new_id = $CarpartsConnection->insert_id;
    stats_day($CarpartsConnection, 'parts_added');
    $stmt->close();

    // Create photo directory
    $dir = parts_photo_dir($new_id);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Save "also fits" compat entries
    $compat_raw = trim($_POST['compat_data'] ?? '[]');
    $compat_arr = json_decode($compat_raw, true);
    if (is_array($compat_arr) && !empty($compat_arr)) {
        parts_compat_save($CarpartsConnection, $new_id, $compat_arr);
    }

    mysqli_close($CarpartsConnection);

    // Redirect to photo-upload step on addpart page
    header("Location: index.php?navigate=addpart&new={$new_id}");
    exit();
} else {
    echo "<div class='content-box'><p style='color:red;'>Error saving part: "
         . htmlspecialchars($stmt->error) . "</p></div>";
    $stmt->close();
    mysqli_close($CarpartsConnection);
}
