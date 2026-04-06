<?php
if (!defined('CARPARTS_ACCESS')) die('Direct access not permitted.');

/**
 * Ensure the PARTS table exists. Requires USERS, CAR_MAKES and CAR_MODELS to exist first.
 *
 * SQL (for direct execution — run AFTER users/makes/models tables):
 *   CREATE TABLE IF NOT EXISTS `PARTS` (
 *     `id`                 INT           NOT NULL AUTO_INCREMENT,
 *     `seller_id`          INT           NOT NULL,
 *     `make_id`            INT           NOT NULL,
 *     `model_id`           INT           DEFAULT NULL,
 *     `title`              VARCHAR(255)  NOT NULL,
 *     `description`        TEXT          DEFAULT NULL,
 *     `year_from`          SMALLINT      NOT NULL,
 *     `year_to`            SMALLINT      DEFAULT NULL,
 *     `price`              DECIMAL(10,2) NOT NULL DEFAULT '0.00',
 *     `condition`          TINYINT(1)    NOT NULL DEFAULT 3,
 *     `stock`              INT           NOT NULL DEFAULT 1,
 *     `oem_number`         VARCHAR(100)  DEFAULT NULL,
 *     `replacement_number` VARCHAR(100)  DEFAULT NULL,
 *     `visible`            TINYINT(1)    NOT NULL DEFAULT 1,
 *     `visible_private`    TINYINT(1)    NOT NULL DEFAULT 0,
 *     `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *     PRIMARY KEY (`id`),
 *     KEY `idx_seller`  (`seller_id`),
 *     KEY `idx_make`    (`make_id`),
 *     KEY `idx_model`   (`model_id`),
 *     KEY `idx_visible` (`visible`),
 *     CONSTRAINT `fk_part_seller` FOREIGN KEY (`seller_id`) REFERENCES `USERS`      (`id`) ON DELETE CASCADE,
 *     CONSTRAINT `fk_part_make`   FOREIGN KEY (`make_id`)   REFERENCES `CAR_MAKES`  (`id`),
 *     CONSTRAINT `fk_part_model`  FOREIGN KEY (`model_id`)  REFERENCES `CAR_MODELS` (`id`) ON DELETE SET NULL
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
function parts_ensure_table(mysqli $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    // Dependency: ensure parent tables exist first
    include_once 'users_helper.php';
    include_once 'makes_helper.php';
    users_ensure_table($db);
    makes_ensure_tables($db);

    $db->query("CREATE TABLE IF NOT EXISTS `PARTS` (
        `id`                 INT           NOT NULL AUTO_INCREMENT,
        `seller_id`          INT           NOT NULL,
        `make_id`            INT           NOT NULL,
        `model_id`           INT           DEFAULT NULL,
        `title`              VARCHAR(255)  NOT NULL,
        `description`        TEXT          DEFAULT NULL,
        `year_from`          SMALLINT      NOT NULL,
        `year_to`            SMALLINT      DEFAULT NULL,
        `price`              DECIMAL(10,2) NOT NULL DEFAULT '0.00',
        `condition`          TINYINT(1)    NOT NULL DEFAULT 3
            COMMENT '0=rubbish, 1=poor, 2=fair, 3=good, 4=very good, 5=mint',
        `stock`              INT           NOT NULL DEFAULT 1,
        `oem_number`         VARCHAR(100)  DEFAULT NULL,
        `replacement_number` VARCHAR(100)  DEFAULT NULL,
        `visible`            TINYINT(1)    NOT NULL DEFAULT 1,
        `visible_private`    TINYINT(1)    NOT NULL DEFAULT 0
            COMMENT 'only incrowd members (is_member=1) can see these',
        `for_sale`           TINYINT(1)    NOT NULL DEFAULT 1
            COMMENT '1=listed for sale, 0=display only',
        `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_seller`  (`seller_id`),
        KEY `idx_make`    (`make_id`),
        KEY `idx_model`   (`model_id`),
        KEY `idx_visible` (`visible`),
        CONSTRAINT `fk_part_seller` FOREIGN KEY (`seller_id`)
            REFERENCES `USERS`      (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_part_make`   FOREIGN KEY (`make_id`)
            REFERENCES `CAR_MAKES`  (`id`),
        CONSTRAINT `fk_part_model`  FOREIGN KEY (`model_id`)
            REFERENCES `CAR_MODELS` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $result = $db->query("SHOW COLUMNS FROM `PARTS` LIKE 'for_sale'");
    if ($result && $result->num_rows === 0) {
        $db->query("ALTER TABLE `PARTS` ADD COLUMN `for_sale` TINYINT(1) NOT NULL DEFAULT 1 AFTER `visible_private`");
    }
}

/** Returns the photo directory path for a part (relative to web root). */
function parts_photo_dir(int $id): string {
    return "parts/{$id}";
}

/**
 * Returns an array of photo file paths (relative to web root) for a part.
 * Sorted by filename ascending.
 */
function parts_photos(int $id): array {
    $dir   = parts_photo_dir($id);
    $files = glob("{$dir}/*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) ?: [];
    sort($files);
    return $files;
}

/** Condition labels (0–5). */
function parts_condition_label(int $cond): string {
    return match($cond) {
        0 => 'Rubbish',
        1 => 'Poor',
        2 => 'Fair',
        3 => 'Good',
        4 => 'Very Good',
        5 => 'Mint',
        default => 'Unknown',
    };
}

/** Display part ID as a human-readable reference number, e.g. PART-00042. */
function parts_ref(int $id): string {
    return sprintf('PART-%05d', $id);
}

/**
 * Fetch a single part by ID with make/model names joined.
 * Visibility filter: pass true to include private/hidden.
 */
function parts_get(mysqli $db, int $id, bool $include_hidden = false): ?array {
    parts_ensure_table($db);
    $vis = $include_hidden ? '' : " AND p.`visible` = 1";
    $stmt = $db->prepare(
        "SELECT p.*, m.`name` AS make_name, mo.`name` AS model_name,
                u.`email` AS seller_email, u.`realname` AS seller_name
         FROM `PARTS` p
         JOIN `CAR_MAKES` m  ON m.`id` = p.`make_id`
         LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
         LEFT JOIN `USERS` u ON u.`id` = p.`seller_id`
         WHERE p.`id` = ?{$vis} LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
