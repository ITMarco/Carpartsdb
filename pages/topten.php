<?php
include 'connection.php';
include 'photo_recent_helper.php';
include_once 'car_stats_helper.php';

// Ensure the ring-buffer table exists (no-op if already created)
photo_recent_init($SNLDBConnection);

// Recent photos (newest first, skip empty slots)
$photos_q = $SNLDBConnection->query(
    "SELECT license, filename, uploaded_at FROM PHOTO_RECENT
     WHERE filename IS NOT NULL AND license IS NOT NULL
     ORDER BY uploaded_at DESC"
);

// Recently modified
$modified_q = $SNLDBConnection->query(
    "SELECT License, Owner_display, Choise_Model, Choise_Status, moddate
     FROM SNLDB WHERE moddate != '0000-00-00 00:00:00'
     ORDER BY moddate DESC LIMIT 10"
);

// Recently added
$added_q = $SNLDBConnection->query(
    "SELECT License, Owner_display, Choise_Model, Choise_Status
     FROM SNLDB ORDER BY RECNO DESC LIMIT 10"
);

// Top 10 most viewed (excluding bots — filters common bot user-agents)
$top_viewed_q = $SNLDBConnection->query(
    "SELECT license, COUNT(*) AS cnt FROM CAR_VIEWS
     WHERE event_type='view'
       AND user_agent NOT LIKE '%bot%'
       AND user_agent NOT LIKE '%crawl%'
       AND user_agent NOT LIKE '%spider%'
     GROUP BY license ORDER BY cnt DESC LIMIT 10"
);

mysqli_close($SNLDBConnection);

// Status badge helper
function status_badge($status) {
    $map = [
        'Running'          => ['#2a8a2a', 'Rijdend'],
        'Garage'           => ['#5588bb', 'Garage'],
        'Forsale'          => ['#c8a020', 'Te koop'],
        'Wrecked'          => ['#c04040', 'Wrak'],
        'Not Available'    => ['#c04040', 'N/A'],
        'No Road License'  => ['#c04040', 'Geen kenteken'],
    ];
    [$color, $label] = $map[$status] ?? ['#888', htmlspecialchars($status)];
    return "<span style='display:inline-block;padding:1px 6px;border-radius:3px;background:{$color};color:#fff;font-size:9px;font-weight:bold;white-space:nowrap;'>{$label}</span>";
}

// Model short label
function model_short($model) {
    $map = [
        'MA-46 (MKI)'   => 'MK I',
        'MA-60 (MKII)'  => 'MK II',
        'MA-70 (MKIII)' => 'MK III',
        'JZA70'         => 'MK III',
        'JA-80 (MKIV)'  => 'MK IV',
        'A-90 (MKV)'    => 'MK V',
    ];
    return $map[$model] ?? htmlspecialchars($model);
}
?>

<!-- ═══ Recent photos ════════════════════════════════════════════════════════ -->
<div class="content-box">
  <h3>Laatste foto's</h3>
<?php if ($photos_q && $photos_q->num_rows > 0): ?>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
  <?php while ($p = $photos_q->fetch_assoc()):
      $lic     = htmlspecialchars($p['license']);
      $img_url = 'cars/' . rawurlencode($p['license']) . '/slides/' . rawurlencode($p['filename']);
      $nav_url = 'index.php?navigate=' . urlencode($p['license']);
      $date    = $p['uploaded_at'] ? date('d-m-Y', strtotime($p['uploaded_at'])) : '';
  ?>
    <a href="<?= $nav_url ?>" style="display:block;text-decoration:none;width:150px;flex-shrink:0;">
      <div style="width:150px;height:100px;overflow:hidden;border-radius:5px;background:var(--color-nav-border);">
        <img src="<?= $img_url ?>" alt="<?= $lic ?>"
             style="width:150px;height:100px;object-fit:cover;display:block;transition:opacity .2s;"
             onerror="this.parentElement.style.background='#ddd';this.style.display='none';" />
      </div>
      <div style="font-size:11px;font-weight:bold;color:var(--color-accent);text-align:center;margin-top:4px;"><?= $lic ?></div>
      <?php if ($date): ?>
      <div style="font-size:9px;color:#7a9ab0;text-align:center;"><?= $date ?></div>
      <?php endif; ?>
    </a>
  <?php endwhile; ?>
  </div>
<?php else: ?>
  <p style="color:#888;font-size:13px;">Nog geen foto's geregistreerd. Foto's die worden geüpload verschijnen hier.</p>
<?php endif; ?>
</div>

<!-- ═══ Recently modified ════════════════════════════════════════════════════ -->
<div class="content-box">
  <h3>Laatst bijgewerkte supras</h3>
  <div style="display:flex;flex-direction:column;gap:0;">
  <?php
  $alt = false;
  while ($row = $modified_q->fetch_assoc()):
      $alt = !$alt;
      $date = $row['moddate'] ? date('d-m-Y H:i', strtotime($row['moddate'])) : '-';
  ?>
    <div style="display:flex;align-items:center;gap:10px;padding:7px 10px;background:<?= $alt ? 'var(--color-input-bg)' : 'var(--color-surface)' ?>;border-bottom:1px solid var(--color-nav-border);">
      <div style="width:80px;flex-shrink:0;">
        <a href="index.php?navigate=<?= urlencode($row['License']) ?>"
           style="font-size:14px;font-weight:bold;color:var(--color-accent);text-decoration:none;">
          <?= htmlspecialchars($row['License']) ?>
        </a>
      </div>
      <div style="width:44px;flex-shrink:0;font-size:11px;color:#4a7090;font-weight:bold;">
        <?= model_short($row['Choise_Model']) ?>
      </div>
      <div style="flex:1;font-size:12px;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= htmlspecialchars($row['Owner_display']) ?>
      </div>
      <div style="flex-shrink:0;"><?= status_badge($row['Choise_Status']) ?></div>
      <div style="width:110px;flex-shrink:0;font-size:10px;color:#7a9ab0;text-align:right;"><?= $date ?></div>
    </div>
  <?php endwhile; ?>
  </div>
</div>

<!-- ═══ Most viewed ══════════════════════════════════════════════════════════ -->
<div class="content-box">
  <h3>Meest bekeken supras</h3>
  <?php if ($top_viewed_q && $top_viewed_q->num_rows > 0): ?>
  <div style="display:flex;flex-direction:column;gap:0;">
  <?php
  $rank = 1;
  while ($row = $top_viewed_q->fetch_assoc()):
      $alt = ($rank % 2 === 0);
  ?>
    <div style="display:flex;align-items:center;gap:10px;padding:7px 10px;background:<?= $alt ? 'var(--color-input-bg)' : 'var(--color-surface)' ?>;border-bottom:1px solid var(--color-nav-border);">
      <div style="width:28px;flex-shrink:0;font-size:18px;font-weight:bold;color:var(--color-nav-border);text-align:center;"><?= $rank ?></div>
      <div style="width:90px;flex-shrink:0;">
        <a href="index.php?navigate=<?= urlencode($row['license']) ?>"
           style="font-size:14px;font-weight:bold;color:var(--color-accent);text-decoration:none;">
          <?= htmlspecialchars($row['license']) ?>
        </a>
      </div>
      <div style="flex:1;"></div>
      <div style="flex-shrink:0;font-size:12px;color:#7a9ab0;"><?= $row['cnt'] ?> keer bekeken</div>
    </div>
  <?php $rank++; endwhile; ?>
  </div>
  <?php else: ?>
  <p style="color:#888;font-size:13px;">Nog geen bekijkdata beschikbaar.</p>
  <?php endif; ?>
</div>

<!-- ═══ Recently added ═══════════════════════════════════════════════════════ -->
<div class="content-box">
  <h3>Laatst toegevoegde supras</h3>
  <div style="display:flex;flex-direction:column;gap:0;">
  <?php
  $alt = false;
  while ($row = $added_q->fetch_assoc()):
      $alt = !$alt;
  ?>
    <div style="display:flex;align-items:center;gap:10px;padding:7px 10px;background:<?= $alt ? 'var(--color-input-bg)' : 'var(--color-surface)' ?>;border-bottom:1px solid var(--color-nav-border);">
      <div style="width:80px;flex-shrink:0;">
        <a href="index.php?navigate=<?= urlencode($row['License']) ?>"
           style="font-size:14px;font-weight:bold;color:var(--color-accent);text-decoration:none;">
          <?= htmlspecialchars($row['License']) ?>
        </a>
      </div>
      <div style="width:44px;flex-shrink:0;font-size:11px;color:#4a7090;font-weight:bold;">
        <?= model_short($row['Choise_Model']) ?>
      </div>
      <div style="flex:1;font-size:12px;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= htmlspecialchars($row['Owner_display']) ?>
      </div>
      <div style="flex-shrink:0;"><?= status_badge($row['Choise_Status']) ?></div>
    </div>
  <?php endwhile; ?>
  </div>
</div>

