
<?php
// includesearch.php — Main car detail page (new design)
// $pagina comes from index.php and contains the license plate from URL (dash-detection route)

include 'connection.php';
include_once 'car_stats_helper.php';
include_once 'settings_helper.php';
include_once 'comment_helper.php';

if (empty($SNLDBConnection) || $SNLDBConnection->connect_error) {
    echo "<div class='content-box'><h3>Fout</h3><p>Database verbinding mislukt."
       . (isset($SNLDBConnection) ? ' ' . htmlspecialchars($SNLDBConnection->connect_error) : '') . "</p></div>";
    return;
}

$kenteken = trim($pagina ?? '');
if ($kenteken === '') {
    echo "<div class='content-box'><h3>Geen kenteken</h3><p>Geen kenteken opgegeven.</p></div>";
    return;
}

// If input has no dashes and is pure alphanumeric, strip dashes from the DB column too
$is_dashless = (strpos($kenteken, '-') === false && preg_match('/^[A-Z0-9]+$/i', $kenteken));
if ($is_dashless) {
    $stmt = $SNLDBConnection->prepare(
        "SELECT * FROM SNLDB WHERE REPLACE(REPLACE(License, '-', ''), ' ', '') LIKE ?"
    );
} else {
    $stmt = $SNLDBConnection->prepare("SELECT * FROM SNLDB WHERE License LIKE ?");
}
if (!$stmt) {
    error_log("includesearch prepare failed: " . $SNLDBConnection->error);
    echo "<div class='content-box'><h3>Fout</h3><p>Database fout opgetreden.</p></div>";
    return;
}
$search_param = '%' . $kenteken . '%';
$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    echo "<div class='content-box'><h3>Fout</h3><p>Query mislukt: " . htmlspecialchars($SNLDBConnection->error) . "</p></div>";
    $stmt->close();
    mysqli_close($SNLDBConnection);
    return;
}

$SNLDBConnection->query("UPDATE `16915snldb`.`HITS` SET `searches` = searches + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");

if ($result->num_rows > 0) {
    car_stats_log($SNLDBConnection, strtoupper(trim($kenteken)));
}

if ($result->num_rows === 0) {
    $kd = htmlspecialchars(strtoupper($kenteken));
    echo "<div class='content-box'>"
       . "<h3>Helaas...</h3>"
       . "<img src='images/tumb1.jpg' style='float:left; margin-left:0px;' alt='img' /><br><br>"
       . "<center><strong>$kd</strong> staat nog niet in onze database.</center>"
       . "<br><center>Is dit een Supra? Voeg 'm toe — het duurt maar 2 minuten:<br><br>"
       . "<button onclick=\"location.href='index.php?navigate=contribute&amp;kenteken=" . urlencode($kenteken) . "'\" class='btn' style='margin:6px 0;padding:8px 18px;font-size:13px;'>➕ Voeg de supra toe</button>"
       . "</center></div>";
    $stmt->close();
    mysqli_close($SNLDBConnection);
    return;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

$mk_silhouette = [
    'MA-46 (MKI)'   => 'mk1',
    'MA-60 (MKII)'  => 'mk2',
    'MA-70 (MKIII)' => 'mk3',
    'JZA70'         => 'mk3',
    'JA-80 (MKIV)'  => 'mk4',
    'A-90 (MKV)'    => 'mk5',
];
$mk_generation = [
    'MA-46 (MKI)'   => 'MK I',
    'MA-60 (MKII)'  => 'MK II',
    'MA-70 (MKIII)' => 'MK III',
    'JZA70'         => 'MK III',
    'JA-80 (MKIV)'  => 'MK IV',
    'A-90 (MKV)'    => 'MK V',
];

function snl_owner_visible(array $row): bool {
    if (!empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1) return true;
    if (!empty($_SESSION['user_license']) && strtoupper($_SESSION['user_license']) === strtoupper($row['License'])) return true;
    return !empty($row['Owner_show']);
}

function snl_apk_from_history(string $history): ?array {
    if (!preg_match('/Vervaldatum APK[^:]*:\s*(\d{2}-\d{2}-\d{4})/i', $history, $m)) {
        return null;
    }
    [$d, $mo, $y] = explode('-', $m[1]);
    $ts      = mktime(0, 0, 0, (int)$mo, (int)$d, (int)$y);
    $expired = $ts < time();
    $soon    = !$expired && $ts < time() + 60 * 86400;
    return ['date' => $m[1], 'expired' => $expired, 'soon' => $soon];
}

function snl_status_color(string $s): string {
    $map = [
        'Running'         => '#2a8a2a',
        'Forsale'         => '#1a60b0',
        'Garage'          => '#a07010',
        'Wrecked'         => '#c04040',
        'Not Available'   => '#c04040',
        'No Road License' => '#666',
    ];
    return $map[$s] ?? '#555';
}
function snl_status_label(string $s): string {
    $map = [
        'Running'         => 'Rijdend ✓',
        'Forsale'         => 'Te koop',
        'Garage'          => 'In garage',
        'Wrecked'         => 'Wrecked',
        'Not Available'   => 'Niet beschikbaar',
        'No Road License' => 'Geen kenteken',
    ];
    return $map[$s] ?? $s;
}

// ─── History timeline renderer ───────────────────────────────────────────────
function snl_render_history_timeline(string $history): string {
    if (trim($history) === '') return '';

    // Split on the 44-dash divider that rdwu_prepend_block() emits
    $blocks = preg_split('/\n-{40,}\n?/', $history);
    $html   = '<div class="snl-timeline">';

    foreach (array_reverse($blocks) as $block) {
        $block = trim($block);
        if ($block === '') continue;

        if (preg_match('/^=== RDW Update gedetecteerd:\s*(.+?)\s*===/i', $block, $m)) {
            $date    = htmlspecialchars($m[1]);
            $body    = trim(preg_replace('/^=== RDW Update gedetecteerd:.+?===\s*/i', '', $block));
            $lines   = array_filter(array_map('trim', explode("\n", $body)));
            $html   .= '<div class="snl-tl-rdw">'
                     . '<div class="snl-tl-rdw-hdr">🔄 RDW Update — ' . $date . '</div>'
                     . '<ul class="snl-tl-rdw-list">';
            foreach ($lines as $line) {
                $html .= '<li>' . htmlspecialchars($line) . '</li>';
            }
            $html .= '</ul></div>';
        } else {
            $html .= '<div class="snl-tl-text">'
                   . nl2br(htmlspecialchars($block))
                   . '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}

// ─── RDW check JS ────────────────────────────────────────────────────────────
?>
<script>
function snlRDWCheck(k) {
    var kClean = k.replace(/[\s\-]/g, '').toUpperCase();
    var box  = document.getElementById('rdw-box-'  + k);
    var tbl  = document.getElementById('rdw-ta-'   + k);
    var apkEl = document.getElementById('apk-live-' + k);
    tbl.innerHTML = '<em>RDW gegevens ophalen…</em>';
    box.style.display = 'block';
    var base = 'https://opendata.rdw.nl/resource/';
    var fmtD = function(d) {
        if (!d) return '-';
        var s = d.replace(/\D/g,'');
        return s.length===8 ? s.slice(6)+'-'+s.slice(4,6)+'-'+s.slice(0,4) : d;
    };
    Promise.all([
        fetch(base+'m9d7-ebf2.json?kenteken='+kClean+'&$limit=1').then(r=>r.json()),
        fetch(base+'8ys7-d773.json?kenteken='+kClean+'&$limit=3').then(r=>r.json())
    ]).then(function([res0, fuels]) {
        if (!res0 || !res0.length) { tbl.innerHTML='<em>Geen RDW data gevonden.</em>'; return; }
        var v = res0[0];
        if (apkEl && v.vervaldatum_apk) {
            var apkRaw = v.vervaldatum_apk.replace(/\D/g,'');
            var apkFmt = apkRaw.length===8 ? apkRaw.slice(6)+'-'+apkRaw.slice(4,6)+'-'+apkRaw.slice(0,4) : v.vervaldatum_apk;
            var exp = new Date(apkRaw.slice(0,4)+'-'+apkRaw.slice(4,6)+'-'+apkRaw.slice(6,8)).getTime();
            var now = Date.now();
            var clr = exp < now ? '#c04040' : (exp < now + 60*86400000 ? '#a07010' : '#2a8a2a');
            var lbl = exp < now ? 'VERLOPEN' : (exp < now + 60*86400000 ? 'Verloopt binnenkort' : 'Geldig');
            apkEl.innerHTML = '<span style="color:'+clr+';font-weight:bold;">APK: '+apkFmt+'</span>'
                            + ' <span style="background:'+clr+';color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;">'+lbl+'</span>';
        }
        var rows = [
            ['Merk', v.merk], ['Handelsbenaming', v.handelsbenaming],
            ['Voertuigsoort', v.voertuigsoort], ['Eerste kleur', v.eerste_kleur],
            ['Tweede kleur', v.tweede_kleur],
            ['Eerste toelating', fmtD(v.datum_eerste_toelating)],
            ['Eerste tenaamstelling', fmtD(v.datum_eerste_tenaamstelling_in_nederland)],
            ['Tenaamstelling', fmtD(v.datum_tenaamstelling)],
            ['APK vervaldatum', fmtD(v.vervaldatum_apk)],
            ['WAM verzekerd', v.wam_verzekerd],
            ['Cilinders', v.aantal_cilinders], ['Cilinderinhoud (cc)', v.cilinderinhoud],
            ['Massa ledig (kg)', v.massa_ledig_voertuig],
            ['Catalogusprijs', v.catalogusprijs ? '€ '+parseInt(v.catalogusprijs).toLocaleString('nl-NL') : null]
        ];
        if (fuels) fuels.forEach(function(f,i){
            var lbl = fuels.length>1 ? 'Brandstof '+(i+1) : 'Brandstof';
            rows.push([lbl, f.brandstof_omschrijving||'-']);
            if (f.emissiecode_omschrijving) rows.push(['Emissienorm', f.emissiecode_omschrijving]);
            if (f.nettomaximumvermogen)     rows.push(['Max. vermogen (kW)', f.nettomaximumvermogen]);
        });
        var html='<table style="border-collapse:collapse;font-size:11px;">';
        rows.forEach(r=>{
            html+='<tr><td style="padding:3px 12px 3px 0;font-weight:bold;white-space:nowrap;color:#3B495A;">'+r[0]+'</td>'
                +'<td style="padding:3px 0;color:#222;">'+(r[1]||'-')+'</td></tr>';
        });
        html+='</table>';
        tbl.innerHTML=html;
    }).catch(e=>{ tbl.innerHTML='<em>Fout: '+e.message+'</em>'; });
}
</script>
<?php

// ─── Render each matching car ────────────────────────────────────────────────
while ($row = $result->fetch_assoc()):
    $License      = $row['License'];
    $Owner        = $row['Owner_display'];
    $Model        = $row['Choise_Model'];
    $Engine       = $row['Choise_Engine'];
    $Trans        = $row['Choise_Transmission'];
    $MA           = $row['MA'] ?? '';
    $BuildDate    = $row['Build_date'];
    $RegDate      = $row['Registration_date'];
    $Milage       = $row['Milage'];
    $Status       = $row['Choise_Status'];
    $Color        = $row['VIN_Colorcode'];
    $History      = $row['History'];
    $Mods         = $row['Mods'];
    $ModDate      = $row['moddate'] ?? '';

    $SNLDBConnection->query("UPDATE `16915snldb`.`HITS` SET `searchhits` = searchhits + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");

    $stripLicense = strtoupper(preg_replace('/\s*/m', '', $License));
    $sl           = htmlspecialchars($stripLicense, ENT_QUOTES);

    $silKey    = $mk_silhouette[$Model]  ?? 'mk4';
    $silSrc    = "images/silhouettes/{$silKey}.png";
    $genLabel  = $mk_generation[$Model]  ?? 'Supra';
    $statusClr = snl_status_color($Status);
    $statusLbl = snl_status_label($Status);
    $apk       = snl_apk_from_history($History);
    $apkColor  = $apk === null ? '#777'
               : ($apk['expired'] ? '#c04040' : ($apk['soon'] ? '#a07010' : '#2a8a2a'));
    $apkLabel  = $apk === null ? 'Onbekend'
               : ($apk['expired'] ? 'VERLOPEN' : ($apk['soon'] ? 'Verloopt binnenkort' : 'Geldig'));

?>
<!-- ═══ Main card ═══════════════════════════════════════════════════════════ -->
<div class="content-box" style="padding:0;overflow:hidden;">

  <!-- ── Top strip: two-column layout ─────────────────────────────────────── -->
  <div style="display:flex;flex-wrap:wrap;background:var(--color-surface);color:var(--color-accent);">

    <!-- LEFT: stats panel -->
    <div style="min-width:220px;max-width:280px;flex-shrink:0;padding:20px 22px;
                border-right:1px solid rgba(59,73,90,0.18);">

      <!-- License plate -->
      <div style="margin-bottom:16px;">
        <div style="display:inline-flex;align-items:stretch;border:2px solid #222;
                    border-radius:5px;overflow:hidden;font-family:'Arial Black',Arial,sans-serif;
                    font-weight:900;font-size:22px;letter-spacing:2px;line-height:1;box-shadow:2px 2px 4px rgba(0,0,0,0.2);">
          <div style="background:#003399;color:#fff;font-size:7px;font-weight:bold;
                      padding:3px 4px;display:flex;flex-direction:column;align-items:center;
                      justify-content:space-between;min-width:18px;letter-spacing:0;">
            <span style="font-size:9px;">★</span>
            <span style="font-size:8px;letter-spacing:0;">NL</span>
          </div>
          <div style="background:#F5C518;color:#222;padding:6px 12px;letter-spacing:3px;">
            <?= htmlspecialchars($License) ?>
          </div>
        </div>
      </div>

      <!-- Owner -->
      <div style="font-size:13px;font-weight:bold;color:#2c4255;margin-bottom:14px;">
        Eigenaar:
        <?php if (snl_owner_visible($row) && $Owner !== ''): ?>
          <?= htmlspecialchars($Owner) ?>
        <?php else: ?>
          <em style="color:#888;font-weight:normal;font-size:12px;">Verborgen of niet opgegeven.</em>
        <?php endif; ?>
      </div>

      <!-- Divider -->
      <div style="border-top:1px solid rgba(59,73,90,0.2);margin-bottom:12px;"></div>

      <!-- Spec rows -->
      <?php
      $specs = [
          ['Model',      $Model],
          ['Motor',      $Engine],
          ['Bak',        $Trans],
          ['M / A',      $MA === 'A' ? 'Automaat' : ($MA === 'M' ? 'Handgeschakeld' : $MA)],
          ['Kleur',      $Color],
          ['Bouwjaar',   $BuildDate],
          ['Reg.datum',  $RegDate],
          ['Kilometers', $Milage !== '' ? $Milage : null],
      ];
      foreach ($specs as [$lbl, $val]):
          if ($val === null || $val === '') continue;
      ?>
      <div style="display:flex;gap:6px;margin-bottom:5px;font-size:11px;">
        <span style="color:#5a7a90;min-width:72px;flex-shrink:0;"><?= htmlspecialchars($lbl) ?></span>
        <span style="color:#1a2e3d;font-weight:500;"><?= htmlspecialchars($val) ?></span>
      </div>
      <?php endforeach; ?>

      <!-- Divider -->
      <div style="border-top:1px solid rgba(59,73,90,0.2);margin:12px 0;"></div>

      <!-- Status badge -->
      <div style="margin-bottom:8px;">
        <div style="font-size:10px;color:#5a7a90;margin-bottom:3px;text-transform:uppercase;letter-spacing:1px;">Status</div>
        <div style="display:inline-block;background:<?= $statusClr ?>;color:#fff;
                    font-size:11px;font-weight:bold;border-radius:4px;padding:3px 10px;">
          <?= htmlspecialchars($statusLbl) ?>
        </div>
      </div>

      <!-- APK -->
      <div id="apk-live-<?= $sl ?>">
        <div style="font-size:10px;color:#5a7a90;margin-bottom:3px;text-transform:uppercase;letter-spacing:1px;">APK</div>
        <?php if ($apk): ?>
        <span style="color:<?= $apkColor ?>;font-weight:bold;font-size:12px;">
          <?= htmlspecialchars($apk['date']) ?>
        </span>
        <span style="background:<?= $apkColor ?>;color:#fff;border-radius:3px;
                     padding:1px 6px;font-size:10px;margin-left:4px;">
          <?= htmlspecialchars($apkLabel) ?>
        </span>
        <?php else: ?>
        <button onclick="snlRDWCheck('<?= $sl ?>')"
                style="font-size:10px;padding:2px 8px;cursor:pointer;background:#3B495A;
                       color:#fff;border:none;border-radius:3px;">
          Check RDW
        </button>
        <?php endif; ?>
      </div>

      <?php if ($ModDate): ?>
      <div style="margin-top:14px;font-size:9px;color:#8aabbf;">
        Bijgewerkt: <?= htmlspecialchars(substr($ModDate, 0, 10)) ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: silhouette + mods + history -->
    <div style="flex:1;min-width:220px;padding:20px 24px;display:flex;flex-direction:column;gap:18px;">

      <!-- Model label + inline silhouette -->
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
        <div style="font-size:11px;color:#5a7a90;font-weight:bold;
                    text-transform:uppercase;letter-spacing:1px;white-space:nowrap;">
          <?= htmlspecialchars($genLabel) ?> — <?= htmlspecialchars($Model) ?>
        </div>
        <img src="<?= htmlspecialchars($silSrc) ?>"
             alt="<?= htmlspecialchars($genLabel) ?> silhouette"
             style="height:36px;width:auto;object-fit:contain;
                    filter:invert(1);mix-blend-mode:multiply;opacity:0.6;flex-shrink:0;" />
      </div>

      <!-- Mods -->
      <?php if (trim($Mods) !== '' && strtolower(trim($Mods)) !== 'no known modifications.'): ?>
      <div>
        <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                    letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;
                    border-bottom:2px solid rgba(59,73,90,0.2);">
          ⚙ Gedane modificaties
        </div>
        <div style="background:var(--color-input-bg);border-left:3px solid var(--color-accent);
                    border-radius:0 4px 4px 0;padding:10px 14px;
                    font-size:12px;color:var(--color-text);line-height:1.6;
                    white-space:pre-wrap;font-family:inherit;max-height:260px;overflow-y:auto;">
<?= htmlspecialchars($Mods) ?>
        </div>
      </div>
      <?php else: ?>
      <div style="color:#8aabbf;font-size:12px;font-style:italic;text-align:center;padding-top:8px;">
        Geen bekende modificaties geregistreerd.
      </div>
      <?php endif; ?>

      <!-- Historie timeline -->
      <?php if (trim($History) !== ''): ?>
      <div>
        <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                    letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;
                    border-bottom:2px solid rgba(59,73,90,0.2);">
          📋 Historie
        </div>
        <style>
        .snl-timeline { display:flex;flex-direction:column;gap:8px; }
        .snl-tl-rdw {
            background:var(--color-input-bg);
            border-left:3px solid var(--color-accent);
            border-radius:0 4px 4px 0;
            padding:8px 12px;font-size:11px;
        }
        .snl-tl-rdw-hdr {
            font-weight:bold;color:var(--color-accent);
            margin-bottom:5px;font-size:11px;
        }
        .snl-tl-rdw-list {
            margin:0;padding-left:16px;
            color:var(--color-text);line-height:1.7;
        }
        .snl-tl-text {
            background:var(--color-surface);
            border:1px solid var(--color-content-border);
            border-radius:4px;
            padding:8px 12px;
            font-size:12px;color:var(--color-text);
            line-height:1.6;
        }
        </style>
        <div style="max-height:280px;overflow-y:auto;padding-right:2px;">
          <?= snl_render_history_timeline($History) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div><!-- end two-column top -->

  <!-- ── Action bar ────────────────────────────────────────────────────────── -->
  <div style="background:var(--color-accent-dark);padding:10px 20px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <button onclick="location.href='index.php?navigate=contribute&amp;kenteken=<?= $sl ?>&amp;mode=upload'"
            class="btn" style="font-size:12px;padding:5px 14px;">
      📷 Upload foto
    </button>
    <a href="index.php?navigate=editsupra2" class="btn" style="font-size:12px;padding:5px 14px;">
      ✏️ Bewerk supra
    </a>
    <button onclick="snlRDWCheck('<?= $sl ?>')"
            class="btn" style="font-size:12px;padding:5px 14px;">
      🔍 Check RDW
    </button>
    <a href="index.php?navigate=navigateclassic&amp;k=<?= urlencode($License) ?>"
       style="font-size:11px;color:var(--color-surface);opacity:0.8;margin-left:6px;">
      Klassieke weergave
    </a>
    <span style="margin-left:auto;font-size:11px;color:var(--color-surface);opacity:0.7;">Deel: </span>
    <?php $pageUrl = 'https://www.supraclub.nl/carparts/index.php?navigate=' . rawurlencode($License); ?>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>"
       target="_blank" rel="noopener"
       style="font-size:11px;color:var(--color-surface);opacity:0.85;">Facebook</a>
    <a href="https://wa.me/?text=<?= urlencode('Check deze Supra: ' . $pageUrl) ?>"
       target="_blank" rel="noopener"
       style="font-size:11px;color:var(--color-surface);opacity:0.85;">WhatsApp</a>
  </div>

  <!-- ── RDW live data (hidden by default) ─────────────────────────────────── -->
  <div id="rdw-box-<?= $sl ?>"
       style="display:none;padding:14px 22px;background:var(--color-input-bg);border-top:1px solid var(--color-content-border);">
    <div style="font-size:11px;font-weight:bold;color:#3B495A;margin-bottom:6px;">
      RDW Gegevens (live)
    </div>
    <div id="rdw-ta-<?= $sl ?>"></div>
  </div>

  <!-- ── Gallery ───────────────────────────────────────────────────────────── -->
  <div style="padding:16px 20px;border-top:1px solid var(--color-content-border);">
    <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                letter-spacing:1px;margin-bottom:10px;">📸 Foto's</div>
    <?php
    include("./bolgallery.php");
    $switchClassic = true;
    bolGallery("./cars/$stripLicense/slides/", 5, 80, 50, $switchClassic);
    ?>
  </div>

  <?php if (isset($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1):
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
  ?>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      var lb    = document.getElementById('snl-lightbox');
      var lbImg = document.getElementById('snl-lb-img');
      if (!lb || !lbImg) return;

      // Inject delete button into lightbox
      var btn = document.createElement('button');
      btn.id = 'snl-lb-delete';
      btn.textContent = '🗑️ Verwijder foto';
      btn.style.cssText = 'display:none;position:fixed;bottom:24px;right:24px;z-index:10001;' +
          'background:#c04040;color:#fff;border:none;padding:10px 20px;border-radius:5px;' +
          'cursor:pointer;font-size:13px;font-weight:bold;box-shadow:0 2px 8px rgba(0,0,0,0.5);';
      lb.appendChild(btn);

      btn.addEventListener('click', function () {
          var fn = lbImg.src.split('/').pop().split('?')[0];
          if (!confirm('Foto verwijderen?\n' + fn)) return;
          var f = document.createElement('form');
          f.method = 'post';
          f.action = 'index.php?navigate=deletephoto';
          f.innerHTML =
              '<input type="hidden" name="license"    value="<?= htmlspecialchars($License, ENT_QUOTES) ?>">' +
              '<input type="hidden" name="filename"   value="' + fn + '">' +
              '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">';
          document.body.appendChild(f);
          f.submit();
      });

      // Show button when lightbox is open, hide when closed
      new MutationObserver(function () {
          btn.style.display = lb.classList.contains('active') ? 'block' : 'none';
      }).observe(lb, { attributes: true, attributeFilter: ['class'] });
  });
  </script>
  <?php endif; ?>

  <!-- ── Comments ──────────────────────────────────────────────────────────── -->
  <?php
  $comments_on = settings_get($SNLDBConnection, 'comments_enabled', '1') === '1';
  if ($comments_on):
      $car_comments = comment_list($SNLDBConnection, $License);
      $comment_msg  = '';

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snl_comment_submit'])
          && $_POST['snl_comment_lic'] === $License) {
          // Honeypot check
          if (!empty($_POST['snl_url'])) {
              $comment_msg = ''; // Bot — silently discard
          } else {
              $c_author  = trim($_POST['snl_author']  ?? '');
              $c_text    = trim($_POST['snl_comment']  ?? '');
              $c_ip      = $_SERVER['REMOTE_ADDR'] ?? '';
              if ($c_text === '') {
                  $comment_msg = '<div style="color:orange;padding:6px 0;">Vul een reactie in.</div>';
              } elseif (strlen($c_text) > 1000) {
                  $comment_msg = '<div style="color:orange;padding:6px 0;">Reactie is te lang (max 1000 tekens).</div>';
              } else {
                  $result = comment_add($SNLDBConnection, $License, $c_author, $c_text, $c_ip);
                  if ($result === true) {
                      $comment_msg  = '<div style="color:green;padding:6px 0;">✓ Reactie geplaatst, bedankt!</div>';
                      $car_comments = comment_list($SNLDBConnection, $License);
                  } else {
                      $comment_msg = '<div style="color:red;padding:6px 0;">' . htmlspecialchars($result) . '</div>';
                  }
              }
          }
      }
  ?>
  <div style="padding:16px 20px;border-top:1px solid var(--color-content-border);">
      <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                  letter-spacing:1px;margin-bottom:12px;">💬 Reacties</div>

      <?= $comment_msg ?>

      <?php if (empty($car_comments)): ?>
      <div style="color:#8aabbf;font-size:12px;font-style:italic;margin-bottom:14px;">
          Nog geen reacties. Wees de eerste!
      </div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
          <?php foreach ($car_comments as $c): ?>
          <div style="background:var(--color-input-bg);border-radius:6px;
                      border:1px solid var(--color-content-border);padding:10px 14px;">
              <div style="font-size:11px;color:#5a7a90;margin-bottom:4px;">
                  <strong style="color:var(--color-text);">
                      <?= htmlspecialchars($c['author'] ?: 'Anoniem') ?>
                  </strong>
                  &nbsp;&mdash;&nbsp;<?= htmlspecialchars(substr($c['created_at'], 0, 16)) ?>
              </div>
              <div style="font-size:12px;color:var(--color-text);line-height:1.6;">
                  <?= comment_render($c['comment'], settings_get($SNLDBConnection, 'comments_video_enabled', '1') === '1') ?>
              </div>
          </div>
          <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Post form -->
      <form method="post" style="margin-top:4px;">
          <input type="hidden" name="snl_comment_submit" value="1" />
          <input type="hidden" name="snl_comment_lic" value="<?= htmlspecialchars($License) ?>" />
          <!-- Honeypot -->
          <div style="display:none;"><input type="text" name="snl_url" tabindex="-1" autocomplete="off" /></div>
          <div style="display:flex;flex-direction:column;gap:8px;max-width:520px;">
              <input type="text" name="snl_author"
                     placeholder="Naam (optioneel)" maxlength="80"
                     style="padding:6px 10px;font-size:12px;border:1px solid var(--color-content-border);
                            border-radius:4px;background:var(--color-input-bg);color:var(--color-text);" />
              <textarea name="snl_comment" rows="3" maxlength="1000"
                        placeholder="Schrijf een reactie…"
                        style="padding:6px 10px;font-size:12px;border:1px solid var(--color-content-border);
                               border-radius:4px;background:var(--color-input-bg);color:var(--color-text);
                               resize:vertical;font-family:inherit;"></textarea>
              <div>
                  <input type="submit" value="Reactie plaatsen" class="btn" style="padding:6px 16px;" />
                  <span style="font-size:10px;color:#8aabbf;margin-left:8px;">Max 3 reacties per 24 uur per IP.</span>
              </div>
          </div>
      </form>
  </div>
  <?php endif; ?>

</div><!-- end main card -->

<?php
endwhile;

$stmt->close();
mysqli_close($SNLDBConnection);
?>
