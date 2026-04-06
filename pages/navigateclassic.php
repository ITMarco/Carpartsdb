
<?php
	// navigateclassic.php — Classic car detail view
	// Access: index.php?navigate=navigateclassic&k=XX-99-XX
	$pagina = trim($_GET['k'] ?? '');
	if ($pagina === '') {
		echo "<div class='content-box'><h3>Geen kenteken</h3><p>Gebruik <code>?navigate=navigateclassic&amp;k=XX-99-XX</code></p></div>";
		return;
	}

	include 'connection.php';
	include 'stats_helper.php';
	stats_session_check($CarpartsConnection);

	$stmt = $CarpartsConnection->prepare("SELECT * FROM SNLDB WHERE License LIKE ?");

	if (!$stmt) {
		error_log("includesearch prepare failed: " . $CarpartsConnection->error);
		echo "<div class='content-box'><h3>Fout</h3><p>Database fout opgetreden.</p></div>";
		return;
	}

	$search_param = '%' . $pagina . '%';
	$stmt->bind_param("s", $search_param);
	$stmt->execute();
	$result = $stmt->get_result();
	$num = $result->num_rows;

	$CarpartsConnection->query("UPDATE `16915snldb`.`HITS` SET `searches` = searches + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");
	stats_day($CarpartsConnection, 'searches');

	if ($num == 0) {
?>
	<div class="content-box">
	       <h3>Helaas...</h3>
	       <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" />
	        <br><br>
<?php
		$pagina_display = htmlspecialchars(strtoupper($pagina));
		echo "<center><strong>" . $pagina_display . "</strong> staat nog niet in onze database.</center>";
		echo "<br><center>Is dit een Supra? Voeg 'm toe — het duurt maar 2 minuten:<br><br>";
		echo "<button onclick=\"location.href='index.php?navigate=contribute&amp;kenteken=" . urlencode($pagina) . "'\" class=\"btn\" style=\"margin:6px 0;padding:8px 18px;font-size:13px;\">➕ Voeg de supra toe</button>";
		echo "</center>";
		echo "</div>";
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
		var html = '<table style="border-collapse:collapse;font-size:11px;color:#000;">';
		rows.forEach(function(row) {
			html += '<tr><td style="padding:3px 10px 3px 0;font-weight:bold;white-space:nowrap;">' + row[0] + '</td><td style="padding:3px 0;">' + (row[1] || '-') + '</td></tr>';
		});
		html += '</table>';
		tbl.innerHTML = html;
	}).catch(function(err){ tbl.innerHTML = '<em>Fout: ' + err.message + '</em>'; });
}
</script>
<?php
	while ($row = $result->fetch_assoc()) {
		$License           = $row['License'];
		$Owner_display     = $row['Owner_display'];
		$Choise_Model      = $row['Choise_Model'];
		$Milage            = $row['Milage'];
		$Choise_Status     = $row['Choise_Status'];
		$Registration_date = $row['Registration_date'];
		$Build_date        = $row['Build_date'];
		$color             = $row['VIN_Colorcode'];
		$History           = $row['History'];
		$mods              = $row['Mods'];
		$Choise_Engine     = $row['Choise_Engine'];
		$Choise_Transmission = $row['Choise_Transmission'];

		$CarpartsConnection->query("UPDATE `16915snldb`.`HITS` SET `searchhits` = searchhits + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");

		$stripLicense = strtoupper(preg_replace('/\s*/m', '', $License));
		$sl = htmlspecialchars($stripLicense, ENT_QUOTES);
?>
	<div class="content-box">
        <h3>Supra <?php echo htmlspecialchars($License); ?>.</h3>
        <br><br>
<?php
		echo "<b>" . htmlspecialchars($License) . " " . htmlspecialchars($Owner_display) . "</b><br>";
		echo "Model: " . htmlspecialchars($Choise_Model) . "<br>";
		echo "Kilometrage: " . htmlspecialchars($Milage) . "<br>";
		echo "Kleur: " . htmlspecialchars($color) . "<BR>";
		echo "Motor: " . htmlspecialchars($Choise_Engine) . "<br>";
		echo "Versnellingsbak: " . htmlspecialchars($Choise_Transmission) . "<br>";
		echo "Status: " . htmlspecialchars($Choise_Status) . "<br>";
		echo "Geregistreerd op: " . htmlspecialchars($Registration_date) . "<br>";
		echo "Bouwjaar: " . htmlspecialchars($Build_date) . "<br>";
		echo "<a href='index.php?navigate=" . urlencode($License) . "'>Directe link</a><BR>";
		echo "<button onclick=\"snlRDWCheck('" . $sl . "')\" class=\"btn\" style=\"margin:6px 0;\">Check RDW</button> ";
		echo "<button onclick=\"location.href='index.php?navigate=contribute&amp;kenteken=" . $sl . "&amp;mode=upload'\" class=\"btn\" style=\"margin:6px 0;\">Upload foto</button><BR>";
		echo "<div id=\"rdw-box-" . $sl . "\" style=\"display:none;margin:8px 0;\">RDW Gegevens:<br><div id=\"rdw-ta-" . $sl . "\"></div></div><BR>";
		echo "Gedane mods:<BR><textarea name='mods' rows='12' cols='50' readonly>" . htmlspecialchars($mods) . "</textarea><BR>";
		echo "Historie:<BR><textarea name='history' rows='12' cols='50' readonly>" . htmlspecialchars($History) . "</textarea><BR>";

		$pageUrl = 'https://www.supraclub.nl/carparts/index.php?navigate=' . $License;
		echo "<br>Deel: ";
		echo "<a href='https://www.facebook.com/sharer/sharer.php?u=" . urlencode($pageUrl) . "' target='_blank' rel='noopener'>Facebook</a> &nbsp;|&nbsp; ";
		echo "<a href='https://wa.me/?text=" . urlencode('Check deze Supra op Supraclub.nl: ' . $pageUrl) . "' target='_blank' rel='noopener'>WhatsApp</a>";

		echo "<hr><br>";

		include("./bolgallery.php");
		$switchClassic = true;
		bolGallery("./cars/$stripLicense/slides/", 5, 80, 50, $switchClassic);

		echo "</div>";
	}

	$stmt->close();
	mysqli_close($CarpartsConnection);
?>


