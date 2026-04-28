<?php
// Seller/admin posts a reply to a buyer's question.

if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p style='color:red;'>Not authenticated.</p></div>";
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo "<div class='content-box'><p style='color:red;'>Invalid request.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'settings_helper.php';

parts_ensure_table($CarpartsConnection);

$parent_id = intval($_POST['parent_id'] ?? 0);
$part_id   = intval($_POST['part_id']   ?? 0);
$message   = trim($_POST['message'] ?? '');

if ($parent_id <= 0 || $part_id <= 0 || $message === '') {
    mysqli_close($CarpartsConnection);
    $dest = 'index.php?navigate=viewpart&id=' . $part_id . '#qa-section';
    echo "<script>window.location.replace('" . addslashes($dest) . "');</script>";
    exit();
}

// Load the parent message — verifies part_id match and gets recipient info
$chk = $CarpartsConnection->prepare(
    "SELECT pm.`id`, pm.`sender_id`, pm.`email`, pm.`name`,
            p.`seller_id`, p.`title`,
            m.`name` AS make_name
     FROM `PART_MESSAGES` pm
     JOIN `PARTS` p ON p.`id` = pm.`part_id`
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     WHERE pm.`id` = ? AND pm.`part_id` = ? AND pm.`parent_id` IS NULL
     LIMIT 1"
);
$chk->bind_param('ii', $parent_id, $part_id);
$chk->execute();
$parent = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$parent) {
    mysqli_close($CarpartsConnection);
    $dest = 'index.php?navigate=viewpart&id=' . $part_id . '#qa-section';
    echo "<script>window.location.replace('" . addslashes($dest) . "');</script>";
    exit();
}

// Must be seller or admin
$is_seller = (int)$_SESSION['user_id'] === (int)$parent['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    return;
}

// Check thread depth limit
$thread_limit = (int)settings_get($CarpartsConnection, 'msg_thread_limit', 20);
if ($thread_limit > 0) {
    $dc = $CarpartsConnection->prepare(
        "SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `id`=? OR `parent_id`=?"
    );
    $dc_val = 0;
    if ($dc) {
        $dc->bind_param('ii', $parent_id, $parent_id);
        $dc->execute();
        $dc->bind_result($dc_val);
        $dc->fetch();
        $dc->close();
    }
    if ($dc_val >= $thread_limit) {
        mysqli_close($CarpartsConnection);
        $dest = 'index.php?navigate=viewpart&id=' . $part_id . '&msg_error=' . urlencode('This conversation has reached the maximum length (' . $thread_limit . ' messages).');
        echo "<script>window.location.replace('" . addslashes($dest) . "');</script>";
        exit();
    }
}

// The reply goes to the original message sender (if registered), or just by email
$reply_recipient = $parent['sender_id'] ? (int)$parent['sender_id'] : null;
$sender_id       = (int)$_SESSION['user_id'];
$sender_name     = $_SESSION['username'] ?? $_SESSION['user_email'] ?? '';

$sender_email = '';
$ins = $CarpartsConnection->prepare(
    "INSERT INTO `PART_MESSAGES`
        (`part_id`,`parent_id`,`sender_id`,`recipient_id`,`name`,`email`,`message`)
     VALUES (?,?,?,?,?,?,?)"
);
if (!$ins) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p style='color:red;'>Database error. <a href='index.php?navigate=viewpart&id={$part_id}'>Back to part</a></p></div>";
    exit();
}
$ins->bind_param('iiissss',
    $part_id, $parent_id, $sender_id, $reply_recipient,
    $sender_name, $sender_email, $message
);
$ins->execute();
$ins->close();

// Email the buyer if they have an address
$buyer_email = $parent['email'] ?? '';
if ($buyer_email !== '' && filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
    $ref     = sprintf('PART-%05d', $part_id);
    $subject = "Reply to your question about: {$parent['title']}";
    $body    = "The seller replied to your question about {$parent['make_name']} {$parent['title']} ({$ref}):\n\n"
             . $message . "\n\n"
             . "View the listing: " . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) : '') . "/index.php?navigate=viewpart&id={$part_id}";
    $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'carpartsdb') . "\r\n"
             . "Content-Type: text/plain; charset=utf-8";
    @mail($buyer_email, $subject, $body, $headers);
}

mysqli_close($CarpartsConnection);
$dest = 'index.php?navigate=viewpart&id=' . $part_id . '&msg_sent=1#qa-section';
echo "<div class='content-box'><p>Reply sent! <a href='" . htmlspecialchars($dest) . "'>Back to part &rarr;</a></p>"
   . "<script>window.location.replace('" . addslashes($dest) . "');</script></div>";
exit();
