<?php
/**
 * Car view/edit statistics helper.
 * Auto-creates the CAR_VIEWS table on first use.
 * Whitelisted IPs are never logged.
 */
require_once __DIR__ . '/ip_whitelist_helper.php';

function car_stats_init($db) {
    $db->query("CREATE TABLE IF NOT EXISTS CAR_VIEWS (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        license    VARCHAR(20)                        NOT NULL,
        event_type ENUM('view','edit')                NOT NULL DEFAULT 'view',
        ip         VARCHAR(45)                        NOT NULL,
        user_agent VARCHAR(255)                       NOT NULL DEFAULT '',
        view_time  INT                                NOT NULL,
        INDEX idx_license  (license),
        INDEX idx_time     (view_time),
        INDEX idx_type     (event_type)
    )");
}

function car_stats_cleanup($db) {
    $cutoff = time() - 90 * 86400;
    $db->query("DELETE FROM CAR_VIEWS WHERE view_time < $cutoff LIMIT 2000");
}

function car_changelog_log($db, $license, $type) {
    $db->query("CREATE TABLE IF NOT EXISTS SNLDB_CHANGELOG (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        license     VARCHAR(20)  NOT NULL,
        change_type VARCHAR(10)  NOT NULL,
        changed_at  DATETIME     NOT NULL,
        INDEX idx_time    (changed_at),
        INDEX idx_license (license)
    )");
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO SNLDB_CHANGELOG (license, change_type, changed_at) VALUES (?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param("sss", $license, $type, $now);
    $stmt->execute();
    $stmt->close();
}

function car_stats_log($db, $license, $event_type = 'view') {
    // For views: only count once per session to avoid refresh spam
    if ($event_type === 'view') {
        $session_key = 'car_stat_view_' . $license;
        if (!empty($_SESSION[$session_key])) return;
        $_SESSION[$session_key] = 1;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($event_type === 'view' && is_ip_whitelisted($db, $ip)) return;
    car_stats_init($db);
    $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $t    = time();
    $stmt = $db->prepare(
        "INSERT INTO CAR_VIEWS (license, event_type, ip, user_agent, view_time) VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param("ssssi", $license, $event_type, $ip, $ua, $t);
    $stmt->execute();
    $stmt->close();
}
