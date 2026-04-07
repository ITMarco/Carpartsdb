<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to view your parts.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'makes_helper.php';

parts_ensure_table($CarpartsConnection);

$seller_id = (int)$_SESSION['user_id'];

$stmt = $CarpartsConnection->prepare(
    "SELECT p.`id`, p.`title`, p.`price`, p.`condition`, p.`year_from`, p.`year_to`,
            p.`stock`, p.`visible`, p.`visible_private`, p.`for_sale`,
            COALESCE(p.`is_sold`,0) AS `is_sold`,
            COALESCE(p.`view_count`,0) AS `view_count`,
            m.`name` AS make_name, mo.`name` AS model_name, p.`created_at`
     FROM `PARTS` p
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
     WHERE p.`seller_id` = ?
     ORDER BY p.`created_at` DESC"
);
$parts = [];
if ($stmt) {
    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $parts[] = $row;
    }
    $stmt->close();
}

mysqli_close($CarpartsConnection);
?>

<div class="content-box">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <h3 style="margin:0;">My Parts Collection</h3>
    <a href="index.php?navigate=addpart" class="btn" style="padding:7px 16px;">+ Add new part</a>
</div>

<?php if (empty($parts)): ?>
<p>You have no parts in your collection yet.</p>
<?php else: ?>
<form method="post" action="index.php?navigate=processbulkparts" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />

    <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:12px;color:#888;">Selected:</span>
        <button type="submit" name="bulk_action" value="sold"
                style="padding:4px 12px;font-size:12px;background:#c87020;color:#fff;border:none;border-radius:3px;cursor:pointer;"
                onclick="return confirmBulk('Mark selected as sold?');">Mark sold</button>
        <button type="submit" name="bulk_action" value="relist"
                style="padding:4px 12px;font-size:12px;background:#5588bb;color:#fff;border:none;border-radius:3px;cursor:pointer;"
                onclick="return confirmBulk('Re-list selected parts?');">Re-list</button>
        <button type="submit" name="bulk_action" value="delete"
                style="padding:4px 12px;font-size:12px;background:#dc3545;color:#fff;border:none;border-radius:3px;cursor:pointer;"
                onclick="return confirmBulk('Delete selected parts? This cannot be undone.');">Delete</button>
        <a href="#" onclick="toggleAll(true);return false;" style="font-size:12px;">All</a>
        <a href="#" onclick="toggleAll(false);return false;" style="font-size:12px;">None</a>
    </div>

    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);background:var(--color-nav-hover-bg);">
        <td style="padding:5px 8px;width:20px;"></td>
        <td style="padding:5px 8px;">Ref</td>
        <td style="padding:5px 8px;">Part</td>
        <td style="padding:5px 8px;">Make / Model</td>
        <td style="padding:5px 8px;">Status</td>
        <td style="padding:5px 8px;">Cond.</td>
        <td style="padding:5px 8px;text-align:center;">Views</td>
        <td style="padding:5px 8px;text-align:right;">Price</td>
        <td style="padding:5px 8px;">Actions</td>
    </tr>
    <?php foreach ($parts as $p):
        $row_style = $p['is_sold'] ? 'color:#aaa;' : '';
    ?>
    <tr style="border-bottom:1px solid var(--color-content-border);<?= $row_style ?>">
        <td style="padding:4px 8px;">
            <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="bulk-cb" />
        </td>
        <td style="padding:4px 8px;font-size:11px;white-space:nowrap;">
            <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"><?= sprintf('PART-%05d', $p['id']) ?></a>
        </td>
        <td style="padding:4px 8px;">
            <?= htmlspecialchars($p['title']) ?><br>
            <small style="color:#aaa;"><?= htmlspecialchars($p['make_name']) ?><?= $p['model_name'] ? ' / ' . htmlspecialchars($p['model_name']) : '' ?></small>
        </td>
        <td style="padding:4px 8px;">
            <?= (int)$p['year_from'] ?><?= $p['year_to'] ? '&ndash;' . (int)$p['year_to'] : '' ?>
        </td>
        <td style="padding:4px 8px;font-size:12px;">
            <?php if ($p['is_sold']): ?>
            <span style="color:#c04040;font-weight:bold;">Sold</span>
            <?php elseif (!$p['visible']): ?>
            <span style="color:#888;">Private</span>
            <?php elseif ($p['visible_private']): ?>
            <span style="color:#448;">Incrowd</span>
            <?php else: ?>
            <span style="color:#2a7a2a;">Public</span>
            <?php endif; ?>
            <?php if (!$p['for_sale'] && !$p['is_sold']): ?>
            <br><small style="color:#888;">Display only</small>
            <?php endif; ?>
        </td>
        <td style="padding:4px 8px;"><?= (int)$p['condition'] ?>/5</td>
        <td style="padding:4px 8px;text-align:center;color:#888;font-size:12px;"><?= number_format((int)$p['view_count']) ?></td>
        <td style="padding:4px 8px;text-align:right;"><?= $p['price'] !== null ? '&euro;' . number_format((float)$p['price'], 2, ',', '.') : '<span style="color:#888;font-size:11px;">On request</span>' ?></td>
        <td style="padding:4px 8px;white-space:nowrap;">
            <a href="index.php?navigate=editpart&id=<?= (int)$p['id'] ?>" style="font-size:12px;">Edit</a>
            | <a href="index.php?navigate=deletepartimage&id=<?= (int)$p['id'] ?>" style="font-size:12px;">Photos</a>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
</form>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.bulk-cb').forEach(function(cb) { cb.checked = checked; });
}
function confirmBulk(msg) {
    var checked = document.querySelectorAll('.bulk-cb:checked').length;
    if (checked === 0) { alert('Select at least one part first.'); return false; }
    return confirm(checked + ' part(s) selected. ' + msg);
}
</script>
<?php endif; ?>
</div>
