<?php
// rdwimport.php — Admin: bulk-import Supras from RDW open data.
// Finds plates in RDW not yet in SNLDB, shows an editable grid (max 50), inserts selected rows.

require_once(__DIR__ . '/../session_manager.php');

if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div class='content-box'><h3>RDW Bulk Import</h3>";
    echo "<p style='color:red;'>Toegang geweigerd. <a href='index.php?navigate=secureadmin'>Log in als admin</a>.</p>";
    echo "</div>";
    return;
}

// ─── Dash-formatting ─────────────────────────────────────────────────────────
// Adds dashes to a raw 6-char Dutch kenteken based on its letter/digit pattern.
function rdwi_format_plate($raw) {
    $k = preg_replace('/[^A-Z0-9]/', '', strtoupper($raw));
    if (strlen($k) !== 6) return $k;

    $mask = '';
    for ($i = 0; $i < 6; $i++) {
        $mask .= ctype_alpha($k[$i]) ? 'L' : 'D';
    }

    // Each mask maps to [group1_len, group2_len, group3_len]
    $splits = [
        'LLDDDD' => [2,2,2], // XX-99-99
        'DDDDLL' => [2,2,2], // 99-99-XX
        'DDLLDD' => [2,2,2], // 99-XX-99
        'LLLLDD' => [2,2,2], // XX-XX-99
        'DDLLLL' => [2,2,2], // 99-XX-XX
        'LLDDLL' => [2,2,2], // XX-99-XX
        'DLLLDD' => [1,3,2], // 9-XXX-99
        'DLLDDD' => [1,2,3], // 9-XX-999
        'LLDDDL' => [2,3,1], // XX-999-X
        'LDDDLL' => [1,3,2], // X-999-XX
        'LDDLLL' => [1,2,3], // X-99-XXX
        'LLLDLL' => [3,1,2], // XXX-9-XX
        'LLLDDL' => [3,2,1], // XXX-99-X
        'DDLLLD' => [2,3,1], // 99-XXX-9
        'DDDLLD' => [3,2,1], // 999-XX-9
        'DLLDDL' => [1,2,3], // 9-XX-999 (alt)
        'DDDLLL' => [3,1,2], // 999-X-XX (alt)
        'DDLDLL' => [2,1,3], // 99-X-XXX (alt)
    ];

    if (isset($splits[$mask])) {
        [$a, $b, $c] = $splits[$mask];
        return substr($k, 0, $a) . '-' . substr($k, $a, $b) . '-' . substr($k, $a + $b, $c);
    }

    // Fallback: 2-2-2
    return substr($k, 0, 2) . '-' . substr($k, 2, 2) . '-' . substr($k, 4, 2);
}

// ─── Auto-suggest model/engine/transmission from RDW data ────────────────────
function rdwi_suggest($v) {
    $result = [
        'model'        => 'JA-80 (MKIV)',
        'engine'       => 'Unknown',
        'transmission' => 'A342E (4 speed Auto 2JZ)', // default to automatic when unknown
        'trans'        => 'A',                         // default to automatic when unknown
        'status'       => 'Running',
    ];

    $bouwjaar = 0;
    if (!empty($v['datum_eerste_toelating'])) {
        $s = preg_replace('/[^0-9]/', '', $v['datum_eerste_toelating']);
        if (strlen($s) >= 4) $bouwjaar = intval(substr($s, 0, 4));
    }

    // APK expired → Not Available
    $apk_raw = $v['vervaldatum_apk'] ?? '';
    if ($apk_raw !== '') {
        $s = preg_replace('/[^0-9]/', '', $apk_raw);
        if (strlen($s) === 8) {
            $exp = mktime(0, 0, 0, intval(substr($s,4,2)), intval(substr($s,6,2)), intval(substr($s,0,4)));
            if ($exp < time()) $result['status'] = 'Not Available';
        }
    }

    $text     = strtolower(($v['handelsbenaming'] ?? '') . ' ' . ($v['inrichting'] ?? '') . ' ' . ($v['voertuigsoort'] ?? ''));
    $is_turbo = strpos($text, 'turbo') !== false;
    // "aut." in handelsbenaming (e.g. "TURBO AUT."), "automat", "atm" shorthand
    $is_auto  = strpos($text, 'automat') !== false || strpos($text, 'atm') !== false || strpos($text, 'aut.') !== false;
    $cc       = intval($v['cilinderinhoud'] ?? 0);

    if ($bouwjaar > 0 && $bouwjaar < 1982) {
        $result['model']        = 'MA-46 (MKI)';
        $result['engine']       = '4M-E';
        $result['transmission'] = !$is_auto ? 'W50 (5 Speed manual 4M)'  : 'A43DE (4 Speed Auto 5M)';
        $result['trans']        = !$is_auto ? 'M' : 'A';
    } elseif ($bouwjaar >= 1982 && $bouwjaar <= 1985) {
        $result['model']        = 'MA-60 (MKII)';
        $result['engine']       = '5M-GE';
        $result['transmission'] = !$is_auto ? 'W58 (5 speed manual 5M)'  : 'A43DE (4 Speed Auto 5M)';
        $result['trans']        = !$is_auto ? 'M' : 'A';
    } elseif ($bouwjaar >= 1986 && $bouwjaar <= 1992) {
        $result['model'] = 'MA-70 (MKIII)';
        if ($is_auto || (!$is_turbo)) {
            // auto detected, or no turbo signal → assume automatic
            $result['engine']       = $is_turbo ? '7M-GTE' : '7M-GE';
            $result['transmission'] = 'A340E (4 Speed Auto 7M)';
            $result['trans']        = 'A';
        } else {
            // turbo + explicitly NOT auto → manual
            $result['engine']       = '7M-GTE';
            $result['transmission'] = 'R154 (5 Speed manual 7M-GTE)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 1993 && $bouwjaar <= 2004) {
        $result['model'] = 'JA-80 (MKIV)';
        if ($is_auto || (!$is_turbo)) {
            // auto detected, or no turbo signal → assume automatic
            $result['engine']       = $is_turbo ? '2JZ-GTE' : '2JZ-GE';
            $result['transmission'] = 'A342E (4 speed Auto 2JZ)';
            $result['trans']        = 'A';
        } else {
            // turbo + explicitly NOT auto → manual
            $result['engine']       = '2JZ-GTE';
            $result['transmission'] = 'V160 (6 speed manual 2JZ)';
            $result['trans']        = 'M';
        }
    } elseif ($bouwjaar >= 2019) {
        $result['model']        = 'A-90 (MKV)';
        $result['engine']       = ($cc > 2800) ? 'BMW-B58' : 'BMW-B48';
        $result['transmission'] = 'ZF 8HP (8 speed Auto MK5)';
        $result['trans']        = 'A';
    }

    return $result;
}

// ─── Build History text from RDW data ────────────────────────────────────────
function rdwi_history_text($v) {
    $pad  = function($label) { return str_pad($label . ':', 32); };
    $val  = function($key) use ($v) { return (isset($v[$key]) && $v[$key] !== '') ? $v[$key] : '-'; };
    $fmtD = function($raw) {
        if (empty($raw)) return '-';
        $s = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($s) === 8) return substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
        return $raw;
    };

    $apk_raw    = $v['vervaldatum_apk'] ?? '';
    $apk_fmt    = '-';
    $apk_status = '';
    if ($apk_raw !== '') {
        $s = preg_replace('/[^0-9]/', '', $apk_raw);
        if (strlen($s) === 8) {
            $apk_fmt = substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
            $exp = mktime(0, 0, 0, intval(substr($s, 4, 2)), intval(substr($s, 6, 2)), intval(substr($s, 0, 4)));
            if ($exp < time()) $apk_status = ' (VERLOPEN)';
            elseif ($exp < time() + 60 * 86400) $apk_status = ' (verloopt binnenkort)';
            else $apk_status = ' (geldig)';
        }
    }

    $catalogus = (isset($v['catalogusprijs']) && $v['catalogusprijs'] !== '')
        ? 'EUR ' . number_format(intval($v['catalogusprijs']), 0, ',', '.')
        : '-';

    $t  = "=== RDW Gegevens (bulk import, opgehaald " . date('d-m-Y') . ") ===\n";
    $t .= $pad('Kenteken')                 . $val('kenteken')                                    . "\n";
    $t .= $pad('Merk')                     . $val('merk')                                        . "\n";
    $t .= $pad('Handelsbenaming')          . $val('handelsbenaming')                             . "\n";
    $t .= $pad('Voertuigsoort')            . $val('voertuigsoort')                               . "\n";
    $t .= $pad('Inrichting')               . $val('inrichting')                                  . "\n";
    $t .= $pad('Eerste kleur')             . $val('eerste_kleur')                                . "\n";
    $t .= $pad('Tweede kleur')             . $val('tweede_kleur')                                . "\n";
    $t .= $pad('Eerste toelating')         . $fmtD($val('datum_eerste_toelating'))               . "\n";
    $t .= $pad('Eerste tenaamstelling NL') . $fmtD($val('datum_eerste_tenaamstelling_in_nederland')) . "\n";
    $t .= $pad('Laatste tenaamstelling')   . $fmtD($val('datum_tenaamstelling'))                 . "\n";
    $t .= $pad('Vervaldatum APK')          . $apk_fmt . $apk_status                             . "\n";
    $t .= $pad('WAM verzekerd')            . $val('wam_verzekerd')                               . "\n";
    $t .= $pad('Aantal cilinders')         . $val('aantal_cilinders')                            . "\n";
    $t .= $pad('Cilinderinhoud (cc)')      . $val('cilinderinhoud')                              . "\n";
    $t .= $pad('Massa ledig (kg)')         . $val('massa_ledig_voertuig')                        . "\n";
    $t .= $pad('Max. massa (kg)')          . $val('toegestane_maximum_massa_voertuig')            . "\n";
    $t .= $pad('Catalogusprijs')           . $catalogus                                          . "\n";
    $t .= "=== Bron: opendata.rdw.nl ===\n";
    return $t;
}

// ─── Fetch all matching Supras from RDW (paginated) ──────────────────────────
function rdwi_fetch_all_rdw() {
    $all    = [];
    $limit  = 1000;
    $offset = 0;

    do {
        $url = 'https://opendata.rdw.nl/resource/m9d7-ebf2.json'
             . '?$where=' . urlencode("merk='TOYOTA' AND upper(handelsbenaming) like '%SUPRA%'")
             . '&$limit='  . $limit
             . '&$offset=' . $offset;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SNLDB/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($err)          return ['error' => 'cURL fout: ' . $err];
        if ($code !== 200) return ['error' => "RDW API antwoordde met HTTP $code"];

        $batch = json_decode($resp, true);
        if (!is_array($batch)) return ['error' => 'Ongeldig JSON antwoord van RDW'];

        foreach ($batch as $row) $all[] = $row;
        $offset += $limit;
    } while (count($batch) === $limit);

    return ['data' => $all];
}

// ─── Dropdown helper ─────────────────────────────────────────────────────────
// $options: associative array of value => label
function rdwi_select($name, $options, $selected, $style = '') {
    $style_attr = $style ? ' style="' . htmlspecialchars($style) . '"' : '';
    echo "<select name=\"" . htmlspecialchars($name) . "\"{$style_attr}>";
    foreach ($options as $value => $label) {
        $sel = ($value === $selected) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'
           . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST: process insertions
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    include 'connection.php';
    include 'stats_helper.php';

    $licenses      = $_POST['license']      ?? [];
    $owners        = $_POST['owner']        ?? [];
    $models        = $_POST['model']        ?? [];
    $engines       = $_POST['engine']       ?? [];
    $transmissions = $_POST['transmission'] ?? [];
    $trans_arr     = $_POST['trans']        ?? [];
    $bouwjaren     = $_POST['bouwjaar']     ?? [];
    $regdates      = $_POST['regdate']      ?? [];
    $colors        = $_POST['color']        ?? [];
    $statuses      = $_POST['status']       ?? [];
    $histories     = $_POST['history']      ?? [];
    $included      = $_POST['include']      ?? [];
    $batch_offset  = intval($_POST['batch_offset'] ?? 0);

    $results = [];
    $date    = date('Y-m-d H:i:s');

    foreach ($licenses as $i => $raw_license) {
        $license = preg_replace('/[^A-Z0-9-]/', '', strtoupper(trim($raw_license)));

        if (!isset($included[$i])) {
            $results[] = ['license' => $license, 'status' => 'skip'];
            continue;
        }

        if (empty($license)) {
            $results[] = ['license' => '(leeg)', 'status' => 'error', 'msg' => 'Geen geldig kenteken'];
            continue;
        }

        $owner        = trim($owners[$i]        ?? 'Onbekend');
        $model        = $models[$i]             ?? 'JA-80 (MKIV)';
        $engine       = $engines[$i]            ?? 'Unknown';
        $transmission = $transmissions[$i]      ?? '';
        $trans        = ($trans_arr[$i] ?? 'M') === 'A' ? 'A' : 'M';
        $bouwjaar     = trim($bouwjaren[$i]     ?? '');
        $regdate      = trim($regdates[$i]      ?? '');
        $color        = trim($colors[$i]        ?? '');
        $status       = $statuses[$i]           ?? 'Running';
        $history      = trim($histories[$i]     ?? '');

        // Duplicate check — normalize both sides so dashes/spaces never cause a miss
        $license_stripped = preg_replace('/[^A-Z0-9]/', '', $license);
        $chk = $SNLDBConnection->prepare(
            "SELECT RECNO FROM SNLDB
             WHERE UPPER(REPLACE(REPLACE(License, '-', ''), ' ', '')) = ?
             LIMIT 1"
        );
        $chk->bind_param('s', $license_stripped);
        $chk->execute();
        $chk->store_result();
        $is_dup = ($chk->num_rows > 0);
        $chk->close();

        if ($is_dup) {
            $results[] = ['license' => $license, 'status' => 'dup'];
            continue;
        }

        // Insert — 12 bound params (s×12)
        // Milage='', VIN_Number='No data', VIN_Modelcode='No data', Mods='No known modifications.', RECNO=NULL are literals
        $stmt = $SNLDBConnection->prepare(
            "INSERT INTO SNLDB
             (License, Owner_display, Choise_Model, Choise_Engine, Choise_Transmission,
              Build_date, Registration_date, Milage, Choise_Status,
              VIN_Number, VIN_Modelcode, VIN_Colorcode, MA,
              Mods, History, RECNO, moddate)
             VALUES
             (?, ?, ?, ?, ?, ?, ?, '', ?,
              'No data', 'No data', ?, ?,
              'No known modifications.', ?, NULL, ?)"
        );

        if (!$stmt) {
            $results[] = ['license' => $license, 'status' => 'error', 'msg' => 'DB prepare fout: ' . $SNLDBConnection->error];
            continue;
        }

        $stmt->bind_param('ssssssssssss',
            $license, $owner, $model, $engine, $transmission,
            $bouwjaar, $regdate, $status,
            $color, $trans,
            $history, $date
        );

        if ($stmt->execute()) {
            $stmt->close();
            include_once 'car_stats_helper.php';
            stats_day($SNLDBConnection, 'supras_added');
            car_changelog_log($SNLDBConnection, $license, 'new');

            $folder = './cars/' . $license . '/slides/';
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
            $results[] = ['license' => $license, 'status' => 'ok'];
        } else {
            $err_msg = $stmt->error;
            $stmt->close();
            $results[] = ['license' => $license, 'status' => 'error', 'msg' => 'DB insert fout: ' . $err_msg];
        }
    }

    mysqli_close($SNLDBConnection);

    $ok_count  = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
    $dup_count = count(array_filter($results, fn($r) => $r['status'] === 'dup'));
    $err_count = count(array_filter($results, fn($r) => $r['status'] === 'error'));
    ?>
    <div class="content-box">
        <h3>RDW Bulk Import — Resultaat</h3>

        <table style="border-collapse:collapse;width:100%;max-width:500px;font-size:12px;">
        <tr style="background:#3B495A;color:#fff;">
            <td style="padding:5px 10px;">Kenteken</td>
            <td style="padding:5px 10px;">Resultaat</td>
        </tr>
        <?php foreach ($results as $r):
            if ($r['status'] === 'skip') continue;
            $bg  = $r['status'] === 'ok'    ? '#e8f5e9'
                 : ($r['status'] === 'dup'  ? '#fff8e1' : '#ffebee');
            $lbl = $r['status'] === 'ok'    ? '&#10003; Toegevoegd'
                 : ($r['status'] === 'dup'  ? '&#9888; Al aanwezig'
                 : ('&#10007; Fout' . (isset($r['msg']) ? ': ' . htmlspecialchars($r['msg']) : '')));
        ?>
        <tr style="background:<?php echo $bg; ?>;">
            <td style="padding:4px 10px;border-bottom:1px solid #ddd;font-family:monospace;"><?php echo htmlspecialchars($r['license']); ?></td>
            <td style="padding:4px 10px;border-bottom:1px solid #ddd;"><?php echo $lbl; ?></td>
        </tr>
        <?php endforeach; ?>
        </table>

        <p style="margin-top:12px;">
            Toegevoegd: <strong><?php echo $ok_count; ?></strong> &nbsp;|&nbsp;
            Al aanwezig: <strong><?php echo $dup_count; ?></strong> &nbsp;|&nbsp;
            Fouten: <strong><?php echo $err_count; ?></strong>
        </p>
        <p>
            <a href="index.php?navigate=rdwimport&offset=<?php echo $batch_offset + 50; ?>" class="btn" style="margin-right:12px;">Volgende batch &rarr;</a>
            <a href="index.php?navigate=rdwimport&offset=<?php echo $batch_offset; ?>">Huidige batch herladen</a>
        </p>
    </div>
    <?php
    return;
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET: fetch from RDW, compare with DB, show editable grid
// ═══════════════════════════════════════════════════════════════════════════════

$batch_offset = max(0, intval($_GET['offset'] ?? 0));
?>
<div class="content-box">
<h3>RDW Bulk Import</h3>
<p style="color:#888;font-size:12px;">Bezig met ophalen van RDW gegevens&hellip; (kan enkele seconden duren)</p>
<?php
@ob_flush(); flush();

$rdw_result = rdwi_fetch_all_rdw();
if (isset($rdw_result['error'])) {
    echo '<p style="color:red;">RDW fout: ' . htmlspecialchars($rdw_result['error']) . '</p></div>';
    return;
}
$rdw_all = $rdw_result['data'];

// Load all existing SNLDB licenses, normalized in SQL to remove dashes/spaces
include 'connection.php';
$existing = [];
$res = $SNLDBConnection->query(
    "SELECT UPPER(REPLACE(REPLACE(License, '-', ''), ' ', '')) AS k FROM SNLDB"
);
if ($res) {
    while ($row = $res->fetch_row()) {
        if ($row[0] !== '') $existing[$row[0]] = true;
    }
}

// Find plates in RDW but not in SNLDB (Toyota only)
$new_cars = [];
foreach ($rdw_all as $v) {
    if (strtoupper(trim($v['merk'] ?? '')) !== 'TOYOTA') continue;
    // Normalize the RDW kenteken the same way as the DB lookup above
    $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper($v['kenteken'] ?? ''));
    if ($raw === '' || isset($existing[$raw])) continue;
    $new_cars[] = $v;
}

// Sort by first registration date ascending (oldest first)
usort($new_cars, function($a, $b) {
    $da = preg_replace('/[^0-9]/', '', $a['datum_eerste_toelating'] ?? '0');
    $db = preg_replace('/[^0-9]/', '', $b['datum_eerste_toelating'] ?? '0');
    return strcmp($da, $db);
});

mysqli_close($SNLDBConnection);

$total_rdw = count($rdw_all);
$total_new = count($new_cars);

$batch       = array_slice($new_cars, $batch_offset, 50);
$batch_count = count($batch);

echo '<p style="margin:8px 0;">'
   . '<strong>RDW resultaten:</strong> ' . $total_rdw . ' &nbsp;|&nbsp;'
   . '<strong>Nieuw (niet in DB):</strong> ' . $total_new
   . '</p>';

if ($batch_count === 0) {
    echo '<p style="color:green;margin-top:10px;"><strong>Alle gevonden Supras staan al in de database!</strong></p>';
    echo '</div>';
    return;
}

$showing_from = $batch_offset + 1;
$showing_to   = $batch_offset + $batch_count;
echo "<p style='font-size:12px;color:#666;margin:4px 0 10px;'>Toont rij {$showing_from}&ndash;{$showing_to} van {$total_new} nieuwe.</p>";

// Dropdown option lists
$model_opts = [
    'MA-46 (MKI)'   => 'Celica Supra MKI',
    'MA-60 (MKII)'  => 'Celica Supra MKII',
    'MA-70 (MKIII)' => 'Supra MKIII MA',
    'JZA70'         => 'Supra MKIII JZA',
    'JA-80 (MKIV)'  => 'Supra MKIV',
    'A-90 (MKV)'    => 'Supra MKV',
];
$engine_opts = [
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
];
$transmission_opts = [
    'W50 (5 Speed manual 4M)'         => 'W50',
    'W58 (5 speed manual 5M)'         => 'W58 (5M)',
    'W58 (5 speed manual 7M-GE)'      => 'W58 (7M-GE)',
    'W58 (5 speed manual 2JZ)'        => 'W58 (2JZ)',
    'V160 (6 speed manual 2JZ)'       => 'V160',
    'V161 (6 speed manual 2JZ)'       => 'V161',
    'R154 (5 Speed manual 7M-GTE)'    => 'R154',
    'A43DE (4 Speed Auto 5M)'         => 'A43DE',
    'A340E (4 Speed Auto 7M)'         => 'A340E',
    'A342E (4 speed Auto 2JZ)'        => 'A342E',
    'T56 (Upgrade kit for JZ)'        => 'T56',
    'ZF 8HP (8 speed Auto MK5)'       => 'ZF 8HP',
    'ZF S6-53 (6 speed manual MK5)'   => 'ZF S6-53',
    'Other'                           => 'Other',
];
$status_opts = [
    'Running'         => 'Rijdend',
    'No Road License' => 'Geen kenteken',
    'Wrecked'         => 'Wrecked',
    'Garage'          => 'Garage',
    'Forsale'         => 'For sale',
    'Not Available'   => 'Not Available',
];
?>

<style>
#rdwi-table { border-collapse:collapse; width:100%; font-size:11px; }
#rdwi-table th { background:#3B495A; color:#fff; padding:5px 6px; text-align:left; white-space:nowrap; position:sticky; top:0; }
#rdwi-table td { padding:3px 5px; border-bottom:1px solid #ddd; vertical-align:top; }
#rdwi-table tr:nth-child(even) td { background:#f7f7f7; }
#rdwi-table input[type=text]  { width:100%; box-sizing:border-box; font-size:11px; padding:2px 4px; }
#rdwi-table select             { font-size:11px; width:100%; box-sizing:border-box; }
#rdwi-table textarea           { width:100%; font-size:10px; height:70px; box-sizing:border-box; font-family:monospace; }
</style>

<form method="post" action="index.php?navigate=rdwimport">
<?php csrf_token_field(); ?>
<input type="hidden" name="batch_offset" value="<?php echo $batch_offset; ?>" />

<div style="overflow-x:auto;">
<table id="rdwi-table">
<thead>
<tr>
    <th title="Selecteer voor import">&#10003;</th>
    <th>Kenteken</th>
    <th>Eigenaar</th>
    <th>Model</th>
    <th>Motor</th>
    <th>Bak</th>
    <th style="min-width:40px;">M/A</th>
    <th style="min-width:55px;">Bouwjaar</th>
    <th style="min-width:80px;">Reg.datum</th>
    <th style="min-width:70px;">Kleur</th>
    <th>Status</th>
    <th style="min-width:200px;">History (RDW data)</th>
</tr>
</thead>
<tbody>
<?php foreach ($batch as $i => $v):
    $raw_k   = preg_replace('/[^A-Z0-9]/', '', strtoupper($v['kenteken'] ?? ''));
    $license = rdwi_format_plate($raw_k);
    $suggest = rdwi_suggest($v);
    $history = rdwi_history_text($v);

    $bouwjaar = '';
    if (!empty($v['datum_eerste_toelating'])) {
        $s = preg_replace('/[^0-9]/', '', $v['datum_eerste_toelating']);
        if (strlen($s) >= 4) $bouwjaar = substr($s, 0, 4);
    }

    $regdate = '';
    $rdw_key = 'datum_eerste_tenaamstelling_in_nederland';
    if (!empty($v[$rdw_key])) {
        $s = preg_replace('/[^0-9]/', '', $v[$rdw_key]);
        if (strlen($s) === 8) $regdate = substr($s,6,2).'-'.substr($s,4,2).'-'.substr($s,0,4);
    }

    $color = ucfirst(strtolower($v['eerste_kleur'] ?? ''));
?>
<tr>
    <td style="text-align:center;">
        <input type="checkbox" name="include[<?php echo $i; ?>]" value="1" checked />
    </td>
    <td style="white-space:nowrap;font-family:monospace;font-weight:bold;">
        <input type="hidden" name="license[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($license); ?>" />
        <?php echo htmlspecialchars($license); ?>
    </td>
    <td><input type="text" name="owner[<?php echo $i; ?>]" value="Onbekend" /></td>
    <td><?php rdwi_select("model[$i]", $model_opts, $suggest['model']); ?></td>
    <td><?php rdwi_select("engine[$i]", $engine_opts, $suggest['engine']); ?></td>
    <td><?php rdwi_select("transmission[$i]", $transmission_opts, $suggest['transmission']); ?></td>
    <td style="white-space:nowrap;">
        <label><input type="radio" name="trans[<?php echo $i; ?>]" value="M"<?php echo $suggest['trans'] === 'M' ? ' checked' : ''; ?> /> M</label><br>
        <label><input type="radio" name="trans[<?php echo $i; ?>]" value="A"<?php echo $suggest['trans'] === 'A' ? ' checked' : ''; ?> /> A</label>
    </td>
    <td><input type="text" name="bouwjaar[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($bouwjaar); ?>" /></td>
    <td><input type="text" name="regdate[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($regdate); ?>" /></td>
    <td><input type="text" name="color[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($color); ?>" /></td>
    <td><?php rdwi_select("status[$i]", $status_opts, $suggest['status']); ?></td>
    <td><textarea name="history[<?php echo $i; ?>]"><?php echo htmlspecialchars($history); ?></textarea></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div style="margin-top:12px;padding:6px 0;">
    <?php if ($batch_offset > 0): ?>
    <a href="index.php?navigate=rdwimport&offset=<?php echo max(0, $batch_offset - 50); ?>"
       style="margin-right:16px;">&larr; Vorige 50</a>
    <?php endif; ?>
    <input type="submit" value="Importeer geselecteerde (<?php echo $batch_count; ?>)" class="btn" />
    <?php if (($batch_offset + 50) < $total_new): ?>
    &nbsp;&nbsp;<a href="index.php?navigate=rdwimport&offset=<?php echo $batch_offset + 50; ?>">Sla over &amp; volgende 50 &rarr;</a>
    <?php endif; ?>
</div>

</form>
</div>
