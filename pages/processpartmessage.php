<?php
// Handles both:
//   POST  action=send  — submit a question/bid
//   GET   action=delete — admin/seller deletes a message

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$action = $_GET['action'] ?? ($_POST['action'] ?? 'send');

// ── Delete a message ──────────────────────────────────────────────────────────
if ($action === 'delete') {
    $msg_id  = intval($_GET['id'] ?? 0);
    $part_id = intval($_GET['part_id'] ?? 0);
    $csrf    = $_GET['csrf'] ?? '';

    if (empty($_SESSION['authenticated'])
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        mysqli_close($CarpartsConnection);
        echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
        return;
    }

    if ($msg_id > 0) {
        // Verify ownership: seller or admin
        $chk = $CarpartsConnection->prepare(
            "SELECT pm.`id`, p.`seller_id` FROM `PART_MESSAGES` pm
             JOIN `PARTS` p ON p.`id` = pm.`part_id`
             WHERE pm.`id` = ? LIMIT 1"
        );
        $chk->bind_param('i', $msg_id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($row) {
            $is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$row['seller_id'];
            if ($is_seller || !empty($_SESSION['isadmin'])) {
                $del = $CarpartsConnection->prepare("DELETE FROM `PART_MESSAGES` WHERE `id` = ?");
                $del->bind_param('i', $msg_id);
                $del->execute();
                $del->close();
            }
        }
    }
    mysqli_close($CarpartsConnection);
    header("Location: index.php?navigate=viewpart&id={$part_id}");
    exit();
}

// ── Send a message ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mysqli_close($CarpartsConnection);
    header('Location: index.php?navigate=browse');
    exit();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p style='color:red;'>Security validation failed.</p></div>";
    return;
}

$part_id = intval($_POST['part_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($part_id <= 0 || $message === '') {
    mysqli_close($CarpartsConnection);
    header("Location: index.php?navigate=viewpart&id={$part_id}&msg_error=" . urlencode('Message cannot be empty.'));
    exit();
}

$part = parts_get($CarpartsConnection, $part_id, false);
if (!$part) {
    mysqli_close($CarpartsConnection);
    header('Location: index.php?navigate=browse');
    exit();
}

// Sender info
if (!empty($_SESSION['authenticated']) && !empty($_SESSION['user_id'])) {
    $sender_id = (int)$_SESSION['user_id'];
    $name      = $_SESSION['username'] ?? $_SESSION['user_email'] ?? '';
    $email     = $_SESSION['user_email'] ?? '';
} else {
    $sender_id = null;
    $name      = trim($_POST['name']  ?? '');
    $email     = trim($_POST['email'] ?? '');
    if ($name === '' || $email === '') {
        mysqli_close($CarpartsConnection);
        header("Location: index.php?navigate=viewpart&id={$part_id}&msg_error=" . urlencode('Please enter your name and email.'));
        exit();
    }
}

// Rate-limit: max 5 messages per IP per 24 h
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$rl = $CarpartsConnection->prepare(
    "SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `sender_id` IS NULL AND `email` = ?
     AND `created_at` > NOW() - INTERVAL 24 HOUR"
);
$rl_count = 0;
if ($rl) {
    $rl->bind_param('s', $email);
    $rl->execute();
    $rl->bind_result($rl_count);
    $rl->fetch();
    $rl->close();
}
if ($sender_id === null && $rl_count >= 5) {
    mysqli_close($CarpartsConnection);
    header("Location: index.php?navigate=viewpart&id={$part_id}&msg_error=" . urlencode('Too many messages from this address. Please try again tomorrow.'));
    exit();
}

$stmt = $CarpartsConnection->prepare(
    "INSERT INTO `PART_MESSAGES` (`part_id`,`sender_id`,`name`,`email`,`message`) VALUES (?,?,?,?,?)"
);
$stmt->bind_param('iisss', $part_id, $sender_id, $name, $email, $message);
$stmt->execute();
$stmt->close();

// Notify the seller by email if they have an email address
$seller_email = $part['seller_email'] ?? '';
if ($seller_email !== '') {
    $subject = 'New message about your part: ' . $part['title'];
    $body    = "You received a new question or message about your listing:\n\n"
             . "Part: " . $part['title'] . " (" . parts_ref($part_id) . ")\n\n"
             . "From: " . ($name ?: 'Registered user') . "\n"
             . "Message:\n" . $message . "\n\n"
             . "Reply by visiting the part listing.";
    $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'carpartsdb') . "\r\n"
             . "Reply-To: " . ($email ?: $seller_email) . "\r\n"
             . "Content-Type: text/plain; charset=utf-8";
    @mail($seller_email, $subject, $body, $headers);
}

mysqli_close($CarpartsConnection);
header("Location: index.php?navigate=viewpart&id={$part_id}&msg_sent=1");
exit();
