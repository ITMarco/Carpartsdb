<?php
if (!defined('CARPARTS_ACCESS')) die('Direct access not permitted.');

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

    // Add email-confirmation columns if the table pre-dates them
    $r = $db->query("SHOW COLUMNS FROM `USERS` LIKE 'is_confirmed'");
    if ($r && $r->num_rows === 0) {
        // Default 1 so existing admin-created accounts stay accessible
        $db->query("ALTER TABLE `USERS`
            ADD COLUMN `is_confirmed`        TINYINT(1)  NOT NULL DEFAULT 1
                COMMENT '0 = pending email confirmation' AFTER `is_member`,
            ADD COLUMN `confirmation_token`  VARCHAR(64) NULL DEFAULT NULL
                COMMENT 'set on self-signup, cleared on confirm' AFTER `is_confirmed`");
    }

    // Per-user theme preference
    $r = $db->query("SHOW COLUMNS FROM `USERS` LIKE 'theme_id'");
    if ($r && $r->num_rows === 0) {
        $db->query("ALTER TABLE `USERS`
            ADD COLUMN `theme_id` INT NULL DEFAULT NULL
                COMMENT 'preferred theme; NULL = use site default' AFTER `confirmation_token`");
    }

    // User model usage history for quick-select in addpart
    $db->query("CREATE TABLE IF NOT EXISTS `USER_MODEL_PREFS` (
        `user_id`   INT       NOT NULL,
        `make_id`   INT       NOT NULL,
        `model_id`  INT       NOT NULL DEFAULT 0 COMMENT '0 = no specific model',
        `use_count` INT       NOT NULL DEFAULT 1,
        `last_used` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `make_id`, `model_id`),
        KEY `idx_user_count` (`user_id`, `use_count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Record that a user added a part for this make/model combination.
 * Increments the use_count; inserts if first time.
 */
function users_record_model_pref(mysqli $db, int $user_id, int $make_id, ?int $model_id): void {
    $mid = $model_id ?? 0;
    $db->query(
        "INSERT INTO `USER_MODEL_PREFS` (`user_id`,`make_id`,`model_id`,`use_count`)
         VALUES ({$user_id},{$make_id},{$mid},1)
         ON DUPLICATE KEY UPDATE `use_count` = `use_count` + 1, `last_used` = NOW()"
    );
}

/**
 * Return top N make/model combos used by a user.
 * Each row: [make_id, model_id (0=none), make_name, model_name (null if no model)]
 */
function users_get_top_models(mysqli $db, int $user_id, int $limit = 5): array {
    $stmt = $db->prepare(
        "SELECT mp.`make_id`, mp.`model_id`, m.`name` AS make_name, mo.`name` AS model_name
         FROM `USER_MODEL_PREFS` mp
         JOIN `CAR_MAKES` m ON m.`id` = mp.`make_id`
         LEFT JOIN `CAR_MODELS` mo ON mo.`id` = mp.`model_id` AND mp.`model_id` > 0
         WHERE mp.`user_id` = ?
         ORDER BY mp.`use_count` DESC, mp.`last_used` DESC
         LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Save (or clear) theme preference for a user. */
function users_save_theme(mysqli $db, int $user_id, ?int $theme_id): void {
    if ($theme_id !== null && $theme_id > 0) {
        $stmt = $db->prepare("UPDATE `USERS` SET `theme_id` = ? WHERE `id` = ?");
        $stmt->bind_param('ii', $theme_id, $user_id);
    } else {
        $stmt = $db->prepare("UPDATE `USERS` SET `theme_id` = NULL WHERE `id` = ?");
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $stmt->close();
}

/** Get stored theme_id for a user, or null. */
function users_get_theme(mysqli $db, int $user_id): ?int {
    $stmt = $db->prepare("SELECT `theme_id` FROM `USERS` WHERE `id` = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($tid);
    $stmt->fetch();
    $stmt->close();
    return $tid ?? null;
}

/** Fetch one user row by email. Returns assoc array or null. */
function users_get_by_email(mysqli $db, string $email): ?array {
    users_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT `id`,`email`,`realname`,`password`,`isadmin`,`is_member`,`is_confirmed`
         FROM `USERS` WHERE `email` = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Fetch a pending (unconfirmed) user by their confirmation token. */
function users_get_by_token(mysqli $db, string $token): ?array {
    users_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT `id`,`email`,`realname` FROM `USERS`
         WHERE `confirmation_token` = ? AND `is_confirmed` = 0 LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Delete unconfirmed accounts that are older than 7 days.
 * Call at most once per day (caller is responsible for throttling).
 */
function users_cleanup_unconfirmed(mysqli $db): void {
    $db->query(
        "DELETE FROM `USERS`
         WHERE `is_confirmed` = 0
           AND `created_at` < NOW() - INTERVAL 7 DAY"
    );
}

/** Mark account confirmed and clear the token. */
function users_confirm(mysqli $db, int $id): void {
    $stmt = $db->prepare(
        "UPDATE `USERS` SET `is_confirmed` = 1, `confirmation_token` = NULL WHERE `id` = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
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
