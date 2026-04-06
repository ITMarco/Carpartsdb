<?php
// contribute.php - Public contribution page
// Allows spotters to: upload photos to existing DB entries, or add new Supras via RDW lookup.
// Authentication: CSRF token + contributor name required. No admin session needed.

// CSRF token setup (session already started by index.php)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Helpers ---

function contrib_csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '" />';
}

function contrib_validate_csrf() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Honeypot: renders an invisible field that only bots fill in
function contrib_honeypot_field() {
    // Visually hidden via inline style; field name is intentionally bland to attract bots
    echo '<div style="position:absolute;left:-9999px;height:0;overflow:hidden;" aria-hidden="true">';
    echo '<label for="hp_website">Website</label>';
    echo '<input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off" />';
    echo '</div>';
}

// Returns true if the honeypot was triggered (bot detected)
function contrib_honeypot_triggered() {
    return !empty($_POST['hp_website']);
}

// Sanitize a license plate string: strip spaces, uppercase, keep only A-Z0-9-
function contrib_sanitize_plate($raw) {
    $s = preg_replace('/\s*/m', '', $raw);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9-]/', '', $s);
    return $s;
}

// Validate plate has dashes and looks like a Dutch plate
function contrib_validate_plate_format($plate) {
    return (bool) preg_match('/^[A-Z0-9]{1,3}-[A-Z0-9]{1,3}-[A-Z0-9]{1,3}$/', $plate);
}

// Format RDW date YYYYMMDD → DD-MM-YYYY
function contrib_rdw_date($val) {
    if (empty($val)) return '';
    $s = preg_replace('/[^0-9]/', '', $val);
    if (strlen($s) === 8) {
        return substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
    }
    return '';
}

// Fetch RDW data via PHP cURL (server-side, consistent with rdwcheck.php)
function contrib_fetch_rdw($kenteken_clean) {
    $result = ['vehicle' => null, 'fuels' => [], 'error' => ''];

    $url_main = 'https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=' . urlencode($kenteken_clean) . '&$limit=1';
    $url_fuel = 'https://opendata.rdw.nl/resource/8ys7-d773.json?kenteken=' . urlencode($kenteken_clean) . '&$limit=5';

    $ch = curl_init($url_main);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SNLDB/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp_main = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    unset($ch);

    if ($curl_err) {
        $result['error'] = 'Verbindingsfout: ' . htmlspecialchars($curl_err);
        return $result;
    }
    if ($http_code !== 200) {
        $result['error'] = 'RDW API antwoordde met HTTP ' . intval($http_code);
        return $result;
    }

    $decoded = json_decode($resp_main, true);
    if (is_array($decoded) && count($decoded) > 0) {
        $result['vehicle'] = $decoded[0];
    }

    $ch2 = curl_init($url_fuel);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_USERAGENT, 'SNLDB/1.0');
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
    $resp_fuel = curl_exec($ch2);
    unset($ch2);

    if ($resp_fuel) {
        $decoded_fuel = json_decode($resp_fuel, true);
        if (is_array($decoded_fuel)) {
            $result['fuels'] = $decoded_fuel;
        }
    }

    return $result;
}

// Build a plain-text archive of all RDW data for storing in the History field
function contrib_rdw_archive_text($v, $fuels) {
    $pad = function($label) { return str_pad($label . ':', 30); };

    $fmtD = function($val) {
        if (empty($val)) return '-';
        $s = preg_replace('/[^0-9]/', '', $val);
        if (strlen($s) === 8) {
            return substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
        }
        return $val;
    };

    $val = function($key) use ($v) {
        return (isset($v[$key]) && $v[$key] !== '') ? $v[$key] : '-';
    };

    // APK status
    $apk_raw = isset($v['vervaldatum_apk']) ? $v['vervaldatum_apk'] : '';
    $apk_fmt = '-';
    $apk_status = '';
    if ($apk_raw !== '') {
        $s = preg_replace('/[^0-9]/', '', $apk_raw);
        if (strlen($s) === 8) {
            $apk_fmt = substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
            $expiry  = mktime(0, 0, 0, intval(substr($s, 4, 2)), intval(substr($s, 6, 2)), intval(substr($s, 0, 4)));
            $now     = time();
            if ($expiry < $now) {
                $apk_status = ' (VERLOPEN)';
            } elseif ($expiry < $now + (60 * 86400)) {
                $apk_status = ' (verloopt binnenkort)';
            } else {
                $apk_status = ' (geldig)';
            }
        }
    }

    $t  = "=== RDW Gegevens (archief, opgehaald " . date('d-m-Y') . ") ===\n";
    $t .= $pad('Kenteken')               . $val('kenteken')                   . "\n";
    $t .= $pad('Merk')                   . $val('merk')                       . "\n";
    $t .= $pad('Handelsbenaming')        . $val('handelsbenaming')             . "\n";
    $t .= $pad('Voertuigsoort')          . $val('voertuigsoort')               . "\n";
    $t .= $pad('Inrichting')             . $val('inrichting')                  . "\n";
    $t .= $pad('Eerste kleur')           . $val('eerste_kleur')                . "\n";
    $t .= $pad('Tweede kleur')           . $val('tweede_kleur')                . "\n";
    $t .= $pad('Eerste toelating')       . $fmtD($val('datum_eerste_toelating'))                          . "\n";
    $t .= $pad('Eerste tenaamstelling NL') . $fmtD($val('datum_eerste_tenaamstelling_in_nederland'))      . "\n";
    $t .= $pad('Laatste tenaamstelling') . $fmtD($val('datum_tenaamstelling')) . "\n";
    $t .= $pad('Vervaldatum APK')        . $apk_fmt . $apk_status              . "\n";
    $t .= $pad('WAM verzekerd')          . $val('wam_verzekerd')               . "\n";
    $t .= $pad('Aantal cilinders')       . $val('aantal_cilinders')            . "\n";
    $t .= $pad('Cilinderinhoud (cc)')    . $val('cilinderinhoud')              . "\n";
    $t .= $pad('Massa ledig (kg)')       . $val('massa_ledig_voertuig')        . "\n";
    $t .= $pad('Max. massa (kg)')        . $val('toegestane_maximum_massa_voertuig') . "\n";
    $catalogus = (isset($v['catalogusprijs']) && $v['catalogusprijs'] !== '')
        ? 'EUR ' . number_format(intval($v['catalogusprijs']), 0, ',', '.')
        : '-';
    $t .= $pad('Catalogusprijs')         . $catalogus . "\n";

    if (!empty($fuels)) {
        foreach ($fuels as $fi => $fuel) {
            $prefix = count($fuels) > 1 ? 'Brandstof ' . ($fi + 1) : 'Brandstof';
            $t .= $pad($prefix) . (isset($fuel['brandstof_omschrijving']) ? $fuel['brandstof_omschrijving'] : '-') . "\n";
            if (!empty($fuel['emissiecode_omschrijving']))    $t .= $pad('Emissienorm')       . $fuel['emissiecode_omschrijving']    . "\n";
            if (!empty($fuel['nettomaximumvermogen']))        $t .= $pad('Max. vermogen (kW)') . $fuel['nettomaximumvermogen']       . "\n";
            if (!empty($fuel['uitstoot_co2']))                $t .= $pad('CO2 uitstoot (g/km)') . $fuel['uitstoot_co2']             . "\n";
            if (!empty($fuel['milieuklasse_eg_goedkeuring_licht'])) $t .= $pad('Milieuklasse') . $fuel['milieuklasse_eg_goedkeuring_licht'] . "\n";
        }
    }

    $t .= "=== Bron: opendata.rdw.nl ===\n";
    return $t;
}

// Suggest model, engine and transmission from RDW vehicle + fuel data
function contrib_suggest_car_data($v, $fuels) {
    $result = [
        'model'        => 'JA-80 (MKIV)',
        'engine'       => 'Unknown',
        'transmission' => '',
        'trans'        => 'M',
    ];

    // Build year from datum_eerste_toelating (YYYYMMDD)
    $bouwjaar = 0;
    if (!empty($v['datum_eerste_toelating'])) {
        $s = preg_replace('/[^0-9]/', '', $v['datum_eerste_toelating']);
        if (strlen($s) >= 4) {
            $bouwjaar = intval(substr($s, 0, 4));
        }
    }

    // Turbo detection: scan all relevant RDW text fields
    $rdw_text  = strtolower(
        (isset($v['handelsbenaming']) ? $v['handelsbenaming'] : '') . ' ' .
        (isset($v['inrichting'])      ? $v['inrichting']      : '') . ' ' .
        (isset($v['voertuigsoort'])   ? $v['voertuigsoort']   : '')
    );
    foreach ($fuels as $fuel) {
        $rdw_text .= ' ' . strtolower(isset($fuel['brandstof_omschrijving']) ? $fuel['brandstof_omschrijving'] : '');
    }
    $is_turbo = (strpos($rdw_text, 'turbo') !== false);
    // "automat" catches automaat / automatisch / automatic; avoids false-positive on "Personenauto"
    // "atm" catches RDW shorthand like "ATM-U9" used for automatic Supras
    $is_auto  = (strpos($rdw_text, 'automat') !== false || strpos($rdw_text, 'atm') !== false);

    // Engine displacement (cc)
    $cc = isset($v['cilinderinhoud']) ? intval($v['cilinderinhoud']) : 0;

    if ($bouwjaar > 0 && $bouwjaar < 1982) {
        // MKI (Celica Supra)
        $result['model']  = 'MA-46 (MKI)';
        $result['engine'] = '4M-E';
        if ($is_auto) {
            $result['transmission'] = 'A43DE (4 Speed Auto 5M)';
            $result['trans']        = 'A';
        } else {
            $result['transmission'] = 'W50 (5 Speed manual 4M)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 1982 && $bouwjaar <= 1985) {
        // MKII (Celica Supra)
        $result['model']  = 'MA-60 (MKII)';
        $result['engine'] = '5M-GE';
        if ($is_auto) {
            $result['transmission'] = 'A43DE (4 Speed Auto 5M)';
            $result['trans']        = 'A';
        } else {
            $result['transmission'] = 'W58 (5 speed manual 5M)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 1986 && $bouwjaar <= 1992) {
        // MKIII
        $result['model'] = 'MA-70 (MKIII)';
        if ($is_auto) {
            $result['engine']       = $is_turbo ? '7M-GTE' : '7M-GE';
            $result['transmission'] = 'A340E (4 Speed Auto 7M)';
            $result['trans']        = 'A';
        } elseif ($is_turbo) {
            $result['engine']       = '7M-GTE';
            $result['transmission'] = 'R154 (5 Speed manual 7M-GTE)';
            $result['trans']        = 'M';
        } else {
            $result['engine']       = '7M-GE';
            $result['transmission'] = 'W58 (5 speed manual 7M-GE)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 1993 && $bouwjaar <= 2004) {
        // MKIV
        $result['model'] = 'JA-80 (MKIV)';
        if ($is_auto) {
            $result['engine']       = $is_turbo ? '2JZ-GTE' : '2JZ-GE';
            $result['transmission'] = 'A342E (4 speed Auto 2JZ)';
            $result['trans']        = 'A';
        } elseif ($is_turbo) {
            $result['engine']       = '2JZ-GTE';
            $result['transmission'] = 'V160 (6 speed manual 2JZ)';
            $result['trans']        = 'M';
        } else {
            $result['engine']       = '2JZ-GE';
            $result['transmission'] = 'W58 (5 speed manual 2JZ)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 2019) {
        // MKV (GR Supra) — always automatic
        $result['model']        = 'A-90 (MKV)';
        $result['engine']       = ($cc > 2800) ? 'BMW-B58' : 'BMW-B48';
        $result['transmission'] = 'ZF 8HP (8 speed Auto MK5)';
        $result['trans']        = 'A';
    }
    // Years 2005–2018: no Supra produced; default (MKIV) already set above

    return $result;
}

// Process file uploads (reuses uploadimage.php logic)
function contrib_process_uploads($stripLicense, $mylocation) {
    include 'image_helper.php';
    $allowed_extensions = array('jpg', 'jpeg', 'gif', 'png');
    $allowed_mime_types = array('image/jpeg', 'image/jpg', 'image/gif', 'image/png');
    $max_file_size      = 20971520; // 20 MB raw input limit (server compresses to ≤1.5 MB)

    $files      = $_FILES["file"];
    $file_count = is_array($files["name"]) ? count($files["name"]) : 1;

    if (!is_array($files["name"])) {
        $files["name"]     = [$files["name"]];
        $files["type"]     = [$files["type"]];
        $files["tmp_name"] = [$files["tmp_name"]];
        $files["error"]    = [$files["error"]];
        $files["size"]     = [$files["size"]];
    }

    $success_count = 0;
    $error_count   = 0;
    $saved_files   = [];

    echo "<hr><strong>Resultaten ({$file_count} bestand(en)):</strong><br><br>";

    for ($i = 0; $i < $file_count; $i++) {
        $filename = $files["name"][$i];
        $tmp      = $files["tmp_name"][$i];
        $error    = $files["error"][$i];
        $size     = $files["size"][$i];

        echo "<strong>" . htmlspecialchars($filename) . ":</strong> ";

        if ($error > 0) {
            echo "<span style='color:red;'>Upload fout (code " . intval($error) . ")</span><br>";
            $error_count++;
            continue;
        }

        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_mime      = mime_content_type($tmp);

        if (!in_array($file_extension, $allowed_extensions)) {
            echo "<span style='color:red;'>Ongeldig bestandstype (alleen JPG, GIF, PNG)</span><br>";
            $error_count++;
        } elseif (!in_array($file_mime, $allowed_mime_types)) {
            echo "<span style='color:red;'>Ongeldig MIME type gedetecteerd</span><br>";
            $error_count++;
        } elseif ($size > $max_file_size) {
            echo "<span style='color:red;'>Bestand te groot (max " . ($max_file_size / 1024 / 1024) . " MB)</span><br>";
            $error_count++;
        } else {
            $base          = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
            $safe_filename = $base . '.jpg'; // always save as JPEG
            $target_file   = $mylocation . $safe_filename;

            if (file_exists($target_file)) {
                echo "<span style='color:orange;'>Bestaat al, overgeslagen.</span><br>";
                $error_count++;
            } elseif (snldb_save_image($tmp, $target_file)) {
                chmod($target_file, 0644);
                $saved_kb = number_format(filesize($target_file) / 1024, 1);
                echo "<span style='color:green;'>&#10003; Opgeslagen ({$saved_kb} KB)</span><br>";
                $success_count++;
                $saved_files[] = $safe_filename;
            } else {
                echo "<span style='color:red;'>Opslaan mislukt (controleer of GD beschikbaar is)</span><br>";
                $error_count++;
            }
        }
    }

    echo "<br><strong>Klaar: {$success_count} succesvol, {$error_count} mislukt.</strong><hr>";
    return $saved_files;
}

// Show upload form for a given (sanitized) license plate
function contrib_show_upload_form($stripLicense, $message = '') {
    if ($message) echo "<p style='color:green;font-weight:bold;'>" . htmlspecialchars($message) . "</p>";
    ?>
    <p>Upload foto's voor <strong><?php echo htmlspecialchars($stripLicense); ?></strong>.<br>
    Vul je naam in — zo weten we wie de foto's heeft bijgedragen.</p>
    <form action="index.php?navigate=contribute" method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="upload" />
        <input type="hidden" name="kenteken" value="<?php echo htmlspecialchars($stripLicense); ?>" />
        <?php contrib_csrf_field(); ?>
        <table style="border-collapse:collapse;">
            <tr>
                <td style="padding:4px 8px;font-weight:bold;">Naam (verplicht):</td>
                <td style="padding:4px 8px;"><input type="text" name="contributor_name" required maxlength="80" style="width:220px;" /></td>
            </tr>
            <tr>
                <td style="padding:4px 8px;font-weight:bold;">Email (optioneel):</td>
                <td style="padding:4px 8px;"><input type="email" name="contributor_email" maxlength="120" style="width:220px;" /></td>
            </tr>
            <tr>
                <td style="padding:4px 8px;font-weight:bold;">Foto's:</td>
                <td style="padding:4px 8px;">
                    <input type="file" name="file[]" id="file" accept="image/jpeg,image/jpg,image/gif,image/png" multiple required /><br>
                    <small style="color:#666;">Houd Ctrl (of Cmd op Mac) ingedrukt voor meerdere bestanden. Max 20 MB per foto &mdash; wordt automatisch verkleind naar max 1920&times;1280 en opgeslagen als JPEG.</small>
                </td>
            </tr>
        </table>
        <br>
        <?php contrib_honeypot_field(); ?>
        <input type="submit" value="Uploaden!" class="btn" style="padding:6px 16px;" />
    </form>
    <?php
}

// Render dropdown option list with optional pre-selection
function contrib_options($options_map, $selected_value) {
    foreach ($options_map as $value => $label) {
        $sel = ($value === $selected_value) ? ' selected="selected"' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
}

// Render a 3-step progress indicator. $active = current step number (1-3).
// Optionally override step labels.
function contrib_step_indicator(int $active, array $labels = []) {
    $steps = !empty($labels) ? $labels : ['Kenteken', 'Bevestigen', "Foto's uploaden"];
    echo '<div style="display:flex;align-items:center;margin:0 0 22px;font-size:12px;">';
    foreach ($steps as $i => $label) {
        $n    = $i + 1;
        $done = $n < $active;
        $cur  = $n === $active;
        $cbg  = $done ? '#2a8a2a' : ($cur ? 'var(--color-accent)' : 'var(--color-nav-border)');
        $ctxt = ($done || $cur) ? '#fff' : 'var(--color-muted)';
        $ltxt = $cur  ? 'var(--color-text)' : ($done ? 'var(--color-muted)' : 'var(--color-muted)');
        $lw   = $cur  ? 'bold' : 'normal';
        echo "<div style='display:flex;align-items:center;gap:6px;'>";
        echo "<div style='width:22px;height:22px;border-radius:50%;background:{$cbg};color:{$ctxt};"
           . "display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:11px;flex-shrink:0;'>";
        echo $done ? '&#10003;' : $n;
        echo "</div>";
        echo "<span style='color:{$ltxt};font-weight:{$lw};white-space:nowrap;'>"
           . htmlspecialchars($label) . "</span>";
        echo "</div>";
        if ($i < count($steps) - 1) {
            echo "<div style='flex:1;height:1px;background:var(--color-nav-border);margin:0 8px;min-width:16px;'></div>";
        }
    }
    echo '</div>';
}

// Render a car summary card for the confirm step.
function contrib_confirm_card(array $car, string $stripLicense): void {
    $photo = '';
    $gal   = "./bolgallerycars/cars{$stripLicense}slides_bolGalleryStaticPage.html";
    if (file_exists($gal)) {
        $html = @file_get_contents($gal);
        if ($html && preg_match('/data-full="([^"]+)"/', $html, $m)) $photo = $m[1];
    }
    if (!$photo) {
        $imgs = glob("./cars/{$stripLicense}/slides/*.{jpg,jpeg,png,webp}", GLOB_BRACE);
        if ($imgs) $photo = $imgs[0];
    }
    ?>
    <div style="background:var(--color-surface);border:2px solid var(--color-content-border);
                border-radius:8px;padding:16px 20px;margin-bottom:20px;
                display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
        <?php if ($photo): ?>
        <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($stripLicense) ?>"
             style="width:160px;height:110px;object-fit:cover;border-radius:6px;
                    border:1px solid var(--color-content-border);flex-shrink:0;" />
        <?php else: ?>
        <div style="width:160px;height:110px;border-radius:6px;flex-shrink:0;
                    background:var(--color-nav-hover-bg);border:1px solid var(--color-content-border);
                    display:flex;align-items:center;justify-content:center;
                    font-size:11px;color:var(--color-muted);">Geen foto beschikbaar</div>
        <?php endif; ?>
        <div style="flex:1;min-width:140px;">
            <div style="font-size:24px;font-weight:bold;margin-bottom:4px;color:var(--color-text);">
                <?= htmlspecialchars($car['License']) ?>
            </div>
            <div style="font-size:13px;color:var(--color-muted);margin-bottom:3px;">
                <?= htmlspecialchars($car['Choise_Model']) ?>
                <?php if (!empty($car['Choise_Engine'])): ?>
                &mdash; <?= htmlspecialchars($car['Choise_Engine']) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($car['Build_date'])): ?>
            <div style="font-size:12px;color:var(--color-muted);">
                Bouwjaar: <?= htmlspecialchars($car['Build_date']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// --- Main dispatcher ---

$step = isset($_POST['step']) ? $_POST['step'] : 'entry';

// On GET: check for fast-path upload mode (coming from search result page)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_GET['mode']) && $_GET['mode'] === 'upload' && isset($_GET['kenteken'])) {
        $step = 'quickupload';
    } else {
        $step = 'entry';
    }
}

?>
<div class="content-box">
<?php if ($step === 'quickupload'): ?>
    <h3>Foto's uploaden</h3>
<?php elseif ($step === 'entry'): ?>
    <h3>Supra spotten of bijwerken</h3>
    <p style="font-size:13px;color:var(--color-muted);margin-bottom:16px;">
        Voer het kenteken in. We kijken of de supra al in de database staat,<br>
        of voegen hem toe via de RDW — waarna je meteen foto's kunt uploaden.
    </p>
<?php else: ?>
    <h3>Supra bijdragen</h3>
<?php endif; ?>

<?php

// ============================================================
// STEP: entry — show license plate entry form
// ============================================================
if ($step === 'entry'):
    $prefill = isset($_GET['kenteken']) ? contrib_sanitize_plate(trim($_GET['kenteken'])) : '';
    contrib_step_indicator(1);
?>
    <form action="index.php?navigate=contribute" method="post" style="margin:0;">
        <input type="hidden" name="step" value="check" />
        <?php contrib_csrf_field(); ?>
        <label for="contrib_kenteken" style="font-size:13px;font-weight:bold;display:block;margin-bottom:8px;">
            Kenteken <span style="font-weight:normal;color:var(--color-muted);">(met streepjes, bijv. XX-XX-XX)</span>
        </label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" id="contrib_kenteken" name="kenteken"
                   placeholder="bijv. XX-XX-XX" maxlength="9" required
                   pattern="[A-Za-z0-9]{1,3}-[A-Za-z0-9]{1,3}-[A-Za-z0-9]{1,3}"
                   title="Voer het kenteken in met streepjes, bijv. 12-AB-34"
                   value="<?php echo htmlspecialchars($prefill); ?>"
                   autofocus
                   style="padding:8px 12px;font-size:16px;width:170px;letter-spacing:1px;
                          border:1px solid var(--color-content-border);border-radius:4px;
                          background:var(--color-input-bg);color:var(--color-text);" />
            <input type="submit" value="Zoeken →" class="btn"
                   style="padding:8px 18px;font-size:14px;" />
        </div>
    </form>

<?php

// ============================================================
// STEP: quickupload — direct photo upload, arrived from search result
// ============================================================
elseif ($step === 'quickupload'):
    $stripLicense = contrib_sanitize_plate(trim(isset($_GET['kenteken']) ? $_GET['kenteken'] : ''));
    if (!contrib_validate_plate_format($stripLicense)) {
        echo "<p style='color:red;'>Ongeldig kenteken. <a href='index.php?navigate=contribute'>&larr; Opnieuw</a></p>";
    } else {
        include 'connection.php';
        $stmt = $SNLDBConnection->prepare("SELECT License FROM SNLDB WHERE License = ?");
        $found = false;
        if ($stmt) {
            $stmt->bind_param("s", $stripLicense);
            $stmt->execute();
            $stmt->store_result();
            $found = ($stmt->num_rows > 0);
            $stmt->close();
        }
        mysqli_close($SNLDBConnection);
        if ($found) {
            contrib_show_upload_form($stripLicense);
        } else {
            echo "<p style='color:red;'>Supra <strong>" . htmlspecialchars($stripLicense) . "</strong> niet gevonden in de database.</p>";
            echo "<p><a href='index.php?navigate=contribute' class='btn'>&larr; Opnieuw</a></p>";
        }
    }

// ============================================================
// STEP: check — validate plate, check SNLDB then RDW
// ============================================================
elseif ($step === 'check'):

    if (!contrib_validate_csrf()) {
        echo "<p style='color:red;'>Ongeldige aanvraag (CSRF fout). Probeer opnieuw.</p>";
    } else {
        $raw_kenteken   = isset($_POST['kenteken']) ? trim($_POST['kenteken']) : '';
        $stripLicense   = contrib_sanitize_plate($raw_kenteken);

        if (!contrib_validate_plate_format($stripLicense)) {
            echo "<p style='color:red;'>Ongeldig kenteken formaat. Gebruik streepjes, bijv. <em>XX-XX-XX</em>.</p>";
            echo "<p><a href='index.php?navigate=contribute' class='btn'>&larr; Opnieuw proberen</a></p>";
        } else {
            // Check SNLDB — fetch full details so we can show a confirm card
            include 'connection.php';
            $found_car = null;
            $stmt = $SNLDBConnection->prepare(
                "SELECT License, Choise_Model, Choise_Engine, Build_date FROM SNLDB WHERE License = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("s", $stripLicense);
                $stmt->execute();
                $found_car = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                error_log("contribute.php: prepare failed: " . $SNLDBConnection->error);
            }
            mysqli_close($SNLDBConnection);
            $found_in_db = ($found_car !== null);

            if ($found_in_db) {
                // --- Found in SNLDB: show confirm card ---
                contrib_step_indicator(2);
                echo "<p style='color:green;font-weight:bold;margin-bottom:14px;'>&#10003; Supra gevonden in de database!</p>";
                contrib_confirm_card($found_car, $stripLicense);
                echo "<p style='font-size:13px;margin-bottom:16px;'>Is dit de juiste supra? Klik dan hieronder om foto's te uploaden.</p>";
                echo "<form method='post' action='index.php?navigate=contribute'>";
                echo "<input type='hidden' name='step' value='do_upload'>";
                echo "<input type='hidden' name='kenteken' value='" . htmlspecialchars($stripLicense) . "'>";
                contrib_csrf_field();
                echo "<input type='submit' value='&#10003; Ja, foto\\'s uploaden voor deze supra' class='btn' style='font-size:14px;padding:8px 20px;'>";
                echo "</form>";
                echo "<p style='font-size:12px;margin-top:12px;'><a href='index.php?navigate=contribute' style='color:var(--color-link);'>&#8592; Ander kenteken</a></p>";
            } else {
                // --- Not in SNLDB: check RDW ---
                echo "<p>Supra <strong>" . htmlspecialchars($stripLicense) . "</strong> staat nog niet in de database. RDW wordt gecontroleerd&hellip;</p>";

                $clean = preg_replace('/[^A-Z0-9]/', '', $stripLicense); // no dashes for RDW API
                $rdw   = contrib_fetch_rdw($clean);

                if ($rdw['error']) {
                    echo "<p style='color:red;'>RDW fout: " . $rdw['error'] . "</p>";
                    echo "<p><a href='index.php?navigate=contribute' class='btn'>&larr; Opnieuw proberen</a></p>";
                } elseif (!$rdw['vehicle']) {
                    echo "<p style='color:#888;'>Kenteken <strong>" . htmlspecialchars($stripLicense) . "</strong> niet gevonden bij de RDW. Kan niet worden toegevoegd aan de database.</p>";
                    echo "<p><a href='index.php?navigate=contribute' class='btn'>&larr; Opnieuw proberen</a></p>";
                } else {
                    // --- Found in RDW: show review/edit form ---
                    $v       = $rdw['vehicle'];
                    $fuels   = $rdw['fuels'];
                    $fuel0   = isset($fuels[0]) ? $fuels[0] : array();

                    // Pre-fill values from RDW
                    $pre_color  = isset($v['eerste_kleur'])          ? ucfirst(strtolower($v['eerste_kleur'])) : '';
                    $pre_bouwjaar = '';
                    if (isset($v['datum_eerste_toelating']) && strlen(preg_replace('/[^0-9]/', '', $v['datum_eerste_toelating'])) >= 4) {
                        $pre_bouwjaar = substr(preg_replace('/[^0-9]/', '', $v['datum_eerste_toelating']), 0, 4);
                    }
                    $pre_regdate     = contrib_rdw_date(isset($v['datum_eerste_toelating']) ? $v['datum_eerste_toelating'] : '');
                    $pre_handels      = isset($v['handelsbenaming']) ? $v['handelsbenaming'] : '';
                    $suggestions      = contrib_suggest_car_data($v, $fuels);
                    $pre_model        = $suggestions['model'];
                    $pre_engine       = $suggestions['engine'];
                    $pre_transmission = $suggestions['transmission'];
                    $pre_trans        = $suggestions['trans'];

                    // APK-based status pre-fill
                    $pre_status = 'Garage'; // default to Garage when APK status is unknown
                    $apk_raw_pre = isset($v['vervaldatum_apk']) ? $v['vervaldatum_apk'] : '';
                    if ($apk_raw_pre !== '') {
                        $apk_s = preg_replace('/[^0-9]/', '', $apk_raw_pre);
                        if (strlen($apk_s) === 8) {
                            $apk_expiry = mktime(0, 0, 0, intval(substr($apk_s, 4, 2)), intval(substr($apk_s, 6, 2)), intval(substr($apk_s, 0, 4)));
                            $pre_status = ($apk_expiry >= time()) ? 'Running' : 'Garage';
                        }
                    }

                    $marks = array(
                        'MA-46 (MKI)'  => 'Celica Supra MKI',
                        'MA-60 (MKII)' => 'Celica Supra MKII',
                        'MA-70 (MKIII)'=> 'Supra MKIII MA',
                        'JZA70'        => 'Supra MKIII JZA',
                        'JA-80 (MKIV)' => 'Supra MKIV',
                        'A-90 (MKV)'   => 'Supra MKV',
                    );
                    $engines = array(
                        '4M-E'         => '4M-E',
                        '5M-GE'        => '5M-GE',
                        '7M-GE'        => '7M-GE',
                        '7M-GTE'       => '7M-GTE',
                        '1JZ-GTE'      => '1JZ-GTE',
                        '1JZ-GTE-VVTI' => '1JZ-GTE VVT-I',
                        '1.5JZ-GTE'    => '1.5JZ-GTE',
                        '2JZ-GE'       => '2JZ-GE',
                        '2JZ-GTE'      => '2JZ-GTE',
                        '1G-GTE'       => '1G-GTE',
                        'BMW-B48'      => 'BMW-B48',
                        'BMW-B58'      => 'BMW-B58',
                        'Unknown'      => 'Unknown',
                    );
                    $transmissions = array(
                        'W50 (5 Speed manual 4M)'        => 'W50 (5 Speed manual 4M)',
                        'W58 (5 speed manual 5M)'        => 'W58 (5 speed manual 5M)',
                        'W58 (5 speed manual 7M-GE)'     => 'W58 (5 speed manual 7M-GE)',
                        'W58 (5 speed manual 2JZ)'       => 'W58 (5 speed manual 2JZ)',
                        'V160 (6 speed manual 2JZ)'      => 'V160 (6 speed manual 2JZ)',
                        'V161 (6 speed manual 2JZ)'      => 'V161 (6 speed manual 2JZ)',
                        'R154 (5 Speed manual 7M-GTE)'   => 'R154 (5 Speed manual 7M-GTE)',
                        'A43DE (4 Speed Auto 5M)'        => 'A43DE (4 Speed Auto 5M)',
                        'A340E (4 Speed Auto 7M)'        => 'A340E (4 Speed Auto 7M)',
                        'A342E (4 speed Auto 2JZ)'       => 'A342E (4 speed Auto 2JZ)',
                        'T56 (Upgrade kit for JZ)'       => 'T56 6-speed (Upgrade kit for 2JZ)',
                        'ZF 8HP (8 speed Auto MK5)'      => 'ZF 8HP (8 speed Auto MK5)',
                        'ZF S6-53 (6 speed manual MK5)'  => 'ZF S6-53 (6 speed manual MK5)',
                        'Other'                          => 'Other',
                    );
                    $statuses = array(
                        'Running'         => 'Rijdend',
                        'No Road License' => 'Geen kenteken',
                        'Wrecked'         => 'Wrecked',
                        'Garage'          => 'Garage',
                        'Forsale'         => 'For sale',
                        'Not Available'   => 'Not Available',
                    );
                    ?>
                    <?php contrib_step_indicator(2, ['Kenteken', 'Supra toevoegen', "Foto's uploaden"]); ?>
                    <div style="background:var(--color-surface);border:1px solid var(--color-content-border);
                                border-radius:6px;padding:10px 14px;margin-bottom:16px;
                                display:flex;align-items:center;gap:10px;">
                        <span style="color:#2a8a2a;font-size:18px;">&#10003;</span>
                        <div>
                            <strong style="font-size:14px;"><?php echo htmlspecialchars($stripLicense); ?></strong>
                            <span style="font-size:13px;color:var(--color-muted);margin-left:8px;">RDW bevestigd</span>
                            <?php if ($pre_handels): ?>
                            <span style="font-size:12px;color:var(--color-muted);margin-left:8px;">&mdash; <?php echo htmlspecialchars($pre_handels); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p style="font-size:13px;margin-bottom:14px;">Controleer de gegevens en voeg de supra toe. Daarna kun je direct foto's uploaden.</p>

                    <form action="index.php?navigate=contribute" method="post">
                        <input type="hidden" name="step" value="review" />
                        <input type="hidden" name="kenteken" value="<?php echo htmlspecialchars($stripLicense); ?>" />
                        <input type="hidden" name="bouwjaar" value="<?php echo htmlspecialchars($pre_bouwjaar); ?>" />
                        <input type="hidden" name="regdate"  value="<?php echo htmlspecialchars($pre_regdate); ?>" />
                        <input type="hidden" name="color"    value="<?php echo htmlspecialchars($pre_color); ?>" />
                        <?php contrib_csrf_field(); ?>

                        <table style="border-collapse:collapse;width:100%;max-width:500px;">
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;width:40%;font-size:13px;">Jouw naam:</td>
                            <td style="padding:5px 8px;"><input type="text" name="contributor_name" required maxlength="80"
                                style="padding:5px 8px;font-size:13px;width:220px;border:1px solid var(--color-content-border);
                                       border-radius:4px;background:var(--color-input-bg);color:var(--color-text);" /></td>
                        </tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Email <span style="font-weight:normal;">(optioneel)</span>:</td>
                            <td style="padding:5px 8px;"><input type="email" name="contributor_email" maxlength="120"
                                style="padding:5px 8px;font-size:13px;width:220px;border:1px solid var(--color-content-border);
                                       border-radius:4px;background:var(--color-input-bg);color:var(--color-text);" /></td>
                        </tr>
                        <tr><td colspan="2"><hr style="margin:8px 0;border:none;border-top:1px solid var(--color-nav-border);"></td></tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Type:</td>
                            <td style="padding:5px 8px;">
                                <select name="mark" style="padding:5px 8px;font-size:13px;border:1px solid var(--color-content-border);border-radius:4px;background:var(--color-input-bg);color:var(--color-text);">
                                    <?php contrib_options($marks, $pre_model); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Motortype:</td>
                            <td style="padding:5px 8px;">
                                <select name="engine" style="padding:5px 8px;font-size:13px;border:1px solid var(--color-content-border);border-radius:4px;background:var(--color-input-bg);color:var(--color-text);">
                                    <?php contrib_options($engines, $pre_engine); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Handbak / automaat:</td>
                            <td style="padding:5px 8px;font-size:13px;">
                                <input type="radio" name="trans" value="M" <?php echo $pre_trans === 'M' ? 'checked' : ''; ?> /> Handbak&nbsp;&nbsp;
                                <input type="radio" name="trans" value="A" <?php echo $pre_trans === 'A' ? 'checked' : ''; ?> /> Automaat
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Versnellingsbak:</td>
                            <td style="padding:5px 8px;">
                                <select name="transmission" style="padding:5px 8px;font-size:13px;border:1px solid var(--color-content-border);border-radius:4px;background:var(--color-input-bg);color:var(--color-text);">
                                    <?php contrib_options($transmissions, $pre_transmission); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:5px 8px;font-weight:bold;font-size:13px;">Status:</td>
                            <td style="padding:5px 8px;">
                                <select name="status" style="padding:5px 8px;font-size:13px;border:1px solid var(--color-content-border);border-radius:4px;background:var(--color-input-bg);color:var(--color-text);">
                                    <?php contrib_options($statuses, $pre_status); ?>
                                </select>
                            </td>
                        </tr>
                        </table>
                        <br>
                        <?php contrib_honeypot_field(); ?>
                        <input type="submit" value="Supra toevoegen →" class="btn"
                               style="padding:8px 20px;font-size:14px;" />
                        <a href="index.php?navigate=contribute"
                           style="margin-left:14px;font-size:12px;color:var(--color-link);">&#8592; Ander kenteken</a>
                    </form>
                    <?php
                }
            }
        }
    }

// ============================================================
// STEP: do_upload — show upload form after confirm card
// ============================================================
elseif ($step === 'do_upload'):

    if (!contrib_validate_csrf()) {
        echo "<p style='color:red;'>Ongeldige aanvraag (CSRF fout). Probeer opnieuw.</p>";
    } else {
        $stripLicense = contrib_sanitize_plate(trim($_POST['kenteken'] ?? ''));
        if (!contrib_validate_plate_format($stripLicense)) {
            echo "<p style='color:red;'>Ongeldig kenteken.</p>";
        } else {
            contrib_step_indicator(3);
            contrib_show_upload_form($stripLicense);
        }
    }

// ============================================================
// STEP: upload — process photo upload for existing car
// ============================================================
elseif ($step === 'upload'):

    if (!contrib_validate_csrf()) {
        echo "<p style='color:red;'>Ongeldige aanvraag (CSRF fout). Probeer opnieuw.</p>";
    } elseif (contrib_honeypot_triggered()) {
        echo "<p style='color:red;'>Ongeldige aanvraag.</p>";
    } else {
        $raw_kenteken      = isset($_POST['kenteken']) ? trim($_POST['kenteken']) : '';
        $stripLicense      = contrib_sanitize_plate($raw_kenteken);
        $contributor_name  = isset($_POST['contributor_name']) ? trim($_POST['contributor_name']) : '';
        $contributor_email = isset($_POST['contributor_email']) ? trim($_POST['contributor_email']) : '';

        if (!contrib_validate_plate_format($stripLicense)) {
            echo "<p style='color:red;'>Ongeldig kenteken.</p>";
        } elseif (empty($contributor_name)) {
            echo "<p style='color:red;'>Vul je naam in om foto's te kunnen uploaden.</p>";
            contrib_show_upload_form($stripLicense);
        } elseif (!isset($_FILES["file"]) || empty($_FILES["file"]["name"][0])) {
            echo "<p style='color:orange;'>Geen bestanden geselecteerd.</p>";
            contrib_show_upload_form($stripLicense);
        } else {
            // Re-verify license exists in SNLDB
            include 'connection.php';
            $stmt = $SNLDBConnection->prepare("SELECT License FROM SNLDB WHERE License = ?");
            $found_in_db = false;
            if ($stmt) {
                $stmt->bind_param("s", $stripLicense);
                $stmt->execute();
                $stmt->store_result();
                $found_in_db = ($stmt->num_rows > 0);
                $stmt->close();
            }
            mysqli_close($SNLDBConnection);

            if (!$found_in_db) {
                echo "<p style='color:red;'>Supra <strong>" . htmlspecialchars($stripLicense) . "</strong> niet gevonden in de database. Upload geannuleerd.</p>";
            } else {
                $mylocation = './cars/' . $stripLicense . '/slides/';
                if (!is_dir($mylocation)) {
                    // Create missing folder (shouldn't happen but be safe)
                    mkdir($mylocation, 0755, true);
                }
                echo "<p>Foto's uploaden voor <strong>" . htmlspecialchars($stripLicense) . "</strong> door <em>" . htmlspecialchars($contributor_name) . "</em>:</p>";
                $saved_files = contrib_process_uploads($stripLicense, $mylocation);
                $uploaded    = count($saved_files);

                if ($uploaded > 0) {
                    // Append contribution note to History in the database
                    $date_now   = date("Y-m-d H:i:s");
                    $email_note = $contributor_email ? ' (' . $contributor_email . ')' : '';
                    $note       = "\n\nFoto bijdrage door: {$contributor_name}{$email_note} op {$date_now}. {$uploaded} foto('s) toegevoegd.";

                    include 'connection.php';
                    include 'stats_helper.php';
                    include 'photo_recent_helper.php';
                    $upd = $SNLDBConnection->prepare("UPDATE SNLDB SET History = CONCAT(COALESCE(History, ''), ?), moddate = NOW() WHERE License = ?");
                    if ($upd) {
                        $upd->bind_param("ss", $note, $stripLicense);
                        $upd->execute();
                        $upd->close();
                    }
                    stats_day($SNLDBConnection, 'images_added', $uploaded);
                    foreach ($saved_files as $fn) {
                        photo_recent_add($SNLDBConnection, $stripLicense, $fn);
                    }
                    mysqli_close($SNLDBConnection);
                }

                contrib_step_indicator(3);
                echo "<p style='color:green;font-weight:bold;'>&#10003; Bedankt voor je bijdrage, " . htmlspecialchars($contributor_name) . "!</p>";
                contrib_show_upload_form($stripLicense, '');
            }
        }
    }

// ============================================================
// STEP: review — insert new car from RDW data, then show upload
// ============================================================
elseif ($step === 'review'):

    if (!contrib_validate_csrf()) {
        echo "<p style='color:red;'>Ongeldige aanvraag (CSRF fout). Probeer opnieuw.</p>";
    } elseif (contrib_honeypot_triggered()) {
        echo "<p style='color:red;'>Ongeldige aanvraag.</p>";
    } else {
        $raw_kenteken      = isset($_POST['kenteken']) ? trim($_POST['kenteken']) : '';
        $stripLicense      = contrib_sanitize_plate($raw_kenteken);
        $contributor_name  = isset($_POST['contributor_name']) ? trim($_POST['contributor_name']) : '';
        $contributor_email = isset($_POST['contributor_email']) ? trim($_POST['contributor_email']) : '';

        if (!contrib_validate_plate_format($stripLicense)) {
            echo "<p style='color:red;'>Ongeldig kenteken formaat.</p>";
        } elseif (empty($contributor_name)) {
            echo "<p style='color:red;'>Vul je naam in om een supra toe te kunnen voegen.</p>";
            echo "<p><a href='index.php?navigate=contribute' class='btn'>&larr; Opnieuw</a></p>";
        } else {
            include 'connection.php';

            // Duplicate check
            $stmt_chk = $SNLDBConnection->prepare("SELECT License FROM SNLDB WHERE License = ?");
            $already_exists = false;
            if ($stmt_chk) {
                $stmt_chk->bind_param("s", $stripLicense);
                $stmt_chk->execute();
                $stmt_chk->store_result();
                $already_exists = ($stmt_chk->num_rows > 0);
                $stmt_chk->close();
            }

            if ($already_exists) {
                echo "<p style='color:orange;'>Supra <strong>" . htmlspecialchars($stripLicense) . "</strong> is inmiddels al toegevoegd aan de database.</p>";
                contrib_show_upload_form($stripLicense);
            } else {
                // Collect and sanitize all form fields
                $date     = date("Y-m-d H:i:s");
                $notavail = "No data";

                $mark         = isset($_POST['mark'])         ? $_POST['mark']         : 'JA-80 (MKIV)';
                $engine       = isset($_POST['engine'])       ? $_POST['engine']       : 'Unknown';
                $transmission = isset($_POST['transmission']) ? $_POST['transmission'] : 'Other';
                $bouwjaar     = isset($_POST['bouwjaar'])     ? trim($_POST['bouwjaar']) : '';
                $regdate      = isset($_POST['regdate'])      ? trim($_POST['regdate'])  : '';
                $milage       = isset($_POST['milage'])       ? trim($_POST['milage'])   : '';
                $status       = isset($_POST['status'])       ? $_POST['status']       : 'Running';
                $color        = isset($_POST['color'])        ? trim($_POST['color'])    : '';
                $trans        = isset($_POST['trans'])        ? $_POST['trans']        : 'M';
                $mods         = isset($_POST['mods'])         ? htmlspecialchars(trim($_POST['mods']), ENT_QUOTES)    : 'Geen bekende modificaties.';
                $history_user = isset($_POST['history'])      ? htmlspecialchars(trim($_POST['history']), ENT_QUOTES) : '';

                // Re-fetch RDW data for the archive dump in History
                $clean_for_rdw = preg_replace('/[^A-Z0-9]/', '', $stripLicense);
                $rdw_archive   = contrib_fetch_rdw($clean_for_rdw);
                $rdw_dump      = '';
                if (!$rdw_archive['error'] && $rdw_archive['vehicle']) {
                    $rdw_dump = "\n\n" . contrib_rdw_archive_text($rdw_archive['vehicle'], $rdw_archive['fuels']);
                }

                // Build history field including contributor credit and full RDW archive
                $email_part  = $contributor_email ? ' (' . htmlspecialchars($contributor_email, ENT_QUOTES) . ')' : '';
                $history_auto = htmlspecialchars(
                    "Toegevoegd via contribute pagina door: {$contributor_name}{$email_part} op {$date}. Bron: RDW open data.",
                    ENT_QUOTES
                );
                $History = $history_auto . $rdw_dump . ($history_user ? "\n\n" . $history_user : '');

                $stmt = $SNLDBConnection->prepare(
                    "INSERT INTO SNLDB (License, Owner_display, Choise_Model, Choise_Engine, " .
                    "Choise_Transmission, Build_date, Registration_date, Milage, Choise_Status, " .
                    "VIN_Number, VIN_Modelcode, VIN_Colorcode, MA, Mods, History, RECNO, moddate) " .
                    "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)"
                );

                if (!$stmt) {
                    error_log("contribute.php insert prepare failed: " . $SNLDBConnection->error);
                    echo "<p style='color:red;'>Database fout. Probeer het later opnieuw.</p>";
                    mysqli_close($SNLDBConnection);
                } else {
                    $owner_display = "Onbekend";
                    $stmt->bind_param(
                        "ssssssssssssssss",
                        $stripLicense, $owner_display, $mark, $engine, $transmission,
                        $bouwjaar, $regdate, $milage, $status,
                        $notavail, $notavail, $color, $trans, $mods, $History, $date
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        include 'stats_helper.php';
                        include_once 'car_stats_helper.php';
                        stats_day($SNLDBConnection, 'supras_added');
                        car_changelog_log($SNLDBConnection, $stripLicense, 'new');
                        mysqli_close($SNLDBConnection);

                        // Create folder structure
                        $MyStruct = './cars/' . $stripLicense . '/slides/';
                        if (!is_dir($MyStruct)) {
                            mkdir($MyStruct, 0755, true);
                        }

                        contrib_step_indicator(3, ['Kenteken', 'Supra toevoegen', "Foto's uploaden"]);
                        echo "<p style='color:green;font-weight:bold;'>&#10003; Supra <strong>" . htmlspecialchars($stripLicense) . "</strong> succesvol toegevoegd aan de database!</p>";
                        echo "<p style='font-size:13px;'>Bekijk de pagina: <a href='index.php?navigate=" . urlencode($stripLicense) . "' style='color:var(--color-link);'>" . htmlspecialchars($stripLicense) . "</a></p>";
                        contrib_show_upload_form($stripLicense, "Upload nu foto's van de supra:");
                    } else {
                        $stmt->close();
                        mysqli_close($SNLDBConnection);
                        echo "<p style='color:red;'>Fout bij opslaan in de database. Probeer het later opnieuw.</p>";
                    }
                }
            }
        }
    }

// ============================================================
// STEP: insert_upload — process upload after insert
// ============================================================
elseif ($step === 'insert_upload'):
    // Identical logic to 'upload' step — reuse by falling through
    if (!contrib_validate_csrf()) {
        echo "<p style='color:red;'>Ongeldige aanvraag (CSRF fout). Probeer opnieuw.</p>";
    } else {
        $raw_kenteken      = isset($_POST['kenteken']) ? trim($_POST['kenteken']) : '';
        $stripLicense      = contrib_sanitize_plate($raw_kenteken);
        $contributor_name  = isset($_POST['contributor_name']) ? trim($_POST['contributor_name']) : '';

        if (!contrib_validate_plate_format($stripLicense)) {
            echo "<p style='color:red;'>Ongeldig kenteken.</p>";
        } elseif (empty($contributor_name)) {
            echo "<p style='color:red;'>Vul je naam in om foto's te kunnen uploaden.</p>";
            contrib_show_upload_form($stripLicense);
        } elseif (!isset($_FILES["file"]) || empty($_FILES["file"]["name"][0])) {
            echo "<p style='color:orange;'>Geen bestanden geselecteerd.</p>";
            contrib_show_upload_form($stripLicense);
        } else {
            $mylocation = './cars/' . $stripLicense . '/slides/';
            if (!is_dir($mylocation)) {
                mkdir($mylocation, 0755, true);
            }
            echo "<p>Foto's uploaden voor <strong>" . htmlspecialchars($stripLicense) . "</strong> door <em>" . htmlspecialchars($contributor_name) . "</em>:</p>";
            $saved_files = contrib_process_uploads($stripLicense, $mylocation);
            if (count($saved_files) > 0) {
                include 'connection.php';
                include 'photo_recent_helper.php';
                foreach ($saved_files as $fn) {
                    photo_recent_add($SNLDBConnection, $stripLicense, $fn);
                }
                mysqli_close($SNLDBConnection);
            }
            contrib_step_indicator(3, ['Kenteken', 'Supra toevoegen', "Foto's uploaden"]);
            echo "<p style='color:green;font-weight:bold;'>&#10003; Bedankt voor je bijdrage!</p>";
            contrib_show_upload_form($stripLicense);
        }
    }

endif;
?>

</div>
