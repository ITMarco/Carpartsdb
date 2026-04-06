
<?php
// Collect POST values for form pre-fill
$myLicense     = (isset($_POST['License'])      && $_SERVER['REQUEST_METHOD'] == 'POST') ? trim($_POST['License'])      : '';
$mymodel       = (isset($_POST['mark'])         && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST['mark']              : '';
$mystatus      = (isset($_POST['status'])       && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST['status']            : '';
$mycolor       = (isset($_POST['color'])        && $_SERVER['REQUEST_METHOD'] == 'POST') ? trim($_POST['color'])        : '';
$myengine      = (isset($_POST['engine'])       && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST['engine']            : '';
$mytransmission= (isset($_POST['transmission']) && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST['transmission']      : '';
$mytranstype   = (isset($_POST['trans'])        && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST['trans']             : '';
$mytrefwoord   = (isset($_POST['trefwoord'])    && $_SERVER['REQUEST_METHOD'] == 'POST') ? trim($_POST['trefwoord'])   : '';
$num = 0;
$result = null;
$stmt = null;

function cs_sel($val, $cur) { return $val === $cur ? ' selected' : ''; }
function cs_chk($val, $cur) { return $val === $cur ? ' checked'  : ''; }
?>

<!-- ═══ Search form ══════════════════════════════════════════════════════════ -->
<div class="content-box">
  <h3>Supras zoeken</h3>

  <form name="contact" id="contact" action="index.php?navigate=customsearch" method="post">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;max-width:640px;margin-top:14px;">

    <!-- Type -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Type</label>
      <select name="mark" style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;">
        <option value=""<?= cs_sel('', $mymodel) ?>>Alle</option>
        <option value="MA-46 (MKI)"<?=    cs_sel('MA-46 (MKI)',    $mymodel) ?>>Celica Supra MKI</option>
        <option value="MA-60 (MKII)"<?=   cs_sel('MA-60 (MKII)',   $mymodel) ?>>Celica Supra MKII</option>
        <option value="MA-70 (MKIII)"<?=  cs_sel('MA-70 (MKIII)',  $mymodel) ?>>Supra MKIII MA</option>
        <option value="JZA70"<?=          cs_sel('JZA70',          $mymodel) ?>>Supra MKIII JZA</option>
        <option value="JA-80 (MKIV)"<?=   cs_sel('JA-80 (MKIV)',   $mymodel) ?>>Supra MKIV</option>
        <option value="A-90 (MKV)"<?=     cs_sel('A-90 (MKV)',     $mymodel) ?>>Supra MKV</option>
      </select>
    </div>

    <!-- Motor -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Motor</label>
      <select name="engine" style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;">
        <option value=""<?= cs_sel('', $myengine) ?>>Alle</option>
        <option value="4M-E"<?=           cs_sel('4M-E',           $myengine) ?>>4M-E</option>
        <option value="5M-GE"<?=          cs_sel('5M-GE',          $myengine) ?>>5M-GE</option>
        <option value="7M-GE"<?=          cs_sel('7M-GE',          $myengine) ?>>7M-GE</option>
        <option value="7M-GTE"<?=         cs_sel('7M-GTE',         $myengine) ?>>7M-GTE</option>
        <option value="1JZ-GTE"<?=        cs_sel('1JZ-GTE',        $myengine) ?>>1JZ-GTE</option>
        <option value="1JZ-GTE-VVTI"<?=   cs_sel('1JZ-GTE-VVTI',   $myengine) ?>>1JZ-GTE VVT-I</option>
        <option value="1.5JZ-GTE"<?=      cs_sel('1.5JZ-GTE',      $myengine) ?>>1.5JZ-GTE</option>
        <option value="2JZ-GE"<?=         cs_sel('2JZ-GE',         $myengine) ?>>2JZ-GE</option>
        <option value="2JZ-GTE"<?=        cs_sel('2JZ-GTE',        $myengine) ?>>2JZ-GTE</option>
        <option value="1G-GTE"<?=         cs_sel('1G-GTE',         $myengine) ?>>1G-GTE</option>
        <option value="BMW-B48"<?=        cs_sel('BMW-B48',        $myengine) ?>>BMW-B48</option>
        <option value="BMW-B58"<?=        cs_sel('BMW-B58',        $myengine) ?>>BMW-B58</option>
        <option value="Unknown"<?=        cs_sel('Unknown',        $myengine) ?>>Unknown</option>
      </select>
    </div>

    <!-- Status -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Status</label>
      <select name="status" style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;">
        <option value=""<?=               cs_sel('',               $mystatus) ?>>Alle</option>
        <option value="Running"<?=        cs_sel('Running',        $mystatus) ?>>Rijdend</option>
        <option value="Garage"<?=         cs_sel('Garage',         $mystatus) ?>>Garage</option>
        <option value="Forsale"<?=        cs_sel('Forsale',        $mystatus) ?>>Te koop</option>
        <option value="No Road License"<?= cs_sel('No Road License', $mystatus) ?>>Geen kenteken</option>
        <option value="Wrecked"<?=        cs_sel('Wrecked',        $mystatus) ?>>Wrecked</option>
        <option value="Not Available"<?=  cs_sel('Not Available',  $mystatus) ?>>Not Available</option>
      </select>
    </div>

    <!-- Bak type -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Versnellingsbak</label>
      <select name="transmission" style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;">
        <option value=""<?= cs_sel('', $mytransmission) ?>>Alle</option>
        <option value="W50 (5 Speed manual 4M)"<?=         cs_sel('W50 (5 Speed manual 4M)',         $mytransmission) ?>>W50 (5-speed manual 4M)</option>
        <option value="W58 (5 speed manual 5M)"<?=         cs_sel('W58 (5 speed manual 5M)',         $mytransmission) ?>>W58 (5-speed manual 5M)</option>
        <option value="W58 (5 speed manual 7M-GE)"<?=      cs_sel('W58 (5 speed manual 7M-GE)',      $mytransmission) ?>>W58 (5-speed manual 7M-GE)</option>
        <option value="W58 (5 speed manual 2JZ)"<?=        cs_sel('W58 (5 speed manual 2JZ)',        $mytransmission) ?>>W58 (5-speed manual 2JZ)</option>
        <option value="V160 (6 speed manual 2JZ)"<?=       cs_sel('V160 (6 speed manual 2JZ)',       $mytransmission) ?>>V160 (6-speed manual 2JZ)</option>
        <option value="V161 (6 speed manual 2JZ)"<?=       cs_sel('V161 (6 speed manual 2JZ)',       $mytransmission) ?>>V161 (6-speed manual 2JZ)</option>
        <option value="R154 (5 Speed manual 7M-GTE)"<?=    cs_sel('R154 (5 Speed manual 7M-GTE)',    $mytransmission) ?>>R154 (5-speed manual 7M-GTE)</option>
        <option value="A43DE (4 Speed Auto 5M)"<?=         cs_sel('A43DE (4 Speed Auto 5M)',         $mytransmission) ?>>A43DE (4-speed auto 5M)</option>
        <option value="A340E (4 Speed Auto 7M)"<?=         cs_sel('A340E (4 Speed Auto 7M)',         $mytransmission) ?>>A340E (4-speed auto 7M)</option>
        <option value="A342E (4 speed Auto 2JZ)"<?=        cs_sel('A342E (4 speed Auto 2JZ)',        $mytransmission) ?>>A342E (4-speed auto 2JZ)</option>
        <option value="T56 (Upgrade kit for JZ)"<?=        cs_sel('T56 (Upgrade kit for JZ)',        $mytransmission) ?>>T56 6-speed (upgrade 2JZ)</option>
        <option value="ZF 8HP (8 speed Auto MK5)"<?=       cs_sel('ZF 8HP (8 speed Auto MK5)',       $mytransmission) ?>>ZF 8HP (8-speed auto MK5)</option>
        <option value="ZF S6-53 (6 speed manual MK5)"<?=   cs_sel('ZF S6-53 (6 speed manual MK5)',   $mytransmission) ?>>ZF S6-53 (6-speed manual MK5)</option>
        <option value="Other"<?= cs_sel('Other', $mytransmission) ?>>Other</option>
      </select>
    </div>

    <!-- Kleur -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Kleur</label>
      <input type="text" name="color" value="<?= htmlspecialchars($mycolor) ?>"
             style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;box-sizing:border-box;" />
    </div>

    <!-- Kenteken -->
    <div>
      <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Kenteken</label>
      <input type="text" id="naam" name="License" value="<?= htmlspecialchars($myLicense) ?>"
             style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;box-sizing:border-box;" />
    </div>

  </div>

  <!-- Overbrenging -->
  <div style="margin-top:12px;">
    <span style="font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-right:10px;">Overbrenging:</span>
    <label style="font-size:13px;margin-right:14px;cursor:pointer;">
      <input type="radio" name="trans" value="M"<?= cs_chk('M', $mytranstype) ?> style="margin-right:4px;" />Handbak
    </label>
    <label style="font-size:13px;cursor:pointer;">
      <input type="radio" name="trans" value="A"<?= cs_chk('A', $mytranstype) ?> style="margin-right:4px;" />Automaat
    </label>
  </div>

  <!-- Trefwoord -->
  <div style="margin-top:12px;max-width:640px;">
    <label style="display:block;font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Of zoek op trefwoord</label>
    <input type="text" name="trefwoord" value="<?= htmlspecialchars($mytrefwoord) ?>"
           style="width:100%;padding:5px 8px;font-size:13px;border:1px solid #b0c4d8;border-radius:3px;box-sizing:border-box;" />
  </div>

  <div style="margin-top:16px;">
    <input type="submit" value="Zoeken" class="btn" style="padding:7px 28px;font-size:14px;" />
  </div>
  </form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	include 'connection.php';
	include 'stats_helper.php';
	stats_session_check($CarpartsConnection);

	$conditions = array();
	$params = array();
	$types = '';

	if (!empty($mymodel))        { $conditions[] = "Choise_Model = ?";        $params[] = $mymodel;                  $types .= 's'; }
	if (!empty($mystatus))       { $conditions[] = "Choise_Status = ?";       $params[] = $mystatus;                 $types .= 's'; }
	if (!empty($mycolor))        { $conditions[] = "VIN_Colorcode LIKE ?";    $params[] = $mycolor;                  $types .= 's'; }
	if (!empty($myengine))       { $conditions[] = "Choise_Engine = ?";       $params[] = $myengine;                 $types .= 's'; }
	if (!empty($mytransmission)) { $conditions[] = "Choise_Transmission = ?"; $params[] = $mytransmission;           $types .= 's'; }
	if (!empty($mytranstype))    { $conditions[] = "MA = ?";                  $params[] = $mytranstype;              $types .= 's'; }
	if (!empty($myLicense))      { $conditions[] = "License LIKE ?";          $params[] = '%' . $myLicense . '%';   $types .= 's'; }

	if (count($conditions) > 0) {
		$query = "SELECT * FROM SNLDB WHERE " . implode(" AND ", $conditions);
		$stmt = $CarpartsConnection->prepare($query);
		if ($stmt) {
			$bind_params = array_merge(array($types), $params);
			$refs = array();
			foreach ($bind_params as $key => $value) { $refs[$key] = &$bind_params[$key]; }
			call_user_func_array(array($stmt, 'bind_param'), $refs);
			$stmt->execute();
			$result = $stmt->get_result();
		} else {
			error_log("customsearch prepare failed: " . $CarpartsConnection->error);
		}
	} else {
		$is_admin = !empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1;
		if ($is_admin) {
			$stmt = $CarpartsConnection->prepare(
				"SELECT *, MATCH(License, Owner_display, VIN_Colorcode, Mods, History)
				          AGAINST (? IN BOOLEAN MODE) AS relevance
				 FROM SNLDB
				 WHERE MATCH(License, Owner_display, VIN_Colorcode, Mods, History)
				       AGAINST (? IN BOOLEAN MODE)
				 ORDER BY relevance DESC"
			);
			if ($stmt) { $stmt->bind_param('ss', $mytrefwoord, $mytrefwoord); }
		} else {
			$p    = '%' . $mytrefwoord . '%';
			$stmt = $CarpartsConnection->prepare(
				"SELECT *, 0 AS relevance FROM SNLDB
				 WHERE License LIKE ? OR Choise_Model LIKE ? OR Choise_Engine LIKE ?
				    OR VIN_Colorcode LIKE ? OR Mods LIKE ?
				 ORDER BY License"
			);
			if ($stmt) { $stmt->bind_param('sssss', $p, $p, $p, $p, $p); }
		}
		if ($stmt) {
			$stmt->execute();
			$result = $stmt->get_result();
		} else {
			error_log("customsearch fulltext prepare failed: " . $CarpartsConnection->error);
		}
	}

	if ($result) {
		$num = $result->num_rows;
	}

	$CarpartsConnection->query("UPDATE `16915snldb`.`HITS` SET `searches` = searches + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");
	stats_day($CarpartsConnection, 'searches');

	if ($num == 0) {
		echo "<div class='content-box'>";
		echo "<h3>Helaas...</h3>";
		echo "<p style='color:#666;'>Geen supras gevonden met deze zoekopdracht.</p>";
		$contribute_url = 'index.php?navigate=contribute';
		if (!empty($myLicense)) { $contribute_url .= '&kenteken=' . urlencode(trim($myLicense)); }
		echo "<button onclick=\"location.href='" . htmlspecialchars($contribute_url, ENT_QUOTES) . "'\" class=\"btn\">Voeg de supra zelf toe</button>";
		echo "</div>";
	}

// Status badge helper
function cs_status_badge($status) {
	$map = [
		'Running'         => ['#2a8a2a', 'Rijdend'],
		'Garage'          => ['#5588bb', 'Garage'],
		'Forsale'         => ['#c8a020', 'Te koop'],
		'Wrecked'         => ['#c04040', 'Wrak'],
		'Not Available'   => ['#c04040', 'N/A'],
		'No Road License' => ['#c04040', 'Geen kenteken'],
	];
	[$color, $label] = $map[$status] ?? ['#888', htmlspecialchars($status)];
	return "<span style='display:inline-block;padding:2px 7px;border-radius:3px;background:{$color};color:#fff;font-size:10px;font-weight:bold;'>{$label}</span>";
}
?>
<script>
function snlRDWCheck(k) {
	var kClean = k.replace(/[\s\-]/g, '').toUpperCase();
	var div = document.getElementById('rdw-box-' + k);
	var tbl = document.getElementById('rdw-ta-' + k);
	tbl.innerHTML = '<em>RDW gegevens ophalen...</em>';
	div.style.display = 'block';
	var base = 'https://opendata.rdw.nl/resource/';
	var fmtD = function(d) {
		if (!d) return '-';
		var s = d.replace(/\D/g,'');
		return s.length === 8 ? s.slice(6)+'-'+s.slice(4,6)+'-'+s.slice(0,4) : d;
	};
	Promise.all([
		fetch(base + 'm9d7-ebf2.json?kenteken=' + kClean + '&$limit=1').then(function(r){ return r.json(); }),
		fetch(base + '8ys7-d773.json?kenteken=' + kClean + '&$limit=3').then(function(r){ return r.json(); })
	]).then(function(res) {
		var v = res[0], fuels = res[1];
		if (!v || v.length === 0) { tbl.innerHTML = '<em>Geen RDW gegevens gevonden voor ' + kClean + '</em>'; return; }
		v = v[0];
		var apkR = v.vervaldatum_apk || '';
		var apkS = fmtD(apkR), apkO = '';
		if (apkR.replace(/\D/g,'').length === 8) {
			var exp = new Date(apkR.slice(0,4)+'-'+apkR.slice(4,6)+'-'+apkR.slice(6,8)).getTime();
			apkO = exp < Date.now() ? ' <span style="color:#c00">(VERLOPEN)</span>' : (exp < Date.now()+60*86400000 ? ' <span style="color:#c80">(verloopt binnenkort)</span>' : ' <span style="color:#060">(geldig)</span>');
		}
		var rows = [
			['Merk',                  v.merk],
			['Handelsbenaming',       v.handelsbenaming],
			['Voertuigsoort',         v.voertuigsoort],
			['Eerste kleur',          v.eerste_kleur],
			['Tweede kleur',          v.tweede_kleur],
			['Eerste toelating',      fmtD(v.datum_eerste_toelating)],
			['Eerste tenaamstelling', fmtD(v.datum_eerste_tenaamstelling_in_nederland)],
			['Tenaamstelling',        fmtD(v.datum_tenaamstelling)],
			['APK vervaldatum',       apkS + apkO],
			['WAM verzekerd',         v.wam_verzekerd],
			['Cilinders',             v.aantal_cilinders],
			['Cilinderinhoud (cc)',   v.cilinderinhoud],
			['Massa ledig (kg)',       v.massa_ledig_voertuig],
			['Catalogusprijs',        v.catalogusprijs ? '\u20ac '+parseInt(v.catalogusprijs).toLocaleString('nl-NL') : null]
		];
		if (fuels && fuels.length > 0) {
			fuels.forEach(function(fuel, fi) {
				var fl = fuels.length > 1 ? 'Brandstof '+(fi+1) : 'Brandstof';
				rows.push([fl, fuel.brandstof_omschrijving || '-']);
				if (fuel.emissiecode_omschrijving) rows.push(['Emissienorm', fuel.emissiecode_omschrijving]);
				if (fuel.nettomaximumvermogen)     rows.push(['Max. vermogen (kW)', fuel.nettomaximumvermogen]);
			});
		}
		var html = '<table style="border-collapse:collapse;font-size:11px;color:#2c4255;">';
		rows.forEach(function(row) {
			if (!row[1]) return;
			html += '<tr><td style="padding:3px 12px 3px 0;font-weight:bold;white-space:nowrap;color:#4a7090;">' + row[0] + '</td><td style="padding:3px 0;">' + row[1] + '</td></tr>';
		});
		html += '</table>';
		tbl.innerHTML = html;
	}).catch(function(err){ tbl.innerHTML = '<em>Fout: ' + err.message + '</em>'; });
}
</script>
<?php
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$License             = $row['License'];
			$Owner_display       = $row['Owner_display'];
			$Choise_Model        = $row['Choise_Model'];
			$Milage              = $row['Milage'];
			$Choise_Status       = $row['Choise_Status'];
			$Registration_date   = $row['Registration_date'];
			$Build_date          = $row['Build_date'];
			$color               = $row['VIN_Colorcode'];
			$History             = $row['History'];
			$mods                = $row['Mods'];
			$Choise_Engine       = $row['Choise_Engine'];
			$Choise_Transmission = $row['Choise_Transmission'];

			$CarpartsConnection->query("UPDATE `16915snldb`.`HITS` SET `searchhits` = searchhits + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");

			$sl           = htmlspecialchars(strtoupper(preg_replace('/\s*/m', '', $License)), ENT_QUOTES);
			$stripLicense = strtoupper(preg_replace('/\s*/m', '', $License));
			$nav_url      = 'index.php?navigate=' . urlencode($License);
?>
<div class="content-box">

  <!-- Header row: license + owner + status badge -->
  <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
    <a href="<?= $nav_url ?>" style="font-size:20px;font-weight:bold;color:#2c5080;text-decoration:none;">
      <?= htmlspecialchars($License) ?>
    </a>
    <?= cs_status_badge($Choise_Status) ?>
    <span style="font-size:12px;color:#4a7090;font-weight:bold;"><?= htmlspecialchars($Choise_Model) ?></span>
    <span style="font-size:13px;color:#555;flex:1;">
      <?php
        $cs_owner_visible = (!empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1)
            || (!empty($_SESSION['user_license']) && strtoupper($_SESSION['user_license']) === strtoupper($License))
            || !empty($row['Owner_show']);
        echo $cs_owner_visible && $Owner_display !== ''
            ? htmlspecialchars($Owner_display)
            : '<em style="color:#888;font-size:11px;">Verborgen of niet opgegeven.</em>';
      ?>
    </span>
  </div>

  <!-- Specs grid -->
  <div style="display:flex;flex-wrap:wrap;gap:6px 20px;margin-bottom:12px;font-size:12px;color:#444;">
    <?php if ($Choise_Engine): ?>
    <div><span style="color:#4a7090;font-weight:bold;">Motor:</span> <?= htmlspecialchars($Choise_Engine) ?></div>
    <?php endif; ?>
    <?php if ($Choise_Transmission): ?>
    <div><span style="color:#4a7090;font-weight:bold;">Bak:</span> <?= htmlspecialchars($Choise_Transmission) ?></div>
    <?php endif; ?>
    <?php if ($color): ?>
    <div><span style="color:#4a7090;font-weight:bold;">Kleur:</span> <?= htmlspecialchars($color) ?></div>
    <?php endif; ?>
    <?php if ($Milage): ?>
    <div><span style="color:#4a7090;font-weight:bold;">KM-stand:</span> <?= htmlspecialchars($Milage) ?></div>
    <?php endif; ?>
    <?php if ($Build_date): ?>
    <div><span style="color:#4a7090;font-weight:bold;">Bouwjaar:</span> <?= htmlspecialchars($Build_date) ?></div>
    <?php endif; ?>
    <?php if ($Registration_date): ?>
    <div><span style="color:#4a7090;font-weight:bold;">Geregistreerd:</span> <?= htmlspecialchars($Registration_date) ?></div>
    <?php endif; ?>
  </div>

  <!-- Action buttons -->
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
    <a href="<?= $nav_url ?>" class="btn" style="font-size:12px;padding:5px 14px;">Bekijk auto</a>
    <button onclick="snlRDWCheck('<?= $sl ?>')" class="btn" style="font-size:12px;padding:5px 14px;">Check RDW</button>
    <button onclick="location.href='index.php?navigate=contribute&amp;kenteken=<?= $sl ?>&amp;mode=upload'" class="btn" style="font-size:12px;padding:5px 14px;">Upload foto</button>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://www.supraclub.nl/carparts/index.php?navigate=' . $License) ?>" target="_blank" rel="noopener" class="btn" style="font-size:12px;padding:5px 14px;">Deel Facebook</a>
    <a href="https://wa.me/?text=<?= urlencode('Check deze Supra op Supraclub.nl: https://www.supraclub.nl/carparts/index.php?navigate=' . $License) ?>" target="_blank" rel="noopener" class="btn" style="font-size:12px;padding:5px 14px;">Deel WhatsApp</a>
  </div>

  <!-- RDW popup -->
  <div id="rdw-box-<?= $sl ?>" style="display:none;background:#f0f5fa;border-radius:4px;padding:10px 14px;margin-bottom:10px;">
    <div style="font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">RDW Gegevens</div>
    <div id="rdw-ta-<?= $sl ?>"></div>
  </div>

  <?php if ($num < 2): ?>
  <!-- Mods + history (single result only) -->
  <?php if (!empty(trim($mods))): ?>
  <div style="margin-top:10px;">
    <div style="font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Mods</div>
    <textarea rows="6" cols="60" readonly style="width:100%;font-size:12px;border:1px solid #c0d0e0;border-radius:3px;padding:6px;resize:vertical;background:#fafcff;"><?= htmlspecialchars($mods) ?></textarea>
  </div>
  <?php endif; ?>
  <?php if (!empty(trim($History))): ?>
  <div style="margin-top:10px;">
    <div style="font-size:10px;font-weight:bold;color:#4a7090;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Historie</div>
    <textarea rows="8" cols="60" readonly style="width:100%;font-size:12px;border:1px solid #c0d0e0;border-radius:3px;padding:6px;resize:vertical;background:#fafcff;"><?= htmlspecialchars($History) ?></textarea>
  </div>
  <?php endif; ?>
  <?php
    include("./bolgallery.php");
    $switchClassic = true;
    bolGallery("./cars/$stripLicense/slides/", 5, 80, 50, $switchClassic);
  endif; ?>

</div>
<?php
		} // end while

		if ($stmt) $stmt->close();
		mysqli_close($CarpartsConnection);
	}
}
?>
