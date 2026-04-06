<?php
// suprabeta.php — Redesigned Supra detail page (beta)
// Access: index.php?navigate=suprabeta&k=XX-99-XX

include 'connection.php';

if (empty($SNLDBConnection) || $SNLDBConnection->connect_error) {
    echo "<div class='content-box'><h3>Fout</h3><p>Database verbinding mislukt."
       . (isset($SNLDBConnection) ? ' ' . htmlspecialchars($SNLDBConnection->connect_error) : '') . "</p></div>";
    return;
}

$kenteken = trim($_GET['k'] ?? '');
if ($kenteken === '') {
    echo "<div class='content-box'><h3>Supra detail (beta)</h3>"
       . "<p>Geen kenteken opgegeven. Gebruik <code>?navigate=suprabeta&amp;k=XX-99-XX</code></p></div>";
    return;
}

$stmt = $SNLDBConnection->prepare("SELECT * FROM SNLDB WHERE License LIKE ?");
if (!$stmt) {
    echo "<div class='content-box'><p>Database fout.</p></div>";
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

if ($result->num_rows === 0) {
    $kd = htmlspecialchars(strtoupper($kenteken));
    echo "<div class='content-box'><h3>Helaas…</h3>"
       . "<p><strong>$kd</strong> staat nog niet in onze database.</p>"
       . "<p><button onclick=\"location.href='index.php?navigate=contribute&amp;kenteken="
       . urlencode($kenteken) . "'\" class='btn'>➕ Voeg de supra toe</button></p></div>";
    $stmt->close();
    mysqli_close($SNLDBConnection);
    return;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

// Map Choise_Model → silhouette file key
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

function beta_apk_from_history(string $history): ?array {
    if (!preg_match('/Vervaldatum APK[^:]*:\s*(\d{2}-\d{2}-\d{4})/i', $history, $m)) {
        return null;
    }
    [$d, $mo, $y] = explode('-', $m[1]);
    $ts      = mktime(0, 0, 0, (int)$mo, (int)$d, (int)$y);
    $expired = $ts < time();
    $soon    = !$expired && $ts < time() + 60 * 86400;
    return ['date' => $m[1], 'expired' => $expired, 'soon' => $soon];
}

function beta_status_color(string $s): string {
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
function beta_status_label(string $s): string {
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

// ─── RDW check JS (reused from includesearch) ────────────────────────────────
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
        // Update APK badge in header
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
        // Build full RDW table
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

    $stripLicense = strtoupper(preg_replace('/\s*/m', '', $License));
    $sl           = htmlspecialchars($stripLicense, ENT_QUOTES);

    $silKey    = $mk_silhouette[$Model]  ?? 'mk4';
    $silSrc    = "images/silhouettes/{$silKey}.png";
    $genLabel  = $mk_generation[$Model]  ?? 'Supra';
    $statusClr = beta_status_color($Status);
    $statusLbl = beta_status_label($Status);
    $apk       = beta_apk_from_history($History);
    $apkColor  = $apk === null ? '#777'
               : ($apk['expired'] ? '#c04040' : ($apk['soon'] ? '#a07010' : '#2a8a2a'));
    $apkLabel  = $apk === null ? 'Onbekend'
               : ($apk['expired'] ? 'VERLOPEN' : ($apk['soon'] ? 'Verloopt binnenkort' : 'Geldig'));

?>
<!-- ═══ Main card ═══════════════════════════════════════════════════════════ -->
<div class="content-box" style="padding:0;overflow:hidden;">

  <!-- ── Top strip: two-column layout ─────────────────────────────────────── -->
  <div style="display:flex;flex-wrap:wrap;background:#e6eef5;color:#2c4255;">

    <!-- LEFT: stats panel -->
    <div style="min-width:220px;max-width:280px;flex-shrink:0;padding:20px 22px;
                border-right:1px solid rgba(59,73,90,0.18);">

      <!-- License plate -->
      <div style="margin-bottom:16px;">
        <div style="display:inline-flex;align-items:stretch;border:2px solid #222;
                    border-radius:5px;overflow:hidden;font-family:'Arial Black',Arial,sans-serif;
                    font-weight:900;font-size:22px;letter-spacing:2px;line-height:1;box-shadow:2px 2px 4px rgba(0,0,0,0.2);">
          <!-- EU blue strip -->
          <div style="background:#003399;color:#fff;font-size:7px;font-weight:bold;
                      padding:3px 4px;display:flex;flex-direction:column;align-items:center;
                      justify-content:space-between;min-width:18px;letter-spacing:0;">
            <span style="font-size:9px;">★</span>
            <span style="font-size:8px;letter-spacing:0;">NL</span>
          </div>
          <!-- Yellow plate body -->
          <div style="background:#F5C518;color:#222;padding:6px 12px;letter-spacing:3px;">
            <?= htmlspecialchars($License) ?>
          </div>
        </div>
      </div>

      <!-- Owner -->
      <div style="font-size:13px;font-weight:bold;color:#2c4255;margin-bottom:14px;">
        <?= htmlspecialchars($Owner) ?>
      </div>

      <!-- Divider -->
      <div style="border-top:1px solid rgba(59,73,90,0.2);margin-bottom:12px;"></div>

      <!-- Spec rows -->
      <?php
      $specs = [
          ['Model',   $Model],
          ['Motor',   $Engine],
          ['Bak',     $Trans],
          ['M / A',   $MA === 'A' ? 'Automaat' : ($MA === 'M' ? 'Handgeschakeld' : $MA)],
          ['Kleur',   $Color],
          ['Bouwjaar',$BuildDate],
          ['Reg.datum',$RegDate],
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

    <!-- RIGHT: silhouette + mods -->
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
        <div style="background:rgba(59,73,90,0.07);border-left:3px solid #3B495A;
                    border-radius:0 4px 4px 0;padding:10px 14px;
                    font-size:12px;color:#2c4255;line-height:1.6;
                    white-space:pre-wrap;font-family:inherit;max-height:260px;overflow-y:auto;">
<?= htmlspecialchars($Mods) ?>
        </div>
      </div>
      <?php else: ?>
      <div style="color:#8aabbf;font-size:12px;font-style:italic;text-align:center;padding-top:8px;">
        Geen bekende modificaties geregistreerd.
      </div>
      <?php endif; ?>

      <!-- Historie -->
      <?php if (trim($History) !== ''): ?>
      <div>
        <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                    letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;
                    border-bottom:2px solid rgba(59,73,90,0.2);">
          📋 Historie
        </div>
        <div style="background:rgba(59,73,90,0.07);border-left:3px solid #3B495A;
                    border-radius:0 4px 4px 0;padding:10px 14px;
                    font-size:12px;color:#2c4255;line-height:1.6;
                    white-space:pre-wrap;font-family:inherit;max-height:260px;overflow-y:auto;">
<?= htmlspecialchars($History) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div><!-- end two-column top -->

  <!-- ── Action bar ────────────────────────────────────────────────────────── -->
  <div style="background:#3B495A;padding:10px 20px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <button onclick="location.href='index.php?navigate=contribute&amp;kenteken=<?= $sl ?>&amp;mode=upload'"
            class="btn" style="font-size:12px;padding:5px 14px;">
      📷 Upload foto
    </button>
    <button onclick="snlRDWCheck('<?= $sl ?>')"
            class="btn" style="font-size:12px;padding:5px 14px;">
      🔍 Check RDW
    </button>
    <a href="index.php?navigate=navigateclassic&amp;k=<?= urlencode($License) ?>"
       style="font-size:11px;color:#aac8e0;margin-left:6px;">
      Klassieke weergave
    </a>
    <span style="margin-left:auto;font-size:11px;color:#8aabbf;">Deel: </span>
    <?php $pageUrl = 'https://www.supraclub.nl/carparts/index.php?navigate=' . rawurlencode($License); ?>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>"
       target="_blank" rel="noopener"
       style="font-size:11px;color:#8ab4d0;">Facebook</a>
    <a href="https://wa.me/?text=<?= urlencode('Check deze Supra: ' . $pageUrl) ?>"
       target="_blank" rel="noopener"
       style="font-size:11px;color:#8ab4d0;">WhatsApp</a>
  </div>

  <!-- ── RDW live data (hidden by default) ─────────────────────────────────── -->
  <div id="rdw-box-<?= $sl ?>"
       style="display:none;padding:14px 22px;background:#f0f5fa;border-top:1px solid #d0dce8;">
    <div style="font-size:11px;font-weight:bold;color:#3B495A;margin-bottom:6px;">
      RDW Gegevens (live)
    </div>
    <div id="rdw-ta-<?= $sl ?>"></div>
  </div>

  <!-- ── Gallery ───────────────────────────────────────────────────────────── -->
  <div style="padding:16px 20px;border-top:1px solid #d0dce8;">
    <div style="font-size:11px;font-weight:bold;color:#3B495A;text-transform:uppercase;
                letter-spacing:1px;margin-bottom:10px;">📸 Foto's</div>
    <?php
    include("./bolgallery.php");
    $switchClassic = true;
    bolGallery("./cars/$stripLicense/slides/", 5, 80, 50, $switchClassic);
    ?>
  </div>


</div><!-- end main card -->

<?php
endwhile;

$stmt->close();
mysqli_close($SNLDBConnection);
?>
