<?php
/**
 * DB-backed login rate-limiting functions.
 * Safe to include mid-request (no session setup, no output).
 * Requires DB_HOST/DB_USER/DB_PASS/DB_NAME/MAX_LOGIN_ATTEMPTS/LOGIN_LOCKOUT_TIME constants.
 */
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ip_whitelist_helper.php';

function _login_attempts_db() {
    $db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$db) return null;
    $db->query("CREATE TABLE IF NOT EXISTS LOGIN_ATTEMPTS (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        attempt_time INT NOT NULL,
        INDEX idx_ip_time (ip, attempt_time)
    )");
    return $db;
}

/**
 * Check if the current IP is rate-limited.
 * Whitelisted IPs are never blocked.
 * Returns ['blocked' => bool, 'remaining_time' => int (seconds)].
 */
function check_rate_limit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $db = _login_attempts_db();
    if (!$db) return ['blocked' => false, 'remaining_time' => 0];

    if (is_ip_whitelisted($db, $ip)) {
        $db->close();
        return ['blocked' => false, 'remaining_time' => 0];
    }

    $window = time() - LOGIN_LOCKOUT_TIME;
    $stmt = $db->prepare("SELECT COUNT(*), MIN(attempt_time) FROM LOGIN_ATTEMPTS WHERE ip = ? AND attempt_time > ?");
    $stmt->bind_param("si", $ip, $window);
    $stmt->execute();
    $stmt->bind_result($count, $oldest);
    $stmt->fetch();
    $stmt->close();
    $db->close();

    if ($count >= MAX_LOGIN_ATTEMPTS) {
        $remaining = max(0, ($oldest + LOGIN_LOCKOUT_TIME) - time());
        return ['blocked' => true, 'remaining_time' => $remaining];
    }
    return ['blocked' => false, 'remaining_time' => 0];
}

/** Record a failed login attempt for the current IP. */
function record_failed_login() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $db = _login_attempts_db();
    if (!$db) return;
    // Don't record if whitelisted
    if (is_ip_whitelisted($db, $ip)) { $db->close(); return; }
    $t    = time();
    $stmt = $db->prepare("INSERT INTO LOGIN_ATTEMPTS (ip, attempt_time) VALUES (?, ?)");
    $stmt->bind_param("si", $ip, $t);
    $stmt->execute();
    $stmt->close();
    $db->close();
    error_log("Failed login attempt from IP: $ip");
}

/** Clear login attempts for the current IP after a successful login. */
function reset_login_attempts() {
    $ip   = $_SERVER['REMOTE_ADDR'];
    $db   = _login_attempts_db();
    if (!$db) return;
    $stmt = $db->prepare("DELETE FROM LOGIN_ATTEMPTS WHERE ip = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

/** How many attempts remain before the current IP is locked out. */
function get_remaining_attempts() {
    $ip     = $_SERVER['REMOTE_ADDR'];
    $window = time() - LOGIN_LOCKOUT_TIME;
    $db     = _login_attempts_db();
    if (!$db) return MAX_LOGIN_ATTEMPTS;
    $stmt   = $db->prepare("SELECT COUNT(*) FROM LOGIN_ATTEMPTS WHERE ip = ? AND attempt_time > ?");
    $stmt->bind_param("si", $ip, $window);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $db->close();
    return max(0, MAX_LOGIN_ATTEMPTS - $count);
}
