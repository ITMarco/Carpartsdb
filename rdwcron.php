<?php
/**
 * rdwcron.php — Automated weekly RDW update endpoint.
 *
 * Triggered by an external scheduler (e.g. cron-job.org or GitHub Actions).
 * URL: https://yoursite/rdwcron.php?token=<secret>
 *
 * The secret token is stored in SETTINGS['cron_secret'].
 * Set it once from the admin panel or run this URL with action=settoken:
 *   rdwcron.php?action=settoken&newtoken=yourSecret&adminpass=yourAdminPass
 */

define('CARPARTS_ACCESS', 1);
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/settings_helper.php';
include_once __DIR__ . '/pages/rdwu_functions.php';

// Plain-text output
header('Content-Type: text/plain; charset=utf-8');

// ── One-time token setup (admin only) ────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'settoken') {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) { echo "DB error: " . $db->connect_error; exit; }
    // Simple protection: require existing admin password from POST
    $pass = trim($_POST['adminpass'] ?? '');
    $expected = settings_get($db, 'admin_password_hash', '');
    if ($pass === '' || !password_verify($pass, $expected)) {
        http_response_code(403);
        echo "Invalid admin password.";
        exit;
    }
    $token = trim($_GET['newtoken'] ?? bin2hex(random_bytes(24)));
    if (strlen($token) < 16) { echo "Token too short (min 16 chars)."; exit; }
    settings_set($db, 'cron_secret', $token);
    $db->close();
    echo "Token set successfully.";
    exit;
}

// ── Token auth ───────────────────────────────────────────────────────────────
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    http_response_code(500);
    echo "DB connection failed.";
    exit;
}

$secret   = settings_get($db, 'cron_secret', '');
$provided = trim($_GET['token'] ?? '');

if ($secret === '' || !hash_equals($secret, $provided)) {
    http_response_code(403);
    echo "Forbidden.";
    $db->close();
    exit;
}

// ── Rate limit: skip if ran within the last 6 days ──────────────────────────
$last_run = settings_get($db, 'cron_last_run', '');
if ($last_run !== '' && (time() - strtotime($last_run)) < 6 * 86400) {
    echo "Skipped — last run was $last_run (min 6 days between runs).";
    $db->close();
    exit;
}

$start_time = date('Y-m-d H:i:s');
echo "RDW cron started at $start_time\n\n";

// ── Fetch all RDW data ───────────────────────────────────────────────────────
echo "Fetching RDW data...\n";
$rdw = rdwu_fetch_all_rdw();
if (isset($rdw['error'])) {
    echo "RDW fetch error: " . $rdw['error'] . "\n";
    $db->close();
    exit;
}
$rdw_raw = $rdw['data'];
echo "Fetched " . count($rdw_raw) . " RDW records.\n\n";

// Build plate lookup map
$rdw_by_plate = [];
foreach ($rdw_raw as $v) {
    $plate = strtoupper(preg_replace('/[^A-Z0-9]/', '', $v['kenteken'] ?? ''));
    if ($plate !== '') $rdw_by_plate[$plate] = $v;
}

// ── Fetch all DB cars ────────────────────────────────────────────────────────
$res = $db->query(
    "SELECT RECNO, License, Choise_Status, History FROM SNLDB
     WHERE Choise_Status NOT IN ('Wrecked') ORDER BY License"
);
$db_cars = $res->fetch_all(MYSQLI_ASSOC);
echo "Checking " . count($db_cars) . " cars in database...\n\n";

// ── Detect changes ───────────────────────────────────────────────────────────
$changed = rdwu_detect_changes($db_cars, $rdw_by_plate);
echo count($changed) . " change(s) detected.\n\n";

if (empty($changed)) {
    settings_set($db, 'cron_last_run', $start_time);
    echo "Nothing to update.\nDone.";
    $db->close();
    exit;
}

// ── Apply changes ────────────────────────────────────────────────────────────
$upd = $db->prepare(
    "UPDATE SNLDB SET Choise_Status = ?, History = ?, moddate = NOW() WHERE RECNO = ?"
);
$applied = 0;
foreach ($changed as $ch) {
    echo "  Updating {$ch['license']}: {$ch['old_status']} → {$ch['new_status']} — {$ch['reason']}\n";
    $upd->bind_param('ssi', $ch['new_status'], $ch['new_history'], $ch['recno']);
    if ($upd->execute()) $applied++;
}
$upd->close();

echo "\n$applied change(s) applied.\n";

settings_set($db, 'cron_last_run', $start_time);
settings_set($db, 'cron_last_count', (string)$applied);
echo "Last run time saved.\nDone.";
$db->close();
