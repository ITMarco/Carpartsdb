<?php
// rdwupdate.php — Admin: update existing DB Supras based on RDW data changes.
// Compares APK date/status per DB record against last known value in History field,
// flags changed records for review in an editable grid, then applies updates.

require_once(__DIR__ . '/../session_manager.php');

if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div class='content-box'><h3>RDW Update Check</h3>";
    echo "<p style='color:red;'>Toegang geweigerd. <a href='index.php?navigate=secureadmin'>Log in als admin</a>.</p>";
    echo "</div>";
    return;
}

require_once __DIR__ . '/rdwu_functions.php';

// ─── Dropdown helper ─────────────────────────────────────────────────────────
function rdwu_select($name, $options, $selected, $style = '') {
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
// POST: apply updates
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    include 'connection.php';

    $recnos    = $_POST['recno']    ?? [];
    $licenses  = $_POST['license']  ?? [];
    $statuses  = $_POST['status']   ?? [];
    $histories = $_POST['history']  ?? [];
    $included  = $_POST['include']  ?? [];

    $results = [];
    $date    = date('Y-m-d H:i:s');

    foreach ($recnos as $i => $recno) {
        $recno   = intval($recno);
        $license = htmlspecialchars($licenses[$i] ?? '');

        if (!isset($included[$i])) {
            $results[] = ['license' => $license, 'status' => 'skip'];
            continue;
        }

        if ($recno <= 0) {
            $results[] = ['license' => $license, 'status' => 'error', 'msg' => 'Ongeldig RECNO'];
            continue;
        }

        $new_status  = $statuses[$i]  ?? '';
        $new_history = trim($histories[$i] ?? '');

        $stmt = $CarpartsConnection->prepare(
            "UPDATE SNLDB SET Choise_Status = ?, History = ?, moddate = ? WHERE RECNO = ?"
        );
        if (!$stmt) {
            $results[] = ['license' => $license, 'status' => 'error', 'msg' => 'DB prepare fout'];
            continue;
        }
        $stmt->bind_param('sssi', $new_status, $new_history, $date, $recno);
        if ($stmt->execute()) {
            $stmt->close();
            $results[] = ['license' => $license, 'status' => 'ok'];
        } else {
            $err_msg = $stmt->error;
            $stmt->close();
            $results[] = ['license' => $license, 'status' => 'error', 'msg' => $err_msg];
        }
    }

    mysqli_close($CarpartsConnection);

    $ok_count  = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
    $err_count = count(array_filter($results, fn($r) => $r['status'] === 'error'));
    ?>
    <div class="content-box">
        <h3>RDW Update — Resultaat</h3>
        <table style="border-collapse:collapse;width:100%;max-width:500px;font-size:12px;">
        <tr style="background:#3B495A;color:#fff;">
            <td style="padding:5px 10px;">Kenteken</td>
            <td style="padding:5px 10px;">Resultaat</td>
        </tr>
        <?php foreach ($results as $r):
            if ($r['status'] === 'skip') continue;
            $bg  = $r['status'] === 'ok' ? '#e8f5e9' : '#ffebee';
            $lbl = $r['status'] === 'ok'
                 ? '&#10003; Bijgewerkt'
                 : ('&#10007; Fout' . (isset($r['msg']) ? ': ' . htmlspecialchars($r['msg']) : ''));
        ?>
        <tr style="background:<?php echo $bg; ?>;">
            <td style="padding:4px 10px;border-bottom:1px solid #ddd;font-family:monospace;"><?php echo htmlspecialchars($r['license']); ?></td>
            <td style="padding:4px 10px;border-bottom:1px solid #ddd;"><?php echo $lbl; ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
        <p style="margin-top:12px;">
            Bijgewerkt: <strong><?php echo $ok_count; ?></strong> &nbsp;|&nbsp;
            Fouten: <strong><?php echo $err_count; ?></strong>
        </p>
        <p><a href="index.php?navigate=rdwupdate" class="btn">Nieuwe check uitvoeren</a></p>
    </div>
    <?php
    return;
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET: fetch RDW, compare with DB, detect changes, show editable grid
// ═══════════════════════════════════════════════════════════════════════════════

$batch_offset = max(0, intval($_GET['offset'] ?? 0));
set_time_limit(60); // paginated bulk fetch may take several seconds
?>
<div class="content-box">
<h3>RDW Update Check</h3>
<p style="color:#888;font-size:12px;">Bezig met ophalen van RDW gegevens&hellip; (kan enkele seconden duren)</p>
<?php
@ob_flush(); flush();

$rdw_result = rdwu_fetch_all_rdw();
if (isset($rdw_result['error'])) {
    echo '<p style="color:red;">RDW fout: ' . htmlspecialchars($rdw_result['error']) . '</p></div>';
    return;
}
$rdw_all = $rdw_result['data'];

// Build plate lookup keyed by normalized kenteken (no dashes, uppercase)
$rdw_by_plate = [];
foreach ($rdw_all as $v) {
    $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper($v['kenteken'] ?? ''));
    if ($raw !== '') $rdw_by_plate[$raw] = $v;
}

// Load all DB supras
include 'connection.php';
$db_cars = [];
$res = $CarpartsConnection->query(
    "SELECT RECNO, License, Choise_Status, History FROM SNLDB ORDER BY License"
);
if ($res) {
    while ($row = $res->fetch_assoc()) $db_cars[] = $row;
}
mysqli_close($CarpartsConnection);

// ─── Debug mode ───────────────────────────────────────────────────────────────
if (!empty($_GET['debug'])) {
    $rdw_plates_sorted = array_keys($rdw_by_plate);
    sort($rdw_plates_sorted);

    echo '<h4 style="margin:16px 0 6px;">Debug: RDW resultaten (' . count($rdw_all) . ' voertuigen)</h4>';
    echo '<p style="font-size:11px;color:#666;margin-bottom:6px;">Genormaliseerd (geen streepjes) — vergelijkingssleutel</p>';
    echo '<div style="font-family:monospace;font-size:11px;column-count:4;column-gap:12px;background:#f5f5f5;padding:10px;border:1px solid #ccc;max-height:300px;overflow:auto;">';
    foreach ($rdw_plates_sorted as $p) {
        $hb = htmlspecialchars($rdw_by_plate[$p]['handelsbenaming'] ?? '');
        echo htmlspecialchars($p) . ' <span style="color:#888;">(' . $hb . ')</span><br>';
    }
    echo '</div>';

    echo '<h4 style="margin:16px 0 6px;">Debug: Database vs RDW vergelijking (' . count($db_cars) . ' DB records)</h4>';
    echo '<table style="border-collapse:collapse;width:100%;font-size:11px;font-family:monospace;">';
    echo '<tr style="background:#3B495A;color:#fff;"><td style="padding:4px 8px;">DB Kenteken</td><td style="padding:4px 8px;">Genormaliseerd</td><td style="padding:4px 8px;">Status DB</td><td style="padding:4px 8px;">Gevonden in RDW?</td><td style="padding:4px 8px;">RDW Handelsbenaming</td></tr>';
    $alt = false;
    foreach ($db_cars as $car) {
        $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper($car['License']));
        $in_rdw = isset($rdw_by_plate[$raw]);
        $bg = $alt ? '#f9f9f9' : '#fff';
        if (!$in_rdw) $bg = '#ffecec';
        $alt = !$alt;
        $rdw_hb = $in_rdw ? htmlspecialchars($rdw_by_plate[$raw]['handelsbenaming'] ?? '?') : '—';
        $found_lbl = $in_rdw ? '<span style="color:green;">✓ ja</span>' : '<span style="color:red;">✗ nee</span>';
        echo '<tr style="background:' . $bg . ';">'
           . '<td style="padding:3px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($car['License']) . '</td>'
           . '<td style="padding:3px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($raw) . '</td>'
           . '<td style="padding:3px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($car['Choise_Status']) . '</td>'
           . '<td style="padding:3px 8px;border-bottom:1px solid #eee;">' . $found_lbl . '</td>'
           . '<td style="padding:3px 8px;border-bottom:1px solid #eee;">' . $rdw_hb . '</td>'
           . '</tr>';
    }
    echo '</table>';
    echo '<p style="margin-top:12px;"><a href="index.php?navigate=rdwupdate" class="btn">Terug naar normale check</a></p>';
    echo '</div>';
    return;
}

// ─── Detect changes ───────────────────────────────────────────────────────────
$changed = rdwu_detect_changes($db_cars, $rdw_by_plate);

$total_changed = count($changed);
$batch         = array_slice($changed, $batch_offset, 50);
$batch_count   = count($batch);

echo '<p style="margin:8px 0;">'
   . '<strong>DB records gecontroleerd:</strong> ' . count($db_cars) . ' &nbsp;|&nbsp;'
   . '<strong>RDW SUPRA resultaten:</strong> ' . count($rdw_all) . ' &nbsp;|&nbsp;'
   . '<strong>Wijzigingen gevonden:</strong> ' . $total_changed
   . ' &nbsp;&nbsp;<a href="index.php?navigate=rdwupdate&debug=1" class="btn" style="font-size:11px;padding:2px 8px;">Debug vergelijking</a>'
   . '</p>';

if ($batch_count === 0) {
    echo '<p style="color:green;margin-top:10px;"><strong>Geen wijzigingen gevonden — alles is up-to-date!</strong></p>';
    echo '</div>';
    return;
}

$showing_from = $batch_offset + 1;
$showing_to   = $batch_offset + $batch_count;
echo "<p style='font-size:12px;color:#666;margin:4px 0 10px;'>Toont rij {$showing_from}&ndash;{$showing_to} van {$total_changed} wijzigingen.</p>";

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
#rdwu-table { border-collapse:collapse; width:100%; font-size:11px; }
#rdwu-table th { background:#3B495A; color:#fff; padding:5px 6px; text-align:left; white-space:nowrap; position:sticky; top:0; }
#rdwu-table td { padding:3px 5px; border-bottom:1px solid #ddd; vertical-align:top; }
#rdwu-table tr:nth-child(even) td { background:#f7f7f7; }
#rdwu-table select   { font-size:11px; width:100%; box-sizing:border-box; }
#rdwu-table textarea { width:100%; font-size:10px; height:110px; box-sizing:border-box; font-family:monospace; }
</style>

<form method="post" action="index.php?navigate=rdwupdate">
<?php csrf_token_field(); ?>
<div style="overflow-x:auto;">
<table id="rdwu-table">
<thead>
<tr>
    <th title="Selecteer voor update">&#10003;</th>
    <th>Kenteken</th>
    <th style="min-width:180px;">Reden</th>
    <th>Huidige status</th>
    <th style="min-width:120px;">Nieuwe status</th>
    <th style="min-width:320px;">Nieuwe history (bewerkbaar)</th>
</tr>
</thead>
<tbody>
<?php foreach ($batch as $i => $row): ?>
<tr>
    <td style="text-align:center;">
        <input type="checkbox" name="include[<?php echo $i; ?>]" value="1" checked />
        <input type="hidden" name="recno[<?php echo $i; ?>]"   value="<?php echo intval($row['recno']); ?>" />
        <input type="hidden" name="license[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($row['license']); ?>" />
    </td>
    <td style="white-space:nowrap;font-family:monospace;font-weight:bold;">
        <?php echo htmlspecialchars($row['license']); ?>
    </td>
    <td style="color:#555;font-size:11px;"><?php echo htmlspecialchars($row['reason']); ?></td>
    <td style="white-space:nowrap;"><?php echo htmlspecialchars($row['old_status']); ?></td>
    <td><?php rdwu_select("status[$i]", $status_opts, $row['new_status']); ?></td>
    <td><textarea name="history[<?php echo $i; ?>]"><?php echo htmlspecialchars($row['new_history']); ?></textarea></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div style="margin-top:12px;padding:6px 0;">
    <?php if ($batch_offset > 0): ?>
    <a href="index.php?navigate=rdwupdate&offset=<?php echo max(0, $batch_offset - 50); ?>"
       style="margin-right:16px;">&larr; Vorige 50</a>
    <?php endif; ?>
    <input type="submit" value="Pas geselecteerde updates toe (<?php echo $batch_count; ?>)" class="btn" />
    <?php if (($batch_offset + 50) < $total_changed): ?>
    &nbsp;&nbsp;<a href="index.php?navigate=rdwupdate&offset=<?php echo $batch_offset + 50; ?>">Sla over &amp; volgende 50 &rarr;</a>
    <?php endif; ?>
</div>

</form>
</div>
