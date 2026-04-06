<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Geen toegang.</div>";
    return;
}

include 'connection.php';
include_once 'car_stats_helper.php';
car_stats_init($CarpartsConnection);
car_stats_cleanup($CarpartsConnection);

$now   = time();
$day30 = $now - 30 * 86400;
$day7  = $now - 7  * 86400;
$day1  = $now - 86400;

// Top 10 all-time views
$top_all = $CarpartsConnection->query(
    "SELECT license, COUNT(*) AS cnt FROM CAR_VIEWS WHERE event_type='view'
     GROUP BY license ORDER BY cnt DESC LIMIT 10"
);

// Top 10 last 30 days
$top_30 = $CarpartsConnection->query(
    "SELECT license, COUNT(*) AS cnt FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day30
     GROUP BY license ORDER BY cnt DESC LIMIT 10"
);

// Top 10 IPs last 24h
$top_ip_day = $CarpartsConnection->query(
    "SELECT ip, COUNT(*) AS cnt,
            GROUP_CONCAT(DISTINCT license ORDER BY license SEPARATOR ', ') AS cars
     FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day1
     GROUP BY ip ORDER BY cnt DESC LIMIT 10"
);

// Top 10 IPs last 30 days
$top_ip_30 = $CarpartsConnection->query(
    "SELECT ip, COUNT(*) AS cnt,
            GROUP_CONCAT(DISTINCT license ORDER BY license SEPARATOR ', ') AS cars
     FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day30
     GROUP BY ip ORDER BY cnt DESC LIMIT 10"
);

// Recent views (last 100)
$recent_views = $CarpartsConnection->query(
    "SELECT license, ip, user_agent, view_time FROM CAR_VIEWS
     WHERE event_type='view' ORDER BY view_time DESC LIMIT 100"
);

// Recent edits (last 50) — from SNLDB_CHANGELOG joined with SNLDB
$recent_edits = $CarpartsConnection->query(
    "SELECT c.license, c.change_type, c.changed_at,
            s.Choise_Model, s.Choise_Status, s.Owner_display
     FROM SNLDB_CHANGELOG c
     LEFT JOIN SNLDB s ON s.License = c.license
     ORDER BY c.changed_at DESC LIMIT 50"
);

// Totals
$r = $CarpartsConnection->query("SELECT COUNT(*) FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day1");
$views_today = $r ? $r->fetch_row()[0] : 0;

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day7");
$views_week = $r ? $r->fetch_row()[0] : 0;

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM CAR_VIEWS WHERE event_type='view' AND view_time > $day30");
$views_month = $r ? $r->fetch_row()[0] : 0;

$r = $CarpartsConnection->query("SELECT COUNT(*) FROM CAR_VIEWS WHERE event_type='view'");
$views_total = $r ? $r->fetch_row()[0] : 0;

mysqli_close($CarpartsConnection);

function fmt_time($t) {
    return date('d-m-Y H:i', $t);
}
function ua_short($ua) {
    if (stripos($ua, 'Googlebot') !== false)  return '<span style="color:#c80;font-size:10px;">Googlebot</span>';
    if (stripos($ua, 'bot')       !== false)  return '<span style="color:#c80;font-size:10px;">Bot</span>';
    if (stripos($ua, 'crawl')     !== false)  return '<span style="color:#c80;font-size:10px;">Crawler</span>';
    if (stripos($ua, 'Mobile')    !== false)  return '<span style="color:#286;font-size:10px;">Mobile</span>';
    return '<span style="color:#555;font-size:10px;">Desktop</span>';
}
?>

<div class="content-box">
<h3>Supra bekijkstatistieken</h3>

<!-- Totals -->
<div style="display:flex;gap:20px;flex-wrap:wrap;margin:10px 0 20px;">
<?php foreach ([
    ['Vandaag',    $views_today],
    ['7 dagen',    $views_week],
    ['30 dagen',   $views_month],
    ['Totaal',     $views_total],
] as [$label, $val]): ?>
<div style="background:var(--color-input-bg);border:1px solid var(--color-nav-border);border-radius:6px;padding:14px 22px;text-align:center;min-width:90px;">
  <div style="font-size:26px;font-weight:bold;color:var(--color-accent);"><?= number_format($val) ?></div>
  <div style="font-size:11px;color:var(--color-text);"><?= $label ?></div>
</div>
<?php endforeach; ?>
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">

<!-- Top 10 all-time -->
<div style="min-width:220px;">
<h4 style="margin-bottom:6px;">Top 10 — alle tijd</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);"><th style="padding:4px 8px;text-align:left;">Kenteken</th><th style="padding:4px 8px;text-align:right;">Bekeken</th></tr>
<?php $i=1; while ($row = $top_all->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:4px 8px;"><a href="index.php?navigate=<?= urlencode($row['license']) ?>"><?= htmlspecialchars($row['license']) ?></a></td>
  <td style="padding:4px 8px;text-align:right;font-weight:bold;"><?= $row['cnt'] ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

<!-- Top 10 last 30 days -->
<div style="min-width:220px;">
<h4 style="margin-bottom:6px;">Top 10 — afgelopen 30 dagen</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);"><th style="padding:4px 8px;text-align:left;">Kenteken</th><th style="padding:4px 8px;text-align:right;">Bekeken</th></tr>
<?php $i=1; while ($row = $top_30->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:4px 8px;"><a href="index.php?navigate=<?= urlencode($row['license']) ?>"><?= htmlspecialchars($row['license']) ?></a></td>
  <td style="padding:4px 8px;text-align:right;font-weight:bold;"><?= $row['cnt'] ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

</div><!-- /flex -->

<!-- Top IPs -->
<?php
function render_ip_table($result) {
    $i = 1;
    while ($row = $result->fetch_assoc()):
        $plates = array_filter(array_map('trim', explode(',', $row['cars'])));
        $bg = $i % 2 ? 'var(--color-input-bg)' : 'var(--color-surface)';
?>
<tr style="background:<?= $bg ?>;">
  <td style="padding:3px 8px;font-family:monospace;"><?= htmlspecialchars($row['ip']) ?></td>
  <td style="padding:3px 8px;text-align:right;font-weight:bold;"><?= $row['cnt'] ?></td>
  <td style="padding:3px 8px;">
    <details>
      <summary style="cursor:pointer;font-size:11px;color:var(--color-accent);"><?= count($plates) ?> kenteken<?= count($plates) !== 1 ? 's' : '' ?></summary>
      <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
        <?php foreach ($plates as $plate): ?>
        <a href="index.php?navigate=<?= urlencode($plate) ?>" style="font-size:11px;white-space:nowrap;"><?= htmlspecialchars($plate) ?></a>
        <?php endforeach; ?>
      </div>
    </details>
  </td>
</tr>
<?php $i++; endwhile; }
?>

<h4 style="margin:24px 0 6px;">Top 10 IP-adressen — vandaag</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">IP</th>
  <th style="padding:4px 8px;text-align:right;">Bekeken</th>
  <th style="padding:4px 8px;text-align:left;">Kentekens</th>
</tr>
<?php render_ip_table($top_ip_day); ?>
</table>

<h4 style="margin:22px 0 6px;">Top 10 IP-adressen — afgelopen 30 dagen</h4>
<table style="border-collapse:collapse;font-size:13px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">IP</th>
  <th style="padding:4px 8px;text-align:right;">Bekeken</th>
  <th style="padding:4px 8px;text-align:left;">Kentekens</th>
</tr>
<?php render_ip_table($top_ip_30); ?>
</table>

<!-- Recent views -->
<h4 style="margin:22px 0 6px;">Laatste 100 bekeken supras</h4>
<div style="overflow-x:auto;">
<table style="border-collapse:collapse;font-size:12px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Tijd</th>
  <th style="padding:4px 8px;text-align:left;">Kenteken</th>
  <th style="padding:4px 8px;text-align:left;">IP</th>
  <th style="padding:4px 8px;text-align:left;">Apparaat</th>
  <th style="padding:4px 8px;text-align:left;">User-Agent</th>
</tr>
<?php $i=1; while ($row = $recent_views->fetch_assoc()): ?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:3px 8px;white-space:nowrap;color:#7a9ab0;"><?= fmt_time($row['view_time']) ?></td>
  <td style="padding:3px 8px;"><a href="index.php?navigate=<?= urlencode($row['license']) ?>"><?= htmlspecialchars($row['license']) ?></a></td>
  <td style="padding:3px 8px;font-family:monospace;"><?= htmlspecialchars($row['ip']) ?></td>
  <td style="padding:3px 8px;"><?= ua_short($row['user_agent']) ?></td>
  <td style="padding:3px 8px;color:#999;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['user_agent']) ?>"><?= htmlspecialchars($row['user_agent']) ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
</div>

<!-- Recent edits -->
<h4 style="margin:22px 0 6px;">Laatste 50 wijzigingen</h4>
<?php if (!$recent_edits || $recent_edits->num_rows === 0): ?>
<p style="color:#888;font-size:13px;">Nog geen wijzigingen geregistreerd. Wijzigingen worden vanaf nu bijgehouden.</p>
<?php else: ?>
<table style="border-collapse:collapse;font-size:12px;width:100%;">
<tr style="background:var(--color-nav-hover-bg);">
  <th style="padding:4px 8px;text-align:left;">Gewijzigd</th>
  <th style="padding:4px 8px;text-align:left;">Kenteken</th>
  <th style="padding:4px 8px;text-align:left;">Wat</th>
  <th style="padding:4px 8px;text-align:left;">Model</th>
  <th style="padding:4px 8px;text-align:left;">Status</th>
  <th style="padding:4px 8px;text-align:left;">Eigenaar</th>
</tr>
<?php $i=1; while ($row = $recent_edits->fetch_assoc()):
    [$label, $color] = match($row['change_type']) {
        'photo'    => ['📷 Foto',       '#286'],
        'info'     => ['✏️ Info',       '#448'],
        'new'      => ['🆕 Nieuw',      '#a60'],
        'photodel' => ['🗑️ Foto weg',  '#c04'],
        default    => [$row['change_type'], '#666'],
    };
?>
<tr style="background:<?= $i%2?'var(--color-input-bg)':'var(--color-surface)' ?>;">
  <td style="padding:3px 8px;white-space:nowrap;color:#7a9ab0;"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['changed_at']))) ?></td>
  <td style="padding:3px 8px;"><a href="index.php?navigate=<?= urlencode($row['license']) ?>"><?= htmlspecialchars($row['license']) ?></a></td>
  <td style="padding:3px 8px;font-weight:bold;color:<?= $color ?>;"><?= $label ?></td>
  <td style="padding:3px 8px;color:#666;"><?= htmlspecialchars($row['Choise_Model'] ?? '') ?></td>
  <td style="padding:3px 8px;color:#666;"><?= htmlspecialchars($row['Choise_Status'] ?? '') ?></td>
  <td style="padding:3px 8px;color:#666;"><?= htmlspecialchars($row['Owner_display'] ?? '') ?></td>
</tr>
<?php $i++; endwhile; ?>
</table>
<?php endif; ?>

</div><!-- /content-box -->
