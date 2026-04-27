<?php
header('Content-Type: application/json');

if (empty($_SESSION['authenticated'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    return;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    return;
}

$part_id = isset($_POST['part_id']) ? (int)$_POST['part_id'] : 0;
$field   = $_POST['field'] ?? '';
$value   = $_POST['value'] ?? '';

$allowed_fields = ['price', 'stock'];
if ($part_id <= 0 || !in_array($field, $allowed_fields, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    return;
}

include 'connection.php';
include_once 'parts_helper.php';

$part = parts_get($CarpartsConnection, $part_id, true);
if (!$part) {
    mysqli_close($CarpartsConnection);
    echo json_encode(['ok' => false, 'error' => 'Part not found']);
    return;
}

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    mysqli_close($CarpartsConnection);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    return;
}

$display = '';
$raw     = '';

if ($field === 'price') {
    $trimmed = trim($value);
    if ($trimmed === '' || $trimmed === null) {
        $stmt = $CarpartsConnection->prepare("UPDATE `PARTS` SET `price` = NULL WHERE `id` = ?");
        $stmt->bind_param('i', $part_id);
        $stmt->execute();
        $stmt->close();
        $display = '<span style="font-size:14px;color:#888;font-weight:normal;">On request</span>';
        $raw     = '';
    } else {
        $price = round((float)$trimmed, 2);
        if ($price < 0) $price = 0;
        $stmt = $CarpartsConnection->prepare("UPDATE `PARTS` SET `price` = ? WHERE `id` = ?");
        $stmt->bind_param('di', $price, $part_id);
        $stmt->execute();
        $stmt->close();
        $display = '&euro;' . number_format($price, 2, ',', '.');
        $raw     = (string)$price;
    }
} elseif ($field === 'stock') {
    $stock = max(0, (int)$value);
    $stmt = $CarpartsConnection->prepare("UPDATE `PARTS` SET `stock` = ? WHERE `id` = ?");
    $stmt->bind_param('ii', $stock, $part_id);
    $stmt->execute();
    $stmt->close();
    $display = (string)$stock;
    $raw     = (string)$stock;
}

mysqli_close($CarpartsConnection);
echo json_encode(['ok' => true, 'display' => $display, 'raw' => $raw]);
