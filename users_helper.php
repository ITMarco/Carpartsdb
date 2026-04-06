<?php
if (!defined('SNLDB_ACCESS')) die('Direct access not permitted.');

/**
 * Ensure the USERS table exists. Safe to call multiple times (cached).
 *
 * SQL (for direct execution):
 *   CREATE TABLE IF NOT EXISTS `USERS` (
 *     `id`         INT          NOT NULL AUTO_INCREMENT,
 *     `email`      VARCHAR(255) NOT NULL,
 *     `realname`   VARCHAR(255) DEFAULT NULL,
 *     `password`   VARCHAR(255) NOT NULL,
 *     `isadmin`    TINYINT(1)   NOT NULL DEFAULT 0,
 *     `is_member`  TINYINT(1)   NOT NULL DEFAULT 0,
 *     `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     PRIMARY KEY (`id`),
 *     UNIQUE KEY `uk_email` (`email`)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
function users_ensure_table(mysqli $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $db->query("CREATE TABLE IF NOT EXISTS `USERS` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `email`      VARCHAR(255) NOT NULL,
        `realname`   VARCHAR(255) DEFAULT NULL,
        `password`   VARCHAR(255) NOT NULL,
        `isadmin`    TINYINT(1)   NOT NULL DEFAULT 0,
        `is_member`  TINYINT(1)   NOT NULL DEFAULT 0
            COMMENT 'incrowd member: can see private listings',
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Fetch one user row by email. Returns assoc array or null. */
function users_get_by_email(mysqli $db, string $email): ?array {
    users_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT `id`,`email`,`realname`,`password`,`isadmin`,`is_member` FROM `USERS` WHERE `email` = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Fetch one user row by id. Returns assoc array or null. */
function users_get_by_id(mysqli $db, int $id): ?array {
    users_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT `id`,`email`,`realname`,`isadmin`,`is_member`,`created_at` FROM `USERS` WHERE `id` = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
