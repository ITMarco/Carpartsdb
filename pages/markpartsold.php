<?php
if (empty($_SESSION['authenticated'])) {
    header('Location: index.php?navigate=secureadmin');
    exit();
}

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$undo = !empty($_GET['undo']);

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

header("Location: index.php?navigate=viewpart&id={$id}");
exit();
