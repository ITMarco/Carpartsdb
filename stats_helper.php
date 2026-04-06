<?php
if (!defined('SNLDBCARPARTS_ACCESS')) die('Direct access not permitted.');

/**
 * Ensure the STATS_DAILY table exists. Safe to call multiple times (cached).
 *
 * SQL (for direct execution):
 *   CREATE TABLE IF NOT EXISTS `STATS_DAILY` (
 *     `stat_date`    DATE NOT NULL,
 *     `sessions`     INT  NOT NULL DEFAULT 0,
 *     `searches`     INT  NOT NULL DEFAULT 0,
 *     `parts_added`  INT  NOT NULL DEFAULT 0,
 *     `images_added` INT  NOT NULL DEFAULT 0,
 *     PRIMARY KEY (`stat_date`)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
function stats_ensure_table(mysqli $db): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db->query("CREATE TABLE IF NOT EXISTS `STATS_DAILY` (
        `stat_date`    DATE NOT NULL,
        `sessions`     INT  NOT NULL DEFAULT 0,
        `searches`     INT  NOT NULL DEFAULT 0,
        `parts_added`  INT  NOT NULL DEFAULT 0,
        `images_added` INT  NOT NULL DEFAULT 0,
        PRIMARY KEY (`stat_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Increment the daily sessions counter (once per calendar day per PHP session).
 */
function stats_session_check(mysqli $db): void {
    stats_ensure_table($db);
    $today = date('Y-m-d');
    if (!isset($_SESSION['stats_day']) || $_SESSION['stats_day'] !== $today) {
        $_SESSION['stats_day'] = $today;
        $db->query(
            "INSERT INTO `STATS_DAILY` (stat_date, sessions) VALUES (CURDATE(), 1)
             ON DUPLICATE KEY UPDATE sessions = sessions + 1"
        );
    }
}

/**
 * Increment a named daily counter.
 *
 * @param string $col  One of: 'searches', 'parts_added', 'images_added'
 * @param int    $n    Amount to add (default 1)
 */
function stats_day(mysqli $db, string $col, int $n = 1): void {
    static $allowed = ['searches', 'parts_added', 'images_added'];
    if (!in_array($col, $allowed, true)) return;
    stats_ensure_table($db);
    $n = max(1, $n);
    $db->query(
        "INSERT INTO `STATS_DAILY` (stat_date, `{$col}`) VALUES (CURDATE(), {$n})
         ON DUPLICATE KEY UPDATE `{$col}` = `{$col}` + {$n}"
    );
}
