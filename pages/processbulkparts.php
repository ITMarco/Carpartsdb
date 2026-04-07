<?php
if (empty($_SESSION['authenticated'])) {
    header('Location: index.php?navigate=secureadmin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: index.php?navigate=myparts');
    exit();
}

$action    = $_POST['bulk_action'] ?? '';
$ids_raw   = $_POST['ids'] ?? [];
$seller_id = (int)$_SESSION['user_id'];
$is_admin  = !empty($_SESSION['isadmin']);

if (!in_array($action, ['sold', 'relist', 'delete'], true) || empty($ids_raw)) {
    header('Location: index.php?navigate=myparts');
    exit();
}

// Sanitise IDs
$ids = array_map('intval', (array)$ids_raw);
$ids = array_filter($ids, fn($v) => $v > 0);
if (empty($ids)) {
    header('Location: index.php?navigate=myparts');
    exit();
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'parts_helper.php';
parts_ensure_table($CarpartsConnection);

// Build safe IN clause — only touch parts owned by this seller (or all if admin)
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$owner_clause = $is_admin ? '' : " AND `seller_id` = {$seller_id}";

if ($action === 'delete') {
    // Fetch photo dirs first so we can clean up files
    $sel = $CarpartsConnection->prepare(
        "SELECT `id`, `photo_dir` FROM `PARTS` WHERE `id` IN ({$placeholders}){$owner_clause}"
    );
    $types = str_repeat('i', count($ids));
    $sel->bind_param($types, ...$ids);
    $sel->execute();
    $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
    $sel->close();

    foreach ($rows as $row) {
        $dir = $row['photo_dir'] ?: parts_find_dir((int)$row['id']);
        if ($dir && is_dir($dir)) {
            foreach (glob("{$dir}/*") ?: [] as $f) { @unlink($f); }
            @rmdir($dir);
        }
    }

    $del = $CarpartsConnection->prepare(
        "DELETE FROM `PARTS` WHERE `id` IN ({$placeholders}){$owner_clause}"
    );
    $del->bind_param($types, ...$ids);
    $del->execute();
    $del->close();

} elseif ($action === 'sold') {
    $upd = $CarpartsConnection->prepare(
        "UPDATE `PARTS` SET `is_sold`=1 WHERE `id` IN ({$placeholders}){$owner_clause}"
    );
    $types = str_repeat('i', count($ids));
    $upd->bind_param($types, ...$ids);
    $upd->execute();
    $upd->close();

} elseif ($action === 'relist') {
    $upd = $CarpartsConnection->prepare(
        "UPDATE `PARTS` SET `is_sold`=0 WHERE `id` IN ({$placeholders}){$owner_clause}"
    );
    $types = str_repeat('i', count($ids));
    $upd->bind_param($types, ...$ids);
    $upd->execute();
    $upd->close();
}

mysqli_close($CarpartsConnection);
header('Location: index.php?navigate=myparts');
exit();
