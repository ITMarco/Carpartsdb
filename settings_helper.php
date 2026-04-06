<?php
// Simple key-value settings store backed by a SETTINGS table.
// Auto-creates the table on first use — no schema migration needed.
// Usage: settings_get($db, 'key', 'default')  /  settings_set($db, 'key', 'value')

function settings_ensure_table($db) {
    static $checked = false;
    if ($checked) return;
    $db->query(
        "CREATE TABLE IF NOT EXISTS SETTINGS (
            setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL DEFAULT '',
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $checked = true;
}

function settings_get($db, $key, $default = '') {
    settings_ensure_table($db);
    $stmt = $db->prepare("SELECT setting_value FROM SETTINGS WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($val);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? $val : $default;
}

function settings_set($db, $key, $value) {
    settings_ensure_table($db);
    $stmt = $db->prepare(
        "INSERT INTO SETTINGS (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
