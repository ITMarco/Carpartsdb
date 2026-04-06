<?php
/**
 * IP Whitelist helper.
 * Whitelisted IPs bypass login rate limiting and are excluded from statistics.
 * Auto-creates IP_WHITELIST table on first use.
 */

function ip_whitelist_init($db) {
    $db->query("CREATE TABLE IF NOT EXISTS IP_WHITELIST (
        ip        VARCHAR(45)  NOT NULL PRIMARY KEY,
        label     VARCHAR(100) NOT NULL DEFAULT '',
        added_at  DATETIME     NOT NULL
    )");
}

function is_ip_whitelisted($db, $ip) {
    ip_whitelist_init($db);
    $stmt = $db->prepare("SELECT 1 FROM IP_WHITELIST WHERE ip = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

function ip_whitelist_add($db, $ip, $label = '') {
    ip_whitelist_init($db);
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "INSERT INTO IP_WHITELIST (ip, label, added_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label)"
    );
    if (!$stmt) return false;
    $stmt->bind_param("sss", $ip, $label, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ip_whitelist_remove($db, $ip) {
    $stmt = $db->prepare("DELETE FROM IP_WHITELIST WHERE ip = ?");
    if (!$stmt) return false;
    $stmt->bind_param("s", $ip);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ip_whitelist_get_all($db) {
    ip_whitelist_init($db);
    $result = $db->query("SELECT ip, label, added_at FROM IP_WHITELIST ORDER BY added_at DESC");
    $rows = [];
    if ($result) while ($r = $result->fetch_assoc()) $rows[] = $r;
    return $rows;
}
