<?php
include 'connection.php';
include 'stats_helper.php';
include_once 'settings_helper.php';
include_once 'parts_helper.php';
include_once 'users_helper.php';

stats_session_check($CarpartsConnection);
parts_ensure_table($CarpartsConnection);

// Purge unconfirmed signups older than 7 days — run at most once per day
$_cleanup_due = settings_get($CarpartsConnection, 'last_signup_cleanup', '2000-01-01 00:00:00');
if (strtotime($_cleanup_due) < time() - 86400) {
    users_cleanup_unconfirmed($CarpartsConnection);
    settings_set($CarpartsConnection, 'last_signup_cleanup', date('Y-m-d H:i:s'));
}
unset($_cleanup_due);

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_parts  = 0;
$total_makes  = 0;
$total_sellers = 0;
$recent_parts = [];

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `PARTS` WHERE `visible`=1");
if ($r) $total_parts = (int)$r->fetch_row()[0];

$r = $CarpartsConnection->query("SELECT COUNT(DISTINCT `make_id`) FROM `PARTS` WHERE `visible`=1");
if ($r) $total_makes = (int)$r->fetch_row()[0];

$r = $CarpartsConnection->query("SELECT COUNT(DISTINCT `seller_id`) FROM `PARTS` WHERE `visible`=1");
if ($r) $total_sellers = (int)$r->fetch_row()[0];

// Top 10 makes by part count
$top_makes = [];
$mq = $CarpartsConnection->query(
    "SELECT m.`name`, COUNT(p.`id`) AS cnt
     FROM `PARTS` p
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     WHERE p.`visible` = 1
     GROUP BY m.`id`, m.`name`
     ORDER BY cnt DESC LIMIT 10"
);
if ($mq) while ($mr = $mq->fetch_assoc()) $top_makes[] = $mr;

// Condition breakdown
$cond_counts = array_fill(0, 6, 0);
$cq = $CarpartsConnection->query(
    "SELECT `condition`, COUNT(*) AS cnt FROM `PARTS` WHERE `visible`=1 GROUP BY `condition`"
);
if ($cq) while ($cr = $cq->fetch_assoc()) {
    $cond_counts[(int)$cr['condition']] = (int)$cr['cnt'];
}

// Recent parts (last 6)
$rq = $CarpartsConnection->query(
    "SELECT p.`id`, p.`title`, p.`price`, p.`condition`, p.`year_from`, p.`year_to`, p.`created_at`,
            m.`name` AS make_name, mo.`name` AS model_name
     FROM `PARTS` p
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
     WHERE p.`visible` = 1
     ORDER BY p.`created_at` DESC LIMIT 6"
);
if ($rq) while ($rr = $rq->fetch_assoc()) $recent_parts[] = $rr;

// Monthly stats
$mstats = $CarpartsConnection->query(
    "SELECT DATE_FORMAT(stat_date,'%Y-%m') AS maand,
            SUM(sessions)    AS sessions,
            SUM(searches)    AS searches,
            SUM(parts_added) AS parts_added,
            SUM(images_added)AS images_added
     FROM STATS_DAILY
     GROUP BY DATE_FORMAT(stat_date,'%Y-%m')
     ORDER BY maand DESC LIMIT 6"
);

// News
$CarpartsConnection->query("CREATE TABLE IF NOT EXISTS HOME_NEWS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    news_date DATE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1
)");
$news_items = [];
$nq = $CarpartsConnection->query(
    "SELECT title, body FROM HOME_NEWS WHERE visible=1 ORDER BY sort_order DESC, news_date DESC"
);
if ($nq) while ($nr = $nq->fetch_assoc()) $news_items[] = $nr;
?>

<div class="content-box">
<h3>Car Parts DB — Used Car Parts Marketplace</h3>
<p>Find used car parts by make, model and year. Sell your spare parts directly to other enthusiasts.</p>

<!-- Stat pills -->
<div style="display:flex;gap:14px;flex-wrap:wrap;margin:16px 0;">
    <div style="background:var(--color-surface);border:1px solid var(--color-content-border);border-radius:6px;padding:14px 20px;text-align:center;min-width:100px;">
        <div style="font-size:28px;font-weight:bold;color:var(--color-accent);"><?= number_format($total_parts) ?></div>
        <div style="font-size:11px;color:#5a7a90;margin-top:3px;">Parts listed</div>
    </div>
    <div style="background:var(--color-surface);border:1px solid var(--color-content-border);border-radius:6px;padding:14px 20px;text-align:center;min-width:100px;">
        <div style="font-size:28px;font-weight:bold;color:var(--color-accent);"><?= number_format($total_makes) ?></div>
        <div style="font-size:11px;color:#5a7a90;margin-top:3px;">Car makes</div>
    </div>
    <div style="background:var(--color-surface);border:1px solid var(--color-content-border);border-radius:6px;padding:14px 20px;text-align:center;min-width:100px;">
        <div style="font-size:28px;font-weight:bold;color:var(--color-accent);"><?= number_format($total_sellers) ?></div>
        <div style="font-size:11px;color:#5a7a90;margin-top:3px;">Sellers</div>
    </div>
</div>

<?php if (!empty($top_makes)): ?>
<!-- Top makes bar chart -->
<div style="margin:18px 0;">
    <div style="font-size:12px;font-weight:bold;color:var(--color-accent);margin-bottom:10px;">Parts by make</div>
    <?php
    $max_cnt = max(array_column($top_makes, 'cnt') ?: [1]);
    foreach ($top_makes as $tm):
        $pct = round($tm['cnt'] / $max_cnt * 100, 1);
    ?>
    <div style="display:flex;align-items:center;gap:8px;margin:4px 0;">
        <div style="width:120px;font-size:12px;color:var(--color-text);text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= htmlspecialchars($tm['name']) ?>
        </div>
        <div style="flex:1;height:16px;background:rgba(59,73,90,0.1);border-radius:3px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--btn-bg,#555);border-radius:3px;"></div>
        </div>
        <div style="width:28px;font-size:11px;color:#5a7a90;"><?= (int)$tm['cnt'] ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (array_sum($cond_counts) > 0): ?>
<!-- Condition breakdown -->
<div style="margin:18px 0;">
    <div style="font-size:12px;font-weight:bold;color:var(--color-accent);margin-bottom:8px;">Condition breakdown</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php
    $cond_labels = ['Rubbish','Poor','Fair','Good','Very Good','Mint'];
    $cond_colors = ['#c04040','#c87020','#c8a020','#5588bb','#3aaa3a','#2a6a2a'];
    foreach ($cond_labels as $ci => $cl):
        if ($cond_counts[$ci] == 0) continue;
    ?>
    <div style="background:var(--color-surface);border:1px solid var(--color-content-border);border-radius:4px;padding:6px 12px;font-size:12px;">
        <span style="display:inline-block;width:9px;height:9px;background:<?= $cond_colors[$ci] ?>;border-radius:50%;margin-right:4px;vertical-align:middle;"></span>
        <?= htmlspecialchars($cl) ?>: <strong><?= $cond_counts[$ci] ?></strong>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<p style="margin-top:14px;">
    <a href="index.php?navigate=browse" class="btn" style="padding:8px 18px;font-size:14px;">Browse all parts</a>
    <a href="index.php?navigate=addpart" class="btn" style="padding:8px 18px;font-size:14px;margin-left:10px;">Sell a part</a>
</p>
</div>

<?php if (!empty($recent_parts)): ?>
<div class="content-box">
<h3>Recently listed</h3>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
    <td style="padding:6px 8px;width:70px;"></td>
    <td style="padding:6px 10px;">Part</td>
    <td style="padding:6px 10px;">Make / Model</td>
    <td style="padding:6px 10px;">Condition</td>
    <td style="padding:6px 10px;text-align:right;">Price</td>
</tr>
<?php foreach ($recent_parts as $rp):
    $thumb = parts_first_photo((int)$rp['id']);
?>
<tr style="border-bottom:1px solid var(--color-content-border);">
    <td style="padding:4px 8px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$rp['id'] ?>">
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
    <td style="padding:5px 10px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$rp['id'] ?>"><?= htmlspecialchars($rp['title']) ?></a>
        <br><small style="color:#888;font-size:11px;"><?= htmlspecialchars(sprintf('PART-%05d', $rp['id'])) ?></small>
    </td>
    <td style="padding:5px 10px;">
        <?= htmlspecialchars($rp['make_name']) ?>
        <?= $rp['model_name'] ? '<br><small style="color:#888;">' . htmlspecialchars($rp['model_name']) . '</small>' : '' ?>
    </td>
    <td style="padding:5px 10px;"><?= htmlspecialchars(parts_condition_label((int)$rp['condition'])) ?></td>
    <td style="padding:5px 10px;text-align:right;font-weight:bold;">
        &euro;<?= number_format((float)$rp['price'], 2, ',', '.') ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<?php if ($mstats && $mstats->num_rows > 0): ?>
<div class="content-box">
<h3>Monthly activity</h3>
<table style="border-collapse:collapse;font-size:11px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
    <td style="padding:4px 10px;">Month</td>
    <td style="padding:4px 10px;text-align:right;">Sessions</td>
    <td style="padding:4px 10px;text-align:right;">Searches</td>
    <td style="padding:4px 10px;text-align:right;">Parts added</td>
    <td style="padding:4px 10px;text-align:right;">Photos added</td>
</tr>
<?php while ($ms = $mstats->fetch_assoc()): ?>
<tr style="border-bottom:1px solid var(--color-content-border);">
    <td style="padding:3px 10px;"><?= htmlspecialchars($ms['maand']) ?></td>
    <td style="padding:3px 10px;text-align:right;"><?= intval($ms['sessions']) ?></td>
    <td style="padding:3px 10px;text-align:right;"><?= intval($ms['searches']) ?></td>
    <td style="padding:3px 10px;text-align:right;"><?= intval($ms['parts_added']) ?></td>
    <td style="padding:3px 10px;text-align:right;"><?= intval($ms['images_added']) ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
<?php endif; ?>

<?php foreach ($news_items as $ni): ?>
<div class="content-box">
    <h3><?= htmlspecialchars($ni['title']) ?></h3>
    <?= $ni['body'] ?>
</div>
<?php endforeach; ?>

<?php mysqli_close($CarpartsConnection); ?>
