<?php
include 'connection.php';
include_once 'parts_helper.php';
include_once 'makes_helper.php';
include_once 'stats_helper.php';
include_once 'settings_helper.php';

parts_ensure_table($CarpartsConnection);
stats_day($CarpartsConnection, 'searches');

$is_member = !empty($_SESSION['is_member']) || !empty($_SESSION['isadmin']);

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_make   = isset($_GET['make'])   ? intval($_GET['make'])   : 0;
$filter_model  = isset($_GET['model'])  ? intval($_GET['model'])  : 0;
$filter_year   = isset($_GET['year'])   ? intval($_GET['year'])   : 0;
$filter_cond   = isset($_GET['cond'])   ? intval($_GET['cond'])   : -1;
$filter_q      = isset($_GET['q'])      ? trim($_GET['q'])        : '';
$filter_seller = isset($_GET['seller']) ? intval($_GET['seller']) : 0;
$filter_sale   = isset($_GET['sale'])   ? intval($_GET['sale'])   : -1; // 1=for sale, 0=display only
$page          = max(1, isset($_GET['pg']) ? intval($_GET['pg']) : 1);
$per_page      = 24;

// ── Sort ──────────────────────────────────────────────────────────────────────
$sort_map = [
    'date'  => 'p.`created_at`',
    'title' => 'p.`title`',
    'make'  => 'm.`name`',
    'model' => 'COALESCE(mo.`name`, "")',
    'year'  => 'p.`year_from`',
    'cond'  => 'p.`condition`',
    'stock' => 'p.`stock`',
    'price' => 'p.`price`',
];
if ($is_member) $sort_map['private'] = 'p.`visible_private`';

$sort_labels = [
    'date'  => 'Date',
    'title' => 'Title',
    'make'  => 'Make',
    'model' => 'Model',
    'year'  => 'Year',
    'cond'  => 'Condition',
    'stock' => 'Qty',
    'price' => 'Price',
];
if ($is_member) $sort_labels['private'] = 'Private';

// Default direction per field (what you get when clicking a fresh column)
$sort_defaults = [
    'date' => 'desc', 'title' => 'asc', 'make' => 'asc', 'model' => 'asc',
    'year' => 'desc', 'cond'  => 'desc', 'stock' => 'desc', 'price' => 'asc',
    'private' => 'desc',
];

$filter_sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map)
    ? $_GET['sort'] : 'date';
$filter_dir  = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';

// Price ASC: put "on request" (NULL) at the end
if ($filter_sort === 'price' && $filter_dir === 'asc') {
    $order_by = 'CASE WHEN p.`price` IS NULL THEN 1 ELSE 0 END, p.`price` ASC';
} else {
    $order_by = $sort_map[$filter_sort] . ' ' . strtoupper($filter_dir);
}

$offset = ($page - 1) * $per_page;

// ── Browse view preference ────────────────────────────────────────────────────
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$browse_view = 'list';
if ($uid > 0) {
    $browse_view = settings_get($CarpartsConnection, "browse_view_u{$uid}", 'list');
} elseif (isset($_COOKIE['cpdb_browse_view']) && $_COOKIE['cpdb_browse_view'] === 'tile') {
    $browse_view = 'tile';
}

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = ["p.`visible` = 1", "COALESCE(p.`is_sold`,0) = 0"];
$params = [];
$types  = '';

if (!$is_member)    $where[] = "p.`visible_private` = 0";
if ($filter_make  > 0) { $where[] = "p.`make_id` = ?";  $params[] = $filter_make;  $types .= 'i'; }
if ($filter_model > 0) { $where[] = "p.`model_id` = ?"; $params[] = $filter_model; $types .= 'i'; }
if ($filter_year  > 0) {
    $where[] = "(p.`year_from` <= ? AND (p.`year_to` IS NULL OR p.`year_to` >= ?))";
    $params[] = $filter_year; $params[] = $filter_year; $types .= 'ii';
}
if ($filter_cond >= 0 && $filter_cond <= 5) {
    $where[] = "p.`condition` = ?"; $params[] = $filter_cond; $types .= 'i';
}
if ($filter_q !== '') {
    $like = '%' . $filter_q . '%';
    $where[] = "(p.`title` LIKE ? OR p.`oem_number` LIKE ? OR p.`replacement_number` LIKE ? OR p.`description` LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'ssss';
}
if ($filter_seller > 0) { $where[] = "p.`seller_id` = ?"; $params[] = $filter_seller; $types .= 'i'; }
if ($filter_sale === 1) { $where[] = "p.`for_sale` = 1"; }
if ($filter_sale === 0) { $where[] = "p.`for_sale` = 0"; }

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
if ($page > $total_pages) $page = $total_pages;

// Fetch page
$parts = [];
$stmt  = $CarpartsConnection->prepare(
    "SELECT p.`id`, p.`title`, p.`price`, p.`condition`, p.`year_from`, p.`year_to`,
            p.`stock`, p.`oem_number`, p.`visible_private`, p.`for_sale`, p.`created_at`,
            m.`name` AS make_name, mo.`name` AS model_name
     {$base_sql}
     ORDER BY {$order_by}
     LIMIT ? OFFSET ?"
);
if ($stmt) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types . 'ii', ...$all_params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($row = $res->fetch_assoc()) $parts[] = $row;
    $stmt->close();
}

// Only show makes that have at least one visible part
$_mq   = $CarpartsConnection->query(
    "SELECT DISTINCT m.`id`, m.`name` FROM `CAR_MAKES` m
     INNER JOIN `PARTS` p ON p.`make_id` = m.`id`
     WHERE p.`visible` = 1 AND p.`is_sold` = 0
     ORDER BY m.`name` ASC"
);
$makes = [];
if ($_mq) while ($r = $_mq->fetch_assoc()) $makes[(int)$r['id']] = $r['name'];
$models_json = makes_all_models_json($CarpartsConnection);

// Batch-fetch "also fits" for displayed parts
$compat_makes = [];
if (!empty($parts)) {
    $pids = implode(',', array_map(fn($p) => (int)$p['id'], $parts));
    $cq = $CarpartsConnection->query(
        "SELECT pc.part_id, GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS makes
         FROM PART_COMPAT pc JOIN CAR_MAKES m ON m.id = pc.make_id
         WHERE pc.part_id IN ({$pids}) GROUP BY pc.part_id"
    );
    if ($cq) while ($cr = $cq->fetch_assoc()) $compat_makes[(int)$cr['part_id']] = $cr['makes'];
}

mysqli_close($CarpartsConnection);

// ── Helpers ───────────────────────────────────────────────────────────────────
function browse_url(array $overrides = []): string {
    global $filter_make, $filter_model, $filter_year, $filter_cond, $filter_q,
           $filter_seller, $filter_sort, $filter_dir, $filter_sale, $page;
    $current = array_filter([
        'navigate' => 'browse',
        'make'     => $filter_make   ?: null,
        'model'    => $filter_model  ?: null,
        'year'     => $filter_year   ?: null,
        'cond'     => $filter_cond  >= 0 ? $filter_cond : null,
        'q'        => $filter_q !== '' ? $filter_q : null,
        'seller'   => $filter_seller ?: null,
        'sale'     => $filter_sale  >= 0 ? $filter_sale : null,
        'sort'     => $filter_sort !== 'date' ? $filter_sort : null,
        'dir'      => ($filter_sort !== 'date' || $filter_dir !== 'desc') ? $filter_dir : null,
        'pg'       => $page > 1 ? $page : null,
    ], fn($v) => $v !== null);
    return 'index.php?' . http_build_query(array_merge($current, $overrides));
}

function browse_paginate(int $current, int $total): array {
    if ($total <= 1) return [];
    $show = []; $prev = null;
    for ($i = 1; $i <= $total; $i++) {
        if ($i === 1 || $i === $total || abs($i - $current) <= 2) {
            if ($prev !== null && $i - $prev > 1) $show[] = 0;
            $show[] = $i; $prev = $i;
        }
    }
    return $show;
}
?>

<div class="content-box">
<h3>Browse Parts</h3>

<!-- Sticky filter bar -->
<div id="filter-bar" style="position:sticky;top:0;z-index:50;
     background:var(--color-content-bg,var(--color-surface));
     padding-bottom:10px;margin-bottom:4px;
     border-bottom:1px solid var(--color-content-border);">
<form method="get" action="index.php" id="browse-filter-form">
    <input type="hidden" name="navigate" value="browse" />
    <input type="hidden" name="sort" value="<?= htmlspecialchars($filter_sort) ?>" />
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($filter_dir) ?>" />
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Search</label><br>
            <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>"
                   placeholder="Title / OEM / description…" style="width:160px;padding:5px;" />
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
                   min="1900" max="2099" placeholder="e.g. 1994" style="width:86px;padding:5px;" />
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
            <label style="font-size:12px;">Listing</label><br>
            <select name="sale" style="padding:5px;">
                <option value="-1" <?= ($filter_sale < 0)  ? 'selected' : '' ?>>All</option>
                <option value="1"  <?= ($filter_sale === 1) ? 'selected' : '' ?>>For sale</option>
                <option value="0"  <?= ($filter_sale === 0) ? 'selected' : '' ?>>Display only</option>
            </select>
        </div>
        <div>
            <input type="submit" value="Search" class="btn" style="padding:6px 16px;" />
            <a href="index.php?navigate=browse" style="margin-left:8px;font-size:12px;">Reset</a>
        </div>
    </div>
</form>

<!-- Sort bar -->
<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;align-items:center;font-size:12px;">
    <span style="color:#888;margin-right:4px;">Sort:</span>
    <?php foreach ($sort_labels as $key => $label):
        $active   = ($filter_sort === $key);
        $new_dir  = $active
            ? ($filter_dir === 'asc' ? 'desc' : 'asc')
            : ($sort_defaults[$key] ?? 'asc');
        $arrow    = $active ? ($filter_dir === 'asc' ? ' &#9650;' : ' &#9660;') : '';
        $url      = htmlspecialchars(browse_url(['sort' => $key, 'dir' => $new_dir, 'pg' => 1]));
    ?>
    <a href="<?= $url ?>"
       style="padding:3px 8px;border:1px solid var(--color-content-border);border-radius:3px;
              text-decoration:none;white-space:nowrap;
              <?= $active ? 'font-weight:bold;background:var(--color-nav-hover-bg);' : '' ?>">
        <?= htmlspecialchars($label) ?><?= $arrow ?>
    </a>
    <?php endforeach; ?>
</div>
</div>

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
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '–' + m.yt : '–') + ')' : '');
            sel.appendChild(o);
        });
    }
}
updateModels();
</script>

<?php if ($filter_seller > 0): ?>
<p style="font-size:12px;color:#888;margin-bottom:6px;">
    Showing listings by one seller. <a href="index.php?navigate=browse">Show all</a>
</p>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin:10px 0 8px;">
    <p style="font-size:12px;color:#666;margin:0;">
        <?= number_format($total_rows) ?> part<?= $total_rows !== 1 ? 's' : '' ?> found
        <?php if ($total_pages > 1): ?> &mdash; page <?= $page ?> of <?= $total_pages ?><?php endif; ?>
    </p>
    <div style="display:flex;gap:4px;">
        <button id="btn-list" onclick="setView('list')"
                style="padding:4px 10px;font-size:12px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
            &#9776; List
        </button>
        <button id="btn-tile" onclick="setView('tile')"
                style="padding:4px 10px;font-size:12px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
            &#9726; Tiles
        </button>
    </div>
</div>

<?php if (empty($parts)): ?>
<p>No parts found matching your criteria.</p>
<?php else: ?>

<!-- ── List view ──────────────────────────────────────────────────────────────── -->
<div id="view-list">
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
    <td style="padding:5px 8px;width:70px;"></td>
    <td style="padding:5px 8px;">Part</td>
    <td style="padding:5px 8px;">Make / Model</td>
    <td style="padding:5px 8px;">Year</td>
    <td style="padding:5px 8px;">Cond.</td>
    <td style="padding:5px 8px;">Qty</td>
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
                        display:flex;align-items:center;justify-content:center;font-size:18px;">&#128295;</div>
        <?php endif; ?>
        </a>
    </td>
    <td style="padding:4px 8px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a>
        <?php if ($p['visible_private']): ?><span style="font-size:10px;color:#c04040;"> [private]</span><?php endif; ?>
        <?php if (!$p['for_sale']): ?><br><small style="color:#666;">[display only]</small><?php endif; ?>
        <?php if (!empty($p['oem_number'])): ?><br><small style="color:#888;">OEM: <?= htmlspecialchars($p['oem_number']) ?></small><?php endif; ?>
        <?php if (isset($compat_makes[$p['id']])): ?><br><small style="color:#aaa;font-size:10px;">Also fits: <?= htmlspecialchars($compat_makes[$p['id']]) ?></small><?php endif; ?>
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
</div>

<!-- ── Tile view ──────────────────────────────────────────────────────────────── -->
<div id="view-tile" style="display:none;">
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
<?php foreach ($parts as $p):
    $thumb = parts_first_photo((int)$p['id']);
?>
<a href="index.php?navigate=viewpart&id=<?= (int)$p['id'] ?>"
   style="display:block;border:1px solid var(--color-content-border);border-radius:6px;
          overflow:hidden;text-decoration:none;color:inherit;background:var(--color-surface);
          transition:box-shadow .15s;"
   onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.15)'"
   onmouseout="this.style.boxShadow='none'">
    <?php if ($thumb): ?>
    <img src="<?= htmlspecialchars($thumb) ?>" alt=""
         style="width:100%;height:130px;object-fit:cover;display:block;" />
    <?php else: ?>
    <div style="width:100%;height:130px;background:var(--color-input-bg);
                display:flex;align-items:center;justify-content:center;font-size:32px;">&#128295;</div>
    <?php endif; ?>
    <div style="padding:8px 10px;">
        <div style="font-size:13px;font-weight:bold;line-height:1.3;margin-bottom:4px;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['title']) ?></div>
        <div style="font-size:11px;color:#888;margin-bottom:2px;">
            <?= htmlspecialchars($p['make_name']) ?><?= $p['model_name'] ? ' &mdash; ' . htmlspecialchars($p['model_name']) : '' ?>
        </div>
        <?php if (isset($compat_makes[$p['id']])): ?>
        <div style="font-size:10px;color:#aaa;margin-bottom:2px;">Also: <?= htmlspecialchars($compat_makes[$p['id']]) ?></div>
        <?php endif; ?>
        <div style="font-size:13px;font-weight:bold;color:var(--color-accent);">
            <?= $p['price'] !== null ? '&euro;' . number_format((float)$p['price'], 2, ',', '.') : '<span style="font-size:11px;color:#888;font-weight:normal;">On request</span>' ?>
        </div>
        <div style="font-size:11px;color:#aaa;margin-top:2px;"><?= sprintf('PART-%05d', $p['id']) ?></div>
    </div>
</a>
<?php endforeach; ?>
</div>
</div>

<!-- ── Pagination ──────────────────────────────────────────────────────────── -->
<?php $pager = browse_paginate($page, $total_pages); ?>
<?php if (!empty($pager)): ?>
<div style="margin-top:14px;display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
    <?php if ($page > 1): ?>
    <a href="<?= htmlspecialchars(browse_url(['pg' => $page - 1])) ?>"
       style="padding:5px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;">&#8592; Prev</a>
    <?php endif; ?>
    <?php foreach ($pager as $pg): ?>
        <?php if ($pg === 0): ?>
        <span style="padding:5px 4px;font-size:12px;color:#888;">&hellip;</span>
        <?php elseif ($pg === $page): ?>
        <span style="padding:5px 10px;border:1px solid var(--color-accent);border-radius:3px;font-size:12px;font-weight:bold;background:var(--color-nav-hover-bg);"><?= $pg ?></span>
        <?php else: ?>
        <a href="<?= htmlspecialchars(browse_url(['pg' => $pg])) ?>"
           style="padding:5px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;"><?= $pg ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($page < $total_pages): ?>
    <a href="<?= htmlspecialchars(browse_url(['pg' => $page + 1])) ?>"
       style="padding:5px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;">Next &#8594;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($_SESSION['authenticated'])): ?>
<p style="margin-top:14px;">
    <a href="index.php?navigate=addpart" class="btn" style="padding:7px 16px;">+ Add a part</a>
</p>
<?php endif; ?>
</div>

<script>
var _currentView = '<?= htmlspecialchars($browse_view) ?>';
var _loggedIn    = <?= $uid > 0 ? 'true' : 'false' ?>;

function setView(v) {
    _currentView = v;
    document.getElementById('view-list').style.display = (v === 'list') ? '' : 'none';
    document.getElementById('view-tile').style.display = (v === 'tile') ? '' : 'none';
    document.getElementById('btn-list').style.fontWeight = (v === 'list') ? 'bold' : 'normal';
    document.getElementById('btn-tile').style.fontWeight = (v === 'tile') ? 'bold' : 'normal';
    if (_loggedIn) {
        fetch('index.php?navigate=savebrowseview&ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'view=' + encodeURIComponent(v)
        });
    } else {
        document.cookie = 'cpdb_browse_view=' + encodeURIComponent(v) + '; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
    }
}
setView(_currentView);
</script>
