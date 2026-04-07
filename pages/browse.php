<?php
include 'connection.php';
include_once 'parts_helper.php';
include_once 'makes_helper.php';
include_once 'stats_helper.php';

parts_ensure_table($CarpartsConnection);
stats_day($CarpartsConnection, 'searches');

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_make  = isset($_GET['make'])  ? intval($_GET['make'])  : 0;
$filter_model = isset($_GET['model']) ? intval($_GET['model']) : 0;
$filter_year  = isset($_GET['year'])  ? intval($_GET['year'])  : 0;
$filter_cond  = isset($_GET['cond'])  ? intval($_GET['cond'])  : -1;
$filter_q     = isset($_GET['q'])     ? trim($_GET['q'])       : '';
$page         = max(1, isset($_GET['pg']) ? intval($_GET['pg']) : 1);
$per_page     = 20;
$offset       = ($page - 1) * $per_page;

$is_member = !empty($_SESSION['is_member']) || !empty($_SESSION['isadmin']);

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ["p.`visible` = 1", "COALESCE(p.`is_sold`,0) = 0"];
$params = [];
$types  = '';

if (!$is_member) {
    $where[] = "p.`visible_private` = 0";
}
if ($filter_make > 0) {
    $where[]  = "p.`make_id` = ?";
    $params[] = $filter_make;
    $types   .= 'i';
}
if ($filter_model > 0) {
    $where[]  = "p.`model_id` = ?";
    $params[] = $filter_model;
    $types   .= 'i';
}
if ($filter_year > 0) {
    $where[]  = "(p.`year_from` <= ? AND (p.`year_to` IS NULL OR p.`year_to` >= ?))";
    $params[] = $filter_year;
    $params[] = $filter_year;
    $types   .= 'ii';
}
if ($filter_cond >= 0 && $filter_cond <= 5) {
    $where[]  = "p.`condition` = ?";
    $params[] = $filter_cond;
    $types   .= 'i';
}
if ($filter_q !== '') {
    $like     = '%' . $filter_q . '%';
    $where[]  = "(p.`title` LIKE ? OR p.`oem_number` LIKE ? OR p.`replacement_number` LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$where_sql = implode(' AND ', $where);
$base_sql  = "FROM `PARTS` p
              JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
              LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
              WHERE {$where_sql}";

// Count total
$total_rows = 0;
$stmt = $CarpartsConnection->prepare("SELECT COUNT(*) {$base_sql}");
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($total_rows);
    $stmt->fetch();
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch page
$parts = [];
$stmt  = $CarpartsConnection->prepare(
    "SELECT p.`id`, p.`title`, p.`price`, p.`condition`, p.`year_from`, p.`year_to`,
            p.`stock`, p.`oem_number`, p.`visible_private`, p.`for_sale`, p.`created_at`,
            m.`name` AS make_name, mo.`name` AS model_name
     {$base_sql}
     ORDER BY p.`created_at` DESC
     LIMIT ? OFFSET ?"
);
if ($stmt) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types  = $types . 'ii';
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $parts[] = $row;
    $stmt->close();
}

$makes       = makes_list($CarpartsConnection);
$models_json = makes_all_models_json($CarpartsConnection);
mysqli_close($CarpartsConnection);

function browse_url(array $overrides = []): string {
    global $filter_make, $filter_model, $filter_year, $filter_cond, $filter_q;
    $current = array_filter([
        'navigate' => 'browse',
        'make'     => $filter_make  ?: null,
        'model'    => $filter_model ?: null,
        'year'     => $filter_year  ?: null,
        'cond'     => $filter_cond >= 0 ? $filter_cond : null,
        'q'        => $filter_q !== '' ? $filter_q : null,
    ], fn($v) => $v !== null);
    return 'index.php?' . http_build_query(array_merge($current, $overrides));
}
?>

<div class="content-box">
<h3>Browse Parts</h3>

<form method="get" action="index.php" style="margin-bottom:14px;">
    <input type="hidden" name="navigate" value="browse" />
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Search</label><br>
            <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>"
                   placeholder="Title / OEM number&hellip;" style="width:170px;padding:5px;" />
        </div>
        <div>
            <label style="font-size:12px;">Make</label><br>
            <select name="make" id="f_make" onchange="updateModels()" style="padding:5px;">
                <option value="0">All makes</option>
                <?php foreach ($makes as $mid => $mname): ?>
                <option value="<?= $mid ?>" <?= ($filter_make == $mid) ? 'selected' : '' ?>><?= htmlspecialchars($mname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Model</label><br>
            <select name="model" id="f_model" style="padding:5px;">
                <option value="0">All models</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Year</label><br>
            <input type="number" name="year" value="<?= $filter_year ?: '' ?>"
                   min="1900" max="2099" placeholder="e.g. 1992"
                   style="width:86px;padding:5px;" />
        </div>
        <div>
            <label style="font-size:12px;">Condition</label><br>
            <select name="cond" style="padding:5px;">
                <option value="-1" <?= ($filter_cond < 0) ? 'selected' : '' ?>>Any</option>
                <?php foreach (['Rubbish','Poor','Fair','Good','Very Good','Mint'] as $ci => $cl): ?>
                <option value="<?= $ci ?>" <?= ($filter_cond === $ci) ? 'selected' : '' ?>><?= $ci ?> — <?= htmlspecialchars($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="submit" value="Search" class="btn" style="padding:6px 16px;" />
            <a href="index.php?navigate=browse" style="margin-left:8px;font-size:12px;">Reset</a>
        </div>
    </div>
</form>

<script>
var _models = <?= $models_json ?>;
var _prevModel = <?= $filter_model ?>;
function updateModels() {
    var makeId = parseInt(document.getElementById('f_make').value);
    var sel = document.getElementById('f_model');
    sel.innerHTML = '<option value="0">All models</option>';
    if (makeId && _models[makeId]) {
        _models[makeId].forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.selected = (m.id === _prevModel);
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            sel.appendChild(o);
        });
    }
}
updateModels();
</script>

<p style="font-size:12px;color:#666;"><?= number_format($total_rows) ?> part(s) found.</p>

<?php if (empty($parts)): ?>
<p>No parts found matching your criteria.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
    <td style="padding:5px 8px;width:70px;"></td>
    <td style="padding:5px 8px;">Part</td>
    <td style="padding:5px 8px;">Make / Model</td>
    <td style="padding:5px 8px;">Year</td>
    <td style="padding:5px 8px;">Cond.</td>
    <td style="padding:5px 8px;">Stock</td>
    <td style="padding:5px 8px;text-align:right;">Price</td>
</tr>
<?php foreach ($parts as $p):
    $thumb = parts_first_photo((int)$p['id']);
?>
<tr style="border-bottom:1px solid var(--color-content-border);">
    <td style="padding:4px 8px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>">
        <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                 style="width:64px;height:48px;object-fit:cover;border-radius:3px;
                        border:1px solid var(--color-content-border);display:block;" />
        <?php else: ?>
            <div style="width:64px;height:48px;background:var(--color-surface);
                        border:1px dashed var(--color-content-border);border-radius:3px;
                        display:flex;align-items:center;justify-content:center;
                        font-size:18px;">🔧</div>
        <?php endif; ?>
        </a>
    </td>
    <td style="padding:4px 8px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a>
        <?php if ($p['visible_private']): ?><span style="font-size:10px;color:#c04040;"> [private]</span><?php endif; ?>
        <?php if (!$p['for_sale']): ?><br><small style="color:#666;">[display only]</small><?php endif; ?>
        <?php if (!empty($p['oem_number'])): ?><br><small style="color:#888;">OEM: <?= htmlspecialchars($p['oem_number']) ?></small><?php endif; ?>
        <br><small style="color:#aaa;font-size:11px;"><?= sprintf('PART-%05d', $p['id']) ?></small>
    </td>
    <td style="padding:4px 8px;">
        <?= htmlspecialchars($p['make_name']) ?>
        <?= $p['model_name'] ? '<br><small style="color:#888;">' . htmlspecialchars($p['model_name']) . '</small>' : '' ?>
    </td>
    <td style="padding:4px 8px;white-space:nowrap;"><?= (int)$p['year_from'] ?><?= $p['year_to'] ? '&ndash;' . (int)$p['year_to'] : '' ?></td>
    <td style="padding:4px 8px;"><?= (int)$p['condition'] ?>/5</td>
    <td style="padding:4px 8px;"><?= (int)$p['stock'] ?></td>
    <td style="padding:4px 8px;text-align:right;font-weight:bold;"><?= $p['price'] !== null ? '&euro;' . number_format((float)$p['price'], 2, ',', '.') : '<span style="color:#888;font-weight:normal;font-size:11px;">On request</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($total_pages > 1): ?>
<div style="margin-top:12px;display:flex;gap:5px;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <a href="<?= htmlspecialchars(browse_url(['pg' => $i])) ?>"
       style="padding:4px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;<?= ($i === $page) ? 'font-weight:bold;' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($_SESSION['authenticated'])): ?>
<p style="margin-top:14px;">
    <a href="index.php?navigate=addpart" class="btn" style="padding:7px 16px;">+ Add a part</a>
</p>
<?php endif; ?>
</div>
