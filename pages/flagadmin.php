<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied.</div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'parts_helper.php';
parts_ensure_table($CarpartsConnection);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle resolve/dismiss
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

    $flag_id = (int)($_POST['flag_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    if ($flag_id > 0 && $action === 'resolve') {
        $CarpartsConnection->query("UPDATE `PART_FLAGS` SET `resolved`=1 WHERE `id`={$flag_id}");
    }
}

// Load open flags
$flags = $CarpartsConnection->query(
    "SELECT f.`id`, f.`part_id`, f.`reason`, f.`created_at`,
            p.`title` AS part_title,
            u.`email` AS reporter_email, u.`realname` AS reporter_name
     FROM `PART_FLAGS` f
     JOIN `PARTS` p ON p.`id`=f.`part_id`
     LEFT JOIN `USERS` u ON u.`id`=f.`reporter_id`
     WHERE f.`resolved`=0
     ORDER BY f.`created_at` DESC"
);

$open_count = $flags ? $flags->num_rows : 0;
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Reported listings <small style="font-weight:normal;color:#888;font-size:14px;"><?= $open_count ?> open</small></h3>

<?php if ($open_count === 0): ?>
<p style="color:#2a7a2a;">No open reports.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="background:var(--color-nav-hover-bg);">
    <th style="padding:6px 10px;text-align:left;">Part</th>
    <th style="padding:6px 10px;text-align:left;">Reason</th>
    <th style="padding:6px 10px;text-align:left;">Reporter</th>
    <th style="padding:6px 10px;text-align:left;">Date</th>
    <th style="padding:6px 10px;text-align:center;">Actions</th>
</tr>
<?php $i=1; while ($row = $flags->fetch_assoc()): ?>
<tr style="border-top:1px solid var(--color-nav-border);background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
    <td style="padding:6px 10px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$row['part_id'] ?>"><?= htmlspecialchars($row['part_title']) ?></a><br>
        <small style="color:#888;"><?= htmlspecialchars(parts_ref((int)$row['part_id'])) ?></small>
    </td>
    <td style="padding:6px 10px;"><?= htmlspecialchars($row['reason']) ?></td>
    <td style="padding:6px 10px;color:#666;"><?= htmlspecialchars($row['reporter_name'] ?: $row['reporter_email'] ?: 'Unknown') ?></td>
    <td style="padding:6px 10px;color:#888;white-space:nowrap;"><?= htmlspecialchars(substr($row['created_at'], 0, 10)) ?></td>
    <td style="padding:6px 10px;text-align:center;white-space:nowrap;">
        <form method="post" action="index.php?navigate=flagadmin" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="flag_id"   value="<?= (int)$row['id'] ?>" />
            <button type="submit" name="action" value="resolve"
                    style="padding:3px 10px;font-size:12px;background:#2a7a2a;color:#fff;border:none;cursor:pointer;border-radius:3px;">
                Dismiss
            </button>
        </form>
        &nbsp;
        <a href="index.php?navigate=deletepart&id=<?= (int)$row['part_id'] ?>"
           style="padding:3px 10px;font-size:12px;background:#dc3545;color:#fff;text-decoration:none;border-radius:3px;"
           onclick="return confirm('Delete this part listing?');">Delete part</a>
    </td>
</tr>
<?php $i++; endwhile; ?>
</table>
<?php endif; ?>

<p style="margin-top:14px;"><a href="index.php?navigate=adminpanel">&larr; Admin panel</a></p>
</div>
