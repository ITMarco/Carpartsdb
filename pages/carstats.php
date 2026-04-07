<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Geen toegang.</div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'parts_helper.php';
include_once 'stats_helper.php';
parts_ensure_table($CarpartsConnection);
stats_ensure_table($CarpartsConnection);

// ── Summary counts ────────────────────────────────────────────────────────────
$r = $CarpartsConnection->query("SELECT
    COUNT(*) AS total,
    SUM(COALESCE(`is_sold`,0)) AS sold,
    SUM(CASE WHEN COALESCE(`is_sold`,0)=0 AND `visible`=1 THEN 1 ELSE 0 END) AS active
    FROM `PARTS`");
$counts = $r ? $r->fetch_assoc() : ['total'=>0,'sold'=>0,'active'=>0];

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `USERS`");
$user_count = $r ? $r->fetch_row()[0] : 0;

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `PART_MESSAGES`");
$msg_count = $r ? $r->fetch_row()[0] : 0;

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `read_at` IS NULL");
$unread_count = $r ? $r->fetch_row()[0] : 0;

// ── Top 10 makes ─────────────────────────────────────────────────────────────
$top_makes = $CarpartsConnection->query(
    "SELECT m.`name`, COUNT(p.`id`) AS cnt
     FROM `PARTS` p JOIN `CAR_MAKES` m ON m.`id`=p.`make_id`
     WHERE p.`visible`=1 AND COALESCE(p.`is_sold`,0)=0
     GROUP BY m.`id` ORDER BY cnt DESC LIMIT 10"
);

// ── Top 10 sellers ────────────────────────────────────────────────────────────
$top_sellers = $CarpartsConnection->query(
    "SELECT u.`realname`, u.`email`, COUNT(p.`id`) AS total,
            SUM(COALESCE(p.`is_sold`,0)) AS sold
     FROM `PARTS` p JOIN `USERS` u ON u.`id`=p.`seller_id`
     GROUP BY u.`id` ORDER BY total DESC LIMIT 10"
);

// ── Recent 20 listings ────────────────────────────────────────────────────────
$recent_parts = $CarpartsConnection->query(
    "SELECT p.`id`, p.`title`, p.`price`, p.`is_sold`, p.`created_at`,
            m.`name` AS make_name, u.`realname` AS seller_name
     FROM `PARTS` p
     JOIN `CAR_MAKES` m ON m.`id`=p.`make_id`
     JOIN `USERS` u ON u.`id`=p.`seller_id`
     ORDER BY p.`created_at` DESC LIMIT 20"
);

// ── Daily stats (last 30 days) ────────────────────────────────────────────────
$daily = $CarpartsConnection->query(
    "SELECT `stat_date`, `sessions`, `searches`, `parts_added`, `images_added`
     FROM `STATS_DAILY`
     ORDER BY `stat_date` DESC LIMIT 30"
);

// ── Recent messages ───────────────────────────────────────────────────────────
$recent_msgs = $CarpartsConnection->query(
    "SELECT pm.`name`, pm.`email`, pm.`message`, pm.`created_at`, pm.`read_at`,
            p.`id` AS part_id, p.`title` AS part_title
     FROM `PART_MESSAGES` pm
     JOIN `PARTS` p ON p.`id`=pm.`part_id`
     ORDER BY pm.`created_at` DESC LIMIT 20"
);
?>

<div class="content-box">
<h3>Parts DB Statistics</h3>

<!-- Summary tiles -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:10px 0 24px;">
<?php foreach ([
    ['Active listings', $counts['active'], ''],
    ['Sold',            $counts['sold'],   ''],
    ['Total parts',     $counts['total'],  ''],
    ['Sellers',         $user_count,       ''],
    ['Messages',        $msg_count,        ''],
    ['Unread messages', $unread_count,     $unread_count > 0 ? 'color:var(--color-accent);' : ''],
] as [$label, $val, $style]): ?>
<div style="background:var(--color-input-bg);border:1px solid var(--color-nav-border);border-radius:6px;padding:14px 22px;text-align:center;min-width:90px;">
  <div style="font-size:26px;font-weight:bold;<?= $style ?>color:var(--color-accent);"><?= number_format((int)$val) ?></div>
  <div style="font-size:11px;color:var(--color-text);"><?= $label ?></div>
</div>
<?php endforeach; ?>
</div>

<div style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-start;">

<!-- Top makes -->
<div style="min-width:200px;">
<h4 style="margin-bottom:8px;">Top 10 Makes (active)</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Make</th>
  <th style="padding:4px 8px;text-align:right;">Parts</th>
</tr>
<?php $i=1; while ($row = $top_makes->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:4px 8px;"><?= htmlspecialchars($row['name']) ?></td>
  <td style="padding:4px 8px;text-align:right;font-weight:bold;"><?= $row['cnt'] ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

<!-- Top sellers -->
<div style="min-width:260px;">
<h4 style="margin-bottom:8px;">Top 10 Sellers</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Seller</th>
  <th style="padding:4px 8px;text-align:right;">Listed</th>
  <th style="padding:4px 8px;text-align:right;">Sold</th>
</tr>
<?php $i=1; while ($row = $top_sellers->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:4px 8px;">
    <?= htmlspecialchars($row['realname'] ?: $row['email']) ?>
  </td>
  <td style="padding:4px 8px;text-align:right;font-weight:bold;"><?= $row['total'] ?></td>
  <td style="padding:4px 8px;text-align:right;color:#888;"><?= $row['sold'] ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

</div><!-- /flex -->

<!-- Recent listings -->
<h4 style="margin:24px 0 8px;">Last 20 Listings</h4>
<div style="overflow-x:auto;">
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Date</th>
  <th style="padding:4px 8px;text-align:left;">Part</th>
  <th style="padding:4px 8px;text-align:left;">Make</th>
  <th style="padding:4px 8px;text-align:right;">Price</th>
  <th style="padding:4px 8px;text-align:left;">Seller</th>
  <th style="padding:4px 8px;text-align:left;">Status</th>
</tr>
<?php $i=1; while ($row = $recent_parts->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:3px 8px;white-space:nowrap;color:#7a9ab0;"><?= htmlspecialchars(date('d-m-Y', strtotime($row['created_at']))) ?></td>
  <td style="padding:3px 8px;"><a href="index.php?navigate=viewpart&id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
  <td style="padding:3px 8px;"><?= htmlspecialchars($row['make_name']) ?></td>
  <td style="padding:3px 8px;text-align:right;"><?= $row['price'] !== null ? '&euro;' . number_format((float)$row['price'], 2) : '<span style="color:#888;font-size:11px;">–</span>' ?></td>
  <td style="padding:3px 8px;"><?= htmlspecialchars($row['seller_name']) ?></td>
  <td style="padding:3px 8px;">
    <?php if ($row['is_sold']): ?>
    <span style="color:#5588bb;font-size:11px;font-weight:bold;">SOLD</span>
    <?php else: ?>
    <span style="color:#3a8;font-size:11px;">Active</span>
    <?php endif; ?>
  </td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

<!-- Daily stats -->
<h4 style="margin:24px 0 8px;">Daily Activity (last 30 days)</h4>
<div style="overflow-x:auto;">
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Date</th>
  <th style="padding:4px 8px;text-align:right;">Sessions</th>
  <th style="padding:4px 8px;text-align:right;">Searches</th>
  <th style="padding:4px 8px;text-align:right;">Parts added</th>
  <th style="padding:4px 8px;text-align:right;">Images added</th>
</tr>
<?php $i=1; while ($row = $daily->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:3px 8px;white-space:nowrap;color:#7a9ab0;"><?= htmlspecialchars(date('d-m-Y', strtotime($row['stat_date']))) ?></td>
  <td style="padding:3px 8px;text-align:right;"><?= $row['sessions'] ?></td>
  <td style="padding:3px 8px;text-align:right;"><?= $row['searches'] ?></td>
  <td style="padding:3px 8px;text-align:right;"><?= $row['parts_added'] ?></td>
  <td style="padding:3px 8px;text-align:right;"><?= $row['images_added'] ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

<!-- Recent messages -->
<h4 style="margin:24px 0 8px;">Last 20 Messages</h4>
<div style="overflow-x:auto;">
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Date</th>
  <th style="padding:4px 8px;text-align:left;">Sender</th>
  <th style="padding:4px 8px;text-align:left;">Part</th>
  <th style="padding:4px 8px;text-align:left;">Message</th>
  <th style="padding:4px 8px;text-align:left;">Read</th>
</tr>
<?php $i=1; while ($row = $recent_msgs->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:3px 8px;white-space:nowrap;color:#7a9ab0;"><?= htmlspecialchars(date('d-m-Y', strtotime($row['created_at']))) ?></td>
  <td style="padding:3px 8px;">
    <a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['name'] ?: $row['email']) ?></a>
  </td>
  <td style="padding:3px 8px;white-space:nowrap;">
    <a href="index.php?navigate=viewpart&id=<?= (int)$row['part_id'] ?>"><?= htmlspecialchars($row['part_title']) ?></a>
  </td>
  <td style="padding:3px 8px;color:#777;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
      title="<?= htmlspecialchars($row['message']) ?>">
    <?= htmlspecialchars($row['message']) ?>
  </td>
  <td style="padding:3px 8px;">
    <?= $row['read_at'] ? '<span style="color:#3a8;font-size:11px;">Yes</span>' : '<span style="color:#c87020;font-size:11px;font-weight:bold;">No</span>' ?>
  </td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

</div><!-- /content-box -->
