<?php
// Ring-buffer table for recently uploaded photos.
// Keeps exactly PHOTO_RECENT_SLOTS rows — oldest slot is overwritten on each new upload.

const PHOTO_RECENT_SLOTS = 24;

function photo_recent_init($db) {
    $db->query("CREATE TABLE IF NOT EXISTS PHOTO_RECENT (
        slot        TINYINT UNSIGNED NOT NULL,
        license     VARCHAR(20)      DEFAULT NULL,
        filename    VARCHAR(255)     DEFAULT NULL,
        uploaded_at DATETIME         DEFAULT NULL,
        PRIMARY KEY (slot)
    )");
    $cnt = $db->query("SELECT COUNT(*) FROM PHOTO_RECENT")->fetch_row()[0];
    for ($i = $cnt; $i < PHOTO_RECENT_SLOTS; $i++) {
        $db->query("INSERT IGNORE INTO PHOTO_RECENT (slot, uploaded_at) VALUES ($i, '2000-01-01 00:00:00')");
    }
}

function photo_recent_add($db, $license, $filename) {
    photo_recent_init($db);
    $r = $db->query("SELECT slot FROM PHOTO_RECENT ORDER BY uploaded_at ASC LIMIT 1");
    if (!$r) return;
    $slot = intval($r->fetch_row()[0]);
    $stmt = $db->prepare("UPDATE PHOTO_RECENT SET license=?, filename=?, uploaded_at=NOW() WHERE slot=?");
    if (!$stmt) return;
    $stmt->bind_param("ssi", $license, $filename, $slot);
    $stmt->execute();
    $stmt->close();
}
