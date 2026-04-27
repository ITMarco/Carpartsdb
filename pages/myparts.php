<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to view your parts.</p></div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'makes_helper.php';
include_once 'image_helper.php';
include_once 'settings_helper.php';

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

// Load view preference (same key as browse)
$view_pref = settings_get($CarpartsConnection, 'browse_view_u' . $seller_id, 'list');

mysqli_close($CarpartsConnection);
?>

<div class="content-box">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <h3 style="margin:0;">My Parts Collection</h3>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:12px;color:#888;">View:</span>
        <button type="button" id="mp-btn-list" onclick="mpSetView('list')"
                style="padding:4px 10px;font-size:12px;border:1px solid var(--color-content-border);border-radius:3px;cursor:pointer;">&#9776; List</button>
        <button type="button" id="mp-btn-tiles" onclick="mpSetView('tiles')"
                style="padding:4px 10px;font-size:12px;border:1px solid var(--color-content-border);border-radius:3px;cursor:pointer;">&#9632; Tiles</button>
        <a href="index.php?navigate=addpart" class="btn" style="padding:7px 16px;">+ Add new part</a>
    </div>
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

    <!-- ── List view ──────────────────────────────────────────────────────── -->
    <div id="mp-list-view" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);background:var(--color-nav-hover-bg);">
        <td style="padding:5px 8px;width:20px;"></td>
        <td style="padding:5px 8px;width:60px;">Photo</td>
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
        $thumb = parts_first_photo((int)$p['id']);
    ?>
    <tr style="border-bottom:1px solid var(--color-content-border);<?= $row_style ?>">
        <td style="padding:4px 8px;">
            <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="bulk-cb" />
        </td>
        <td style="padding:4px 8px;">
            <?php if ($thumb): ?>
            <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>">
                <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                     style="width:50px;height:40px;object-fit:cover;border-radius:3px;display:block;" />
            </a>
            <?php else: ?>
            <div style="width:50px;height:40px;background:var(--color-nav-hover-bg);border-radius:3px;
                        display:flex;align-items:center;justify-content:center;font-size:18px;color:#ccc;">&#128247;</div>
            <?php endif; ?>
        </td>
        <td style="padding:4px 8px;font-size:11px;white-space:nowrap;">
            <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"><?= sprintf('PART-%05d', $p['id']) ?></a>
        </td>
        <td style="padding:4px 8px;">
            <?= htmlspecialchars($p['title']) ?>
        </td>
        <td style="padding:4px 8px;">
            <?= htmlspecialchars($p['make_name']) ?><?= $p['model_name'] ? ' / ' . htmlspecialchars($p['model_name']) : '' ?><br>
            <small style="color:#aaa;"><?= (int)$p['year_from'] ?><?= $p['year_to'] ? '&ndash;' . (int)$p['year_to'] : '' ?></small>
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

    <!-- ── Tile view ──────────────────────────────────────────────────────── -->
    <div id="mp-tile-view" style="display:none;">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
    <?php foreach ($parts as $p):
        $thumb = parts_first_photo((int)$p['id']);
        $sold_overlay = $p['is_sold'] ? 'opacity:.55;' : '';
    ?>
    <div style="border:1px solid var(--color-content-border);border-radius:5px;overflow:hidden;
                background:var(--color-surface);position:relative;<?= $sold_overlay ?>">
        <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="bulk-cb"
               style="position:absolute;top:6px;left:6px;z-index:2;" />
        <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>" style="display:block;text-decoration:none;color:inherit;">
            <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                 style="width:100%;height:110px;object-fit:cover;display:block;" />
            <?php else: ?>
            <div style="width:100%;height:110px;background:var(--color-nav-hover-bg);
                        display:flex;align-items:center;justify-content:center;font-size:32px;color:#ccc;">&#128247;</div>
            <?php endif; ?>
            <div style="padding:7px 8px;">
                <div style="font-size:12px;font-weight:bold;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                     title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></div>
                <div style="font-size:11px;color:#888;margin-top:2px;">
                    <?= htmlspecialchars($p['make_name']) ?>
                    <?= $p['model_name'] ? ' / ' . htmlspecialchars($p['model_name']) : '' ?>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:5px;">
                    <span style="font-size:12px;">
                        <?= $p['price'] !== null ? '&euro;' . number_format((float)$p['price'], 2, ',', '.') : '<span style="color:#888;">On req.</span>' ?>
                    </span>
                    <span style="font-size:11px;color:#888;" title="Views">&#128065; <?= number_format((int)$p['view_count']) ?></span>
                </div>
                <div style="font-size:11px;margin-top:4px;">
                    <?php if ($p['is_sold']): ?>
                    <span style="color:#c04040;font-weight:bold;">Sold</span>
                    <?php elseif (!$p['visible']): ?>
                    <span style="color:#888;">Private</span>
                    <?php elseif ($p['visible_private']): ?>
                    <span style="color:#448;">Incrowd</span>
                    <?php else: ?>
                    <span style="color:#2a7a2a;">Public</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <div style="padding:4px 8px 7px;border-top:1px solid var(--color-content-border);font-size:11px;display:flex;gap:8px;">
            <a href="index.php?navigate=editpart&id=<?= (int)$p['id'] ?>">Edit</a>
            <a href="index.php?navigate=deletepartimage&id=<?= (int)$p['id'] ?>">Photos</a>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    </div>

</form>

<script>
var _mpView = '<?= ($view_pref === 'tiles') ? 'tiles' : 'list' ?>';

function mpSetView(v) {
    _mpView = v;
    document.getElementById('mp-list-view').style.display  = (v === 'list')  ? '' : 'none';
    document.getElementById('mp-tile-view').style.display  = (v === 'tiles') ? '' : 'none';
    document.getElementById('mp-btn-list').style.fontWeight  = (v === 'list')  ? 'bold' : '';
    document.getElementById('mp-btn-tiles').style.fontWeight = (v === 'tiles') ? 'bold' : '';
    // Persist using same key as browse page
    var fd = new FormData();
    fd.append('view', v);
    fetch('index.php?navigate=savebrowseview&ajax=1', {method:'POST', body:fd}).catch(function(){});
    document.cookie = 'cpdb_browse_view=' + v + ';path=/;max-age=31536000';
}

function toggleAll(checked) {
    document.querySelectorAll('.bulk-cb').forEach(function(cb) { cb.checked = checked; });
}
function confirmBulk(msg) {
    var checked = document.querySelectorAll('.bulk-cb:checked').length;
    if (checked === 0) { alert('Select at least one part first.'); return false; }
    return confirm(checked + ' part(s) selected. ' + msg);
}

mpSetView(_mpView);
</script>
<?php endif; ?>
</div>
