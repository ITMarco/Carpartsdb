<?php
if (!defined('SNLDB_ACCESS')) define('SNLDB_ACCESS', 1);

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo "<div class=\"content-box\"><div style='color:#c04040;'>Geen toegang. "
       . "<a href='index.php?navigate=editsupra2'>Log opnieuw in</a>.</div></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="content-box">
<h3>Wijzigingen opslaan</h3>

<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<p style='color:#c04040;'>Ongeldige aanvraag.</p>"
       . "<p><a href='index.php?navigate=editsupra2'>Terug</a></p>";
    echo "</div>";
    return;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    echo "<p style='color:#c04040;'>Beveiligingscontrole mislukt.</p>"
       . "<p><a href='index.php?navigate=editsupra2'>Terug</a></p>";
    echo "</div>";
    return;
}

include_once 'connection.php';

$License             = strtoupper(trim($_POST['License']            ?? ''));
$Owner_display       = trim($_POST['owner']                         ?? '');
$Owner_show          = isset($_POST['owner_show']) ? 1 : 0;
$Choise_Model        = $_POST['mark']                               ?? '';
$Choise_Transmission = $_POST['transmission']                       ?? '';
$Milage              = trim($_POST['milage']                        ?? '');
$Choise_Status       = $_POST['status']                             ?? '';
$Choise_Engine       = $_POST['engine']                             ?? '';
$Registration_date   = trim($_POST['regdate']                       ?? '');
$Build_date          = trim($_POST['bouwjaar']                      ?? '');
$History             = $_POST['history']                            ?? '';
$mods                = $_POST['mods']                               ?? '';
$ma                  = $_POST['trans']                              ?? '';
$color               = trim($_POST['color']                         ?? '');
$recordnr            = (int)($_POST['recno']                        ?? 0);
$history2            = trim($_POST['history2']                      ?? '');

// Verify the record belongs to the logged-in user
if ($License !== strtoupper($_SESSION['user_license'] ?? '')) {
    echo "<p style='color:#c04040;'>Geen toegang tot dit record.</p>";
    echo "</div>";
    return;
}

$MyHistory = $history2 !== ''
    ? $history2 . "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n" . $History
    : $History;

$date = date('Y-m-d H:i:s');

// Fetch current owner name + owner history (admin-only field, never submitted via form)
$Owner_history = '';
$cur = $SNLDBConnection->prepare("SELECT Owner_display, Owner_history FROM SNLDB WHERE RECNO = ? AND License = ?");
if ($cur) {
    $cur->bind_param('is', $recordnr, $License);
    $cur->execute();
    $cur_row = $cur->get_result()->fetch_assoc();
    $cur->close();
    if ($cur_row) {
        $Owner_history = $cur_row['Owner_history'] ?? '';
        if ($cur_row['Owner_display'] !== '' && $cur_row['Owner_display'] !== $Owner_display) {
            $entry = date('Y-m-d') . ': ' . $cur_row['Owner_display'];
            $Owner_history = $Owner_history !== '' ? $entry . "\n" . $Owner_history : $entry;
        }
    }
}

$stmt = $SNLDBConnection->prepare(
    "UPDATE SNLDB
     SET Owner_display = ?, Owner_show = ?, Owner_history = ?,
         Choise_Model = ?, Choise_Engine = ?, Choise_Transmission = ?,
         Build_date = ?, Registration_date = ?, Milage = ?, Choise_Status = ?,
         VIN_Colorcode = ?, MA = ?, Mods = ?, History = ?, moddate = ?
     WHERE RECNO = ? AND License = ?"
);

if (!$stmt) {
    echo "<p style='color:#c04040;'>Database fout: " . htmlspecialchars($SNLDBConnection->error) . "</p>";
    echo "</div>";
    return;
}

$stmt->bind_param(
    'sisssssssssssssis',
    $Owner_display, $Owner_show, $Owner_history,
    $Choise_Model, $Choise_Engine, $Choise_Transmission,
    $Build_date, $Registration_date, $Milage, $Choise_Status,
    $color, $ma, $mods, $MyHistory, $date,
    $recordnr, $License
);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    ?>
    <div style="background:var(--color-input-bg);border:1px solid var(--color-content-border);
                border-radius:6px;padding:16px 20px;margin-bottom:16px;">
        <div style="color:#2a8a2a;font-size:15px;font-weight:bold;margin-bottom:8px;">✓ Wijzigingen opgeslagen!</div>
        <div style="font-size:13px;">Kenteken: <strong><?= htmlspecialchars($License) ?></strong></div>
    </div>
    <p style="font-size:13px;">
        <a href="index.php?navigate=<?= urlencode($License) ?>" style="color:var(--color-link);">Bekijk auto</a>
        &nbsp;·&nbsp;
        <a href="index.php?navigate=editsupra2" style="color:var(--color-link);">Terug naar bewerken</a>
    </p>
    <?php
} else {
    echo "<p style='color:#c04040;'>Fout bij opslaan: " . htmlspecialchars($stmt->error) . "</p>"
       . "<p><a href='index.php?navigate=editsupra2'>Terug</a></p>";
}

$stmt->close();
?>
</div>
