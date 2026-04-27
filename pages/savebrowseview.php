<?php
header('Content-Type: application/json');

if (empty($_SESSION['authenticated']) || empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    return;
}

$view = isset($_POST['view']) ? trim($_POST['view']) : '';
if (!in_array($view, ['list', 'tile'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid view']);
    return;
}

include 'connection.php';
include_once 'settings_helper.php';

$uid = (int)$_SESSION['user_id'];
settings_set($CarpartsConnection, "browse_view_u{$uid}", $view);
mysqli_close($CarpartsConnection);

echo json_encode(['ok' => true]);
