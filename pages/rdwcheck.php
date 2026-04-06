<?php
// RDW Open Data check - queries opendata.rdw.nl for vehicle information
// Accepts: ?navigate=rdwcheck&kenteken=XX-XX-XX (from index.php routing)

$raw_kenteken = isset($_GET['kenteken']) ? $_GET['kenteken'] : '';

// Strip dashes/spaces and uppercase
$kenteken = strtoupper(preg_replace('/[\s\-]/', '', $raw_kenteken));

if (empty($kenteken)) {
    echo "<div class=\"content-box\"><h3>RDW Check</h3><p style='color:red;'>Geen kenteken opgegeven.</p></div>";
    return;
}

// --- Helper: format RDW date (YYYYMMDD -> DD-MM-YYYY) ---
function rdwFormatDate($val) {
    if (empty($val)) return '-';
    $s = preg_replace('/[^0-9]/', '', $val);
    if (strlen($s) === 8) {
        return substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
    }
    return htmlspecialchars($val);
}

// --- Helper: safe display ---
function rdwVal($data, $key) {
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : '-';
}

// --- Fetch main vehicle data ---
$url_main = 'https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=' . urlencode($kenteken) . '&$limit=1';

$ch = curl_init($url_main);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'SNLDB/1.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response_main = curl_exec($ch);
$http_code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err      = curl_error($ch);
unset($ch);

// --- Fetch fuel data (separate RDW endpoint) ---
$url_fuel = 'https://opendata.rdw.nl/resource/8ys7-d773.json?kenteken=' . urlencode($kenteken) . '&$limit=5';

$ch2 = curl_init($url_fuel);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
curl_setopt($ch2, CURLOPT_USERAGENT, 'SNLDB/1.0');
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
$response_fuel = curl_exec($ch2);
unset($ch2);

$vehicle  = null;
$fuels    = [];
$error_msg = '';

if ($curl_err) {
    $error_msg = 'Verbindingsfout: ' . htmlspecialchars($curl_err);
} elseif ($http_code !== 200) {
    $error_msg = 'RDW API antwoordde met HTTP ' . intval($http_code);
} else {
    $decoded = json_decode($response_main, true);
    if (is_array($decoded) && count($decoded) > 0) {
        $vehicle = $decoded[0];
    }
}

if ($response_fuel) {
    $decoded_fuel = json_decode($response_fuel, true);
    if (is_array($decoded_fuel)) {
        $fuels = $decoded_fuel;
    }
}

// --- APK status helper ---
function rdwApkStatus($vervaldatum) {
    if (empty($vervaldatum)) return '';
    $s = preg_replace('/[^0-9]/', '', $vervaldatum);
    if (strlen($s) !== 8) return '';
    $y = intval(substr($s, 0, 4));
    $m = intval(substr($s, 4, 2));
    $d = intval(substr($s, 6, 2));
    $expiry = mktime(0, 0, 0, $m, $d, $y);
    $now    = time();
    if ($expiry < $now) {
        return " <span style='color:red;font-weight:bold;'>(VERLOPEN)</span>";
    } elseif ($expiry < $now + (60 * 86400)) {
        return " <span style='color:orange;'>(verloopt binnenkort)</span>";
    }
    return " <span style='color:green;'>(geldig)</span>";
}

?>
<div class="content-box">
    <h3>RDW Voertuiggegevens: <?php echo htmlspecialchars($kenteken); ?></h3>
    <a href="javascript:history.back()" class="btn" style="margin-bottom:12px;display:inline-block;">&larr; Terug</a>

<?php if ($error_msg): ?>
    <p style="color:red;"><?php echo $error_msg; ?></p>
<?php elseif (!$vehicle): ?>
    <p style="color:#888;">Geen gegevens gevonden bij de RDW voor kenteken <strong><?php echo htmlspecialchars($kenteken); ?></strong>.</p>
<?php else: ?>

    <table style="border-collapse:collapse;width:100%;max-width:600px;color:#000;">
    <?php
    $apk_verval = isset($vehicle['vervaldatum_apk']) ? $vehicle['vervaldatum_apk'] : '';

    $rows = [
        'Kenteken'              => rdwVal($vehicle, 'kenteken'),
        'Merk'                  => rdwVal($vehicle, 'merk'),
        'Handelsbenaming'       => rdwVal($vehicle, 'handelsbenaming'),
        'Voertuigsoort'         => rdwVal($vehicle, 'voertuigsoort'),
        'Inrichting'            => rdwVal($vehicle, 'inrichting'),
        'Eerste kleur'          => rdwVal($vehicle, 'eerste_kleur'),
        'Tweede kleur'          => rdwVal($vehicle, 'tweede_kleur'),
        'Eerste toelating'      => rdwFormatDate(isset($vehicle['datum_eerste_toelating']) ? $vehicle['datum_eerste_toelating'] : ''),
        'Eerste tenaamstelling' => rdwFormatDate(isset($vehicle['datum_eerste_tenaamstelling_in_nederland']) ? $vehicle['datum_eerste_tenaamstelling_in_nederland'] : ''),
        'Tenaamstelling'        => rdwFormatDate(isset($vehicle['datum_tenaamstelling']) ? $vehicle['datum_tenaamstelling'] : ''),
        'Vervaldatum APK'       => rdwFormatDate($apk_verval) . rdwApkStatus($apk_verval),
        'WAM verzekerd'         => rdwVal($vehicle, 'wam_verzekerd'),
        'Aantal cilinders'      => rdwVal($vehicle, 'aantal_cilinders'),
        'Cilinderinhoud (cc)'   => rdwVal($vehicle, 'cilinderinhoud'),
        'Massa ledig (kg)'      => rdwVal($vehicle, 'massa_ledig_voertuig'),
        'Max. massa (kg)'       => rdwVal($vehicle, 'toegestane_maximum_massa_voertuig'),
        'Aantal zitplaatsen'    => rdwVal($vehicle, 'aantal_zitplaatsen'),
        'Catalogusprijs'        => isset($vehicle['catalogusprijs']) && $vehicle['catalogusprijs'] !== '' ? '&euro; ' . number_format(intval($vehicle['catalogusprijs']), 0, ',', '.') : '-',
    ];

    $alt = false;
    foreach ($rows as $label => $value):
        $bg = $alt ? '#f5f5f5' : '#fff';
        $alt = !$alt;
    ?>
        <tr style="background:<?php echo $bg; ?>;">
            <td style="padding:5px 10px;font-weight:bold;white-space:nowrap;border-bottom:1px solid #ddd;width:45%;"><?php echo htmlspecialchars($label); ?></td>
            <td style="padding:5px 10px;border-bottom:1px solid #ddd;"><?php echo $value; ?></td>
        </tr>
    <?php endforeach; ?>
    </table>

<?php if (!empty($fuels)): ?>
    <h4 style="margin-top:16px;">Brandstof</h4>
    <table style="border-collapse:collapse;width:100%;max-width:600px;color:#000;">
    <?php
    $fuel_labels = [
        'brandstof_omschrijving' => 'Brandstof',
        'emissiecode_omschrijving' => 'Emissienorm',
        'uitstoot_co2' => 'CO&#8322; uitstoot (g/km)',
        'milieuklasse_eg_goedkeuring_licht' => 'Milieuklasse',
        'nettomaximumvermogen' => 'Netto max. vermogen (kW)',
        'nominaal_continu_maximumvermogen' => 'Continu max. vermogen (kW)',
        'emissie_deeltjes_type1_wltp' => 'Deeltjesemissie WLTP',
    ];
    foreach ($fuels as $fi => $fuel):
        if ($fi > 0): ?>
        <tr><td colspan="2" style="padding:4px 10px;font-style:italic;color:#888;font-size:12px;">Brandstof <?php echo $fi + 1; ?></td></tr>
    <?php endif;
        $alt = false;
        foreach ($fuel_labels as $fkey => $flabel):
            if (!isset($fuel[$fkey]) || $fuel[$fkey] === '') continue;
            $bg = $alt ? '#f5f5f5' : '#fff';
            $alt = !$alt;
    ?>
        <tr style="background:<?php echo $bg; ?>;">
            <td style="padding:5px 10px;font-weight:bold;white-space:nowrap;border-bottom:1px solid #ddd;width:45%;"><?php echo $flabel; ?></td>
            <td style="padding:5px 10px;border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($fuel[$fkey]); ?></td>
        </tr>
    <?php endforeach;
    endforeach; ?>
    </table>
<?php endif; ?>

    <p style="margin-top:14px;font-size:12px;color:#888;">
        Bron: <a href="https://opendata.rdw.nl" target="_blank" rel="noopener">opendata.rdw.nl</a>
    </p>

<?php endif; ?>
</div>
