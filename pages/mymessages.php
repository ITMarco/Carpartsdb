<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a>.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$seller_id = (int)$_SESSION['user_id'];

// Mark all unread messages for this seller as read
$CarpartsConnection->query(
    "UPDATE `PART_MESSAGES` pm
     JOIN `PARTS` p ON p.`id` = pm.`part_id`
     SET pm.`read_at` = NOW()
     WHERE p.`seller_id` = {$seller_id} AND pm.`read_at` IS NULL AND pm.`sender_id` != {$seller_id}"
);

// Fetch all messages grouped by part, newest first
$stmt = $CarpartsConnection->prepare(
    "SELECT pm.`id`, pm.`part_id`, pm.`name`, pm.`email`, pm.`message`, pm.`created_at`, pm.`read_at`,
            p.`title` AS part_title,
            u.`realname` AS sender_name, u.`email` AS sender_email
     FROM `PART_MESSAGES` pm
     JOIN `PARTS` p ON p.`id` = pm.`part_id`
     LEFT JOIN `USERS` u ON u.`id` = pm.`sender_id`
     WHERE p.`seller_id` = ? AND (pm.`sender_id` IS NULL OR pm.`sender_id` != ?)
     ORDER BY pm.`created_at` DESC"
);
$messages = [];
if ($stmt) {
    $stmt->bind_param('ii', $seller_id, $seller_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Messages from buyers</h3>

<?php if (empty($messages)): ?>
<p style="color:#888;">No messages yet.</p>
<?php else: ?>
<?php
$current_part = null;
foreach ($messages as $msg):
    if ($current_part !== $msg['part_id']):
        if ($current_part !== null) echo '</div>'; // close previous group
        $current_part = $msg['part_id'];
?>
<h4 style="margin-top:18px;margin-bottom:6px;">
    <a href="index.php?navigate=viewpart&id=<?= (int)$msg['part_id'] ?>"><?= htmlspecialchars($msg['part_title']) ?></a>
    <small style="color:#888;font-weight:normal;font-size:12px;">&mdash; PART-<?= sprintf('%05d', $msg['part_id']) ?></small>
</h4>
<div style="border-left:3px solid var(--color-accent);padding-left:12px;">
<?php endif; ?>
<div style="margin-bottom:10px;padding:8px 12px;background:var(--color-surface);
            border:1px solid var(--color-content-border);border-radius:4px;">
    <div style="font-size:12px;color:#888;margin-bottom:4px;">
        <strong><?= htmlspecialchars($msg['sender_name'] ?: $msg['name'] ?: 'Anonymous') ?></strong>
        <?php
        $contact = $msg['sender_email'] ?: $msg['email'];
        if ($contact): ?>
        &lt;<a href="mailto:<?= htmlspecialchars($contact) ?>"><?= htmlspecialchars($contact) ?></a>&gt;
        <?php endif; ?>
        &mdash; <?= htmlspecialchars(substr($msg['created_at'], 0, 16)) ?>
    </div>
    <div style="font-size:13px;line-height:1.5;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
    <div style="margin-top:6px;font-size:11px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$msg['part_id'] ?>">View listing</a>
        &nbsp;|&nbsp;
        <a href="index.php?navigate=processpartmessage&action=delete&id=<?= (int)$msg['id'] ?>&part_id=<?= (int)$msg['part_id'] ?>&csrf=<?= urlencode($_SESSION['csrf_token']) ?>"
           style="color:#c04040;"
           onclick="return confirm('Delete this message?');">Delete</a>
    </div>
</div>
<?php endforeach; ?>
<?php if ($current_part !== null) echo '</div>'; ?>
<?php endif; ?>
</div>
