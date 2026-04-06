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
<h3>My Parts Collection</h3>
<p style="margin-bottom:16px;">
    <a href="index.php?navigate=addpart" class="btn" style="padding:8px 16px;">+ Add new part</a>
</p>

<?php if (empty($parts)): ?>
<p>You have no parts in your collection yet.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
    <td style="padding:5px 8px;">Ref</td>
    <td style="padding:5px 8px;">Part</td>
    <td style="padding:5px 8px;">Visibility</td>
    <td style="padding:5px 8px;">Sale</td>
    <td style="padding:5px 8px;">Make / Model</td>
    <td style="padding:5px 8px;">Year</td>
    <td style="padding:5px 8px;">Cond.</td>
    <td style="padding:5px 8px;">Stock</td>
    <td style="padding:5px 8px;text-align:right;">Price</td>
    <td style="padding:5px 8px;">Actions</td>
</tr>
<?php foreach ($parts as $p): ?>
<tr style="border-bottom:1px solid var(--color-content-border);">
    <td style="padding:4px 8px;font-size:11px;white-space:nowrap;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"><?= sprintf('PART-%05d', $p['id']) ?></a>
    </td>
    <td style="padding:4px 8px;"><?= htmlspecialchars($p['title']) ?></td>
    <td style="padding:4px 8px;">
        <?php if (!$p['visible']): ?>Private<?php elseif ($p['visible_private']): ?>Incrowd<?php else: ?>Public<?php endif; ?>
    </td>
    <td style="padding:4px 8px;"><?= $p['for_sale'] ? 'For sale' : 'Display only' ?></td>
    <td style="padding:4px 8px;">
        <?= htmlspecialchars($p['make_name']) ?>
        <?= $p['model_name'] ? '<br><small style="color:#888;">' . htmlspecialchars($p['model_name']) . '</small>' : '' ?>
    </td>
    <td style="padding:4px 8px;white-space:nowrap;"><?= (int)$p['year_from'] ?><?= $p['year_to'] ? '&ndash;' . (int)$p['year_to'] : '' ?></td>
    <td style="padding:4px 8px;"><?= (int)$p['condition'] ?>/5</td>
    <td style="padding:4px 8px;"><?= (int)$p['stock'] ?></td>
    <td style="padding:4px 8px;text-align:right;">&euro;<?= number_format((float)$p['price'], 2, ',', '.') ?></td>
    <td style="padding:4px 8px;white-space:nowrap;">
        <a href="index.php?navigate=editpart&id=<?= (int)$p['id'] ?>">Edit</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
