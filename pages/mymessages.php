<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to view your messages.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$uid = (int)$_SESSION['user_id'];

// Load all top-level message threads where the current user is involved
// (as seller = recipient, or as buyer = sender)
$stmt = $CarpartsConnection->prepare(
    "SELECT
        pm.`id`, pm.`part_id`, pm.`sender_id`, pm.`recipient_id`, pm.`is_read`,
        pm.`name` AS sender_name_raw, pm.`email` AS sender_email,
        pm.`message`, pm.`created_at`,
        u.`realname` AS sender_realname,
        p.`title` AS part_title,
        m.`name` AS make_name,
        (SELECT COUNT(*) FROM `PART_MESSAGES` r WHERE r.`parent_id` = pm.`id`) AS reply_count,
        (SELECT COUNT(*) FROM `PART_MESSAGES` r
         WHERE (r.`parent_id` = pm.`id` OR r.`id` = pm.`id`)
           AND r.`recipient_id` = ? AND r.`is_read` = 0) AS unread_count,
        (SELECT MAX(r2.`created_at`) FROM `PART_MESSAGES` r2
         WHERE r2.`id` = pm.`id` OR r2.`parent_id` = pm.`id`) AS last_activity
     FROM `PART_MESSAGES` pm
     JOIN `PARTS` p  ON p.`id` = pm.`part_id`
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     LEFT JOIN `USERS` u ON u.`id` = pm.`sender_id`
     WHERE pm.`parent_id` IS NULL
       AND (pm.`recipient_id` = ? OR pm.`sender_id` = ?)
     ORDER BY last_activity DESC"
);
$threads = [];
if ($stmt) {
    $stmt->bind_param('iii', $uid, $uid, $uid);
    $stmt->execute();
    $threads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$total_unread = array_sum(array_column($threads, 'unread_count'));

mysqli_close($CarpartsConnection);
?>

<div class="content-box">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <h3 style="margin:0;">
        Messages
        <?php if ($total_unread > 0): ?>
        <span style="display:inline-block;background:#c87020;color:#fff;border-radius:10px;
                     padding:1px 8px;font-size:12px;font-weight:bold;vertical-align:middle;margin-left:6px;">
            <?= $total_unread ?> new
        </span>
        <?php endif; ?>
    </h3>
</div>

<?php if (empty($threads)): ?>
<p style="color:#888;">No messages yet.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);background:var(--color-nav-hover-bg);">
    <td style="padding:6px 10px;">Part</td>
    <td style="padding:6px 10px;">From</td>
    <td style="padding:6px 10px;">Message</td>
    <td style="padding:6px 10px;white-space:nowrap;">Last activity</td>
    <td style="padding:6px 10px;text-align:center;">Replies</td>
</tr>
<?php foreach ($threads as $t):
    $unread  = (int)$t['unread_count'] > 0;
    $bold    = $unread ? 'font-weight:bold;' : '';
    $from    = $t['sender_realname'] ?: $t['sender_name_raw'] ?: 'Anonymous';
    $preview = mb_strimwidth($t['message'], 0, 80, '…');
    $last    = substr($t['last_activity'] ?? $t['created_at'], 0, 16);
?>
<tr style="border-bottom:1px solid var(--color-content-border);<?= $bold ?>">
    <td style="padding:6px 10px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$t['part_id'] ?>#qa-section">
            <?= htmlspecialchars($t['make_name'] . ' — ' . $t['part_title']) ?>
        </a>
        <br><small style="color:#aaa;font-weight:normal;"><?= sprintf('PART-%05d', $t['part_id']) ?></small>
    </td>
    <td style="padding:6px 10px;">
        <?= htmlspecialchars($from) ?>
        <?php if ((int)$t['sender_id'] === $uid): ?>
        <br><small style="color:#888;font-weight:normal;">(you)</small>
        <?php endif; ?>
    </td>
    <td style="padding:6px 10px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$t['part_id'] ?>#qa-section"
           style="color:inherit;text-decoration:none;">
            <?= htmlspecialchars($preview) ?>
        </a>
        <?php if ($unread): ?>
        <span style="display:inline-block;background:#c87020;color:#fff;border-radius:8px;
                     padding:1px 6px;font-size:10px;font-weight:bold;vertical-align:middle;margin-left:4px;">
            <?= (int)$t['unread_count'] ?> new
        </span>
        <?php endif; ?>
    </td>
    <td style="padding:6px 10px;white-space:nowrap;font-size:12px;color:#888;font-weight:normal;">
        <?= htmlspecialchars($last) ?>
    </td>
    <td style="padding:6px 10px;text-align:center;color:#888;font-weight:normal;">
        <?= (int)$t['reply_count'] ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
