<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p style='color:red;'>Please <a href='index.php?navigate=secureadmin'>log in</a>.</p></div>";
    return;
}

// State-changing action — must be POST with valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(405);
    echo "<div class='content-box'><p style='color:red;'>Invalid request.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$id   = isset($_POST['id'])   ? intval($_POST['id'])   : 0;
$undo = !empty($_POST['undo']);

if ($id <= 0) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Invalid part ID.</p></div>";
    return;
}

$part = parts_get($CarpartsConnection, $id, true);
if (!$part) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Part not found.</p></div>";
    return;
}

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    return;
}

$new_sold = $undo ? 0 : 1;
$stmt = $CarpartsConnection->prepare("UPDATE `PARTS` SET `is_sold` = ? WHERE `id` = ?");
$stmt->bind_param('ii', $new_sold, $id);
$stmt->execute();
$stmt->close();
mysqli_close($CarpartsConnection);

$dest = 'index.php?navigate=viewpart&id=' . $id;
echo "<div class='content-box'><p>Done. <a href='" . htmlspecialchars($dest) . "'>Back to part &rarr;</a></p>"
   . "<script>window.location.replace('" . addslashes($dest) . "');</script></div>";
exit();
