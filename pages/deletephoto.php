<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Geen toegang.</div>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div style='color:red;'>Ongeldige aanvraag.</div>";
    return;
}

require_once(__DIR__ . '/../session_manager.php');
if (!validate_csrf_token()) {
    echo "<div style='color:red;'>Ongeldige CSRF token.</div>";
    return;
}

$license  = strtoupper(preg_replace('/[^A-Z0-9-]/', '', trim($_POST['license'] ?? '')));
$filename = basename($_POST['filename'] ?? '');
$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
$ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($license === '' || $filename === '' || !in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
    echo "<div style='color:red;'>Ongeldige invoer.</div>";
    return;
}

$path = "./cars/$license/slides/$filename";

if (!file_exists($path) || !is_file($path)) {
    echo "<div style='color:red;'>Foto niet gevonden: " . htmlspecialchars($filename) . "</div>";
    return;
}

if (unlink($path)) {
    include 'connection.php';
    include_once 'car_stats_helper.php';
    $upd = $SNLDBConnection->prepare("UPDATE SNLDB SET moddate = NOW() WHERE License = ?");
    if ($upd) {
        $upd->bind_param("s", $license);
        $upd->execute();
        $upd->close();
    }
    car_changelog_log($SNLDBConnection, $license, 'photodel');
    mysqli_close($SNLDBConnection);
    echo "<div style='background:#d4edda;border:1px solid #28a745;padding:12px 16px;border-radius:4px;margin:10px 0;'>
            ✓ Foto <strong>" . htmlspecialchars($filename) . "</strong> verwijderd.
            <a href='index.php?navigate=" . urlencode($license) . "' style='margin-left:12px;'>← Terug naar supra</a>
          </div>";
} else {
    echo "<div style='color:red;'>Verwijderen mislukt. Controleer bestandsrechten.</div>";
}
?>
