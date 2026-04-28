<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Not authenticated.</p></div>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>window.location.replace('index.php?navigate=addpart');</script>";
    exit();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo "<div class='content-box'><p style='color:red;'>Security validation failed.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'users_helper.php';
include_once 'stats_helper.php';

parts_ensure_table($CarpartsConnection);

// Collect and validate input
$title       = trim($_POST['title'] ?? '');
$make_id     = intval($_POST['make_id'] ?? 0);
$model_id    = intval($_POST['model_id'] ?? 0) ?: null;
$year_from   = intval($_POST['year_from'] ?? 0);
$year_to     = intval($_POST['year_to'] ?? 0) ?: null;
$price_type  = in_array($_POST['price_type'] ?? '', ['fixed','request','bid']) ? $_POST['price_type'] : 'fixed';
$price_raw   = trim($_POST['price'] ?? '');
$price       = ($price_type === 'fixed' && $price_raw !== '') ? round(floatval($price_raw), 2) : null;
$condition   = max(0, min(5, intval($_POST['condition'] ?? 3)));
$stock       = max(1, intval($_POST['stock'] ?? 1));
$oem         = trim($_POST['oem_number'] ?? '') ?: null;
$replacement = trim($_POST['replacement_number'] ?? '') ?: null;
$description = trim($_POST['description'] ?? '') ?: null;
$can_set_private = !empty($_SESSION['isadmin']) || !empty($_SESSION['is_member']);
$vis_val = $_POST['visibility'] ?? ($can_set_private ? 'private' : 'hidden');
if ($vis_val === 'public') {
    $visible = 1; $visible_prv = 0;
} elseif ($vis_val === 'private' && $can_set_private) {
    $visible = 0; $visible_prv = 1;
} else {
    $visible = 0; $visible_prv = 0;
}
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
if ($year_from < 1900 && $model_id) {
    $yr = $CarpartsConnection->prepare("SELECT `year_from`,`year_to` FROM `CAR_MODELS` WHERE `id`=? LIMIT 1");
    $yr->bind_param('i', $model_id);
    $yr->execute();
    $yr->bind_result($mf, $mt);
    $yr->fetch();
    $yr->close();
    if ($mf) { $year_from = (int)$mf; }
    if (!$year_to && $mt)  { $year_to  = (int)$mt; }
}
if ($year_from < 1900) {
    $year_from = (int)date('Y'); // fallback: current year
}
if ($year_to !== null && $year_to < $year_from) {
    $year_to = null;
}

$stmt = $CarpartsConnection->prepare(
    "INSERT INTO `PARTS`
        (`seller_id`,`make_id`,`model_id`,`title`,`description`,`year_from`,`year_to`,
         `price`,`price_type`,`condition`,`stock`,`oem_number`,`replacement_number`,`visible`,`visible_private`,`for_sale`)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$stmt->bind_param(
    'iiissiidsiissiii',
    $seller_id, $make_id, $model_id, $title, $description,
    $year_from, $year_to, $price, $price_type, $condition, $stock,
    $oem, $replacement, $visible, $visible_prv, $for_sale
);

if ($stmt->execute()) {
    $new_id = $CarpartsConnection->insert_id;
    stats_day($CarpartsConnection, 'parts_added');
    $stmt->close();

    // Create photo directory in YYYYMMDD-00042 format
    $photo_dir = parts_photo_dir_new($new_id);
    if (!is_dir($photo_dir)) @mkdir($photo_dir, 0755, true);

    // Persist the folder path in PARTS so uploads always find the right dir
    $pd = $CarpartsConnection->prepare("UPDATE `PARTS` SET `photo_dir` = ? WHERE `id` = ?");
    $pd->bind_param('si', $photo_dir, $new_id);
    $pd->execute();
    $pd->close();

    // Save "also fits" compat entries
    $compat_raw = trim($_POST['compat_data'] ?? '[]');
    $compat_arr = json_decode($compat_raw, true);
    if (is_array($compat_arr) && !empty($compat_arr)) {
        parts_compat_save($CarpartsConnection, $new_id, $compat_arr);
    }

    // Record make/model choice for quick-select history
    users_ensure_table($CarpartsConnection);
    users_record_model_pref($CarpartsConnection, $seller_id, $make_id, $model_id);

    mysqli_close($CarpartsConnection);

    // Redirect to photo-upload step on addpart page
    echo "<div class='content-box'><p>Part saved. <a href='index.php?navigate=addpart&amp;new={$new_id}'>Continue to photo upload &rarr;</a></p>"
         . "<script>window.location.replace('index.php?navigate=addpart&new={$new_id}');</script></div>";
    exit();
} else {
    echo "<div class='content-box'><p style='color:red;'>Error saving part: "
         . htmlspecialchars($stmt->error) . "</p></div>";
    $stmt->close();
    mysqli_close($CarpartsConnection);
}
