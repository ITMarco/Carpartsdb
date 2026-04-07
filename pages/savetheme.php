<?php
// AJAX endpoint — save (or clear) the per-user theme preference.
// Called by the theme picker in body_bottom.engine.php.
// Expects: ?theme_id=N  (N=0 to clear)
header('Content-Type: application/json');

if (empty($_SESSION['authenticated']) || empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'users_helper.php';

users_ensure_table($CarpartsConnection);

$theme_id = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
$user_id  = (int)$_SESSION['user_id'];

users_save_theme($CarpartsConnection, $user_id, ($theme_id > 0 ? $theme_id : null));
mysqli_close($CarpartsConnection);

echo json_encode(['ok' => true]);
