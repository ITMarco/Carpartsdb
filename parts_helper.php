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

    // photo_dir — stores the YYYYMMDD-{id} folder path; NULL means legacy parts/{id}
    $result = $db->query("SHOW COLUMNS FROM `PARTS` LIKE 'photo_dir'");
    if ($result && $result->num_rows === 0) {
        $db->query("ALTER TABLE `PARTS` ADD COLUMN `photo_dir` VARCHAR(100) NULL DEFAULT NULL AFTER `for_sale`");
    }

    // PART_COMPAT — additional make/model fitments for a part
    $db->query("CREATE TABLE IF NOT EXISTS `PART_COMPAT` (
        `id`       INT NOT NULL AUTO_INCREMENT,
        `part_id`  INT NOT NULL,
        `make_id`  INT NOT NULL,
        `model_id` INT DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_part` (`part_id`),
        CONSTRAINT `fk_compat_part`  FOREIGN KEY (`part_id`)  REFERENCES `PARTS`      (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_compat_make`  FOREIGN KEY (`make_id`)  REFERENCES `CAR_MAKES`  (`id`),
        CONSTRAINT `fk_compat_model` FOREIGN KEY (`model_id`) REFERENCES `CAR_MODELS` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Build the new-style photo directory path: parts/YYYYMMDD-00042
 * Call this when CREATING a new part's folder.
 */
function parts_photo_dir_new(int $id): string {
    return "parts/" . date('Ymd') . "-" . sprintf('%05d', $id);
}

/**
 * Find the actual photo directory for an existing part.
 * Checks new-style (parts/YYYYMMDD-00042) via glob first,
 * then falls back to legacy (parts/42).
 * Safe to call when no directory has been created yet.
 */
function parts_find_dir(int $id): string {
    $matches = array_filter(
        glob("parts/*-" . sprintf('%05d', $id)) ?: [],
        'is_dir'
    );
    if (!empty($matches)) return (string)reset($matches);
    return "parts/{$id}";
}

/**
 * Return the photo directory for a part, using the stored path from the DB row
 * ($part['photo_dir']) when available, with glob-based discovery as fallback.
 * Use this anywhere you have the full $part array (uploads, deletes, view).
 */
function parts_photo_dir_for(array $part): string {
    if (!empty($part['photo_dir'])) return (string)$part['photo_dir'];
    return parts_find_dir((int)$part['id']);
}

/** @deprecated Use parts_find_dir() or parts_photo_dir_for() instead. */
function parts_photo_dir(int $id): string {
    return parts_find_dir($id);
}

/**
 * Returns an array of photo file paths (relative to web root) for a part.
 * Sorted by filename ascending.
 */
function parts_photos(int $id): array {
    $dir   = parts_find_dir($id);
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
 * Returns the path to the first photo for a part, or null if none.
 */
function parts_first_photo(int $id): ?string {
    $dir   = parts_find_dir($id);
    $files = glob("{$dir}/*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) ?: [];
    sort($files);
    return $files[0] ?? null;
}

/**
 * Returns all "also fits" compat entries for a part.
 * Each entry: [make_id, model_id, make_name, model_name]
 */
function parts_compat_get(mysqli $db, int $part_id): array {
    $stmt = $db->prepare(
        "SELECT pc.`make_id`, pc.`model_id`, m.`name` AS make_name, mo.`name` AS model_name
         FROM `PART_COMPAT` pc
         JOIN `CAR_MAKES` m ON m.`id` = pc.`make_id`
         LEFT JOIN `CAR_MODELS` mo ON mo.`id` = pc.`model_id`
         WHERE pc.`part_id` = ?
         ORDER BY m.`name`, mo.`name`"
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $part_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Replace all compat entries for a part.
 * $entries = array of ['make_id' => int, 'model_id' => int|null]
 */
function parts_compat_save(mysqli $db, int $part_id, array $entries): void {
    $del = $db->prepare("DELETE FROM `PART_COMPAT` WHERE `part_id` = ?");
    $del->bind_param('i', $part_id);
    $del->execute();
    $del->close();

    if (empty($entries)) return;

    $ins = $db->prepare("INSERT INTO `PART_COMPAT` (`part_id`,`make_id`,`model_id`) VALUES (?,?,?)");
    foreach ($entries as $e) {
        $make_id  = (int)($e['make_id'] ?? 0);
        $model_id = isset($e['model_id']) && $e['model_id'] ? (int)$e['model_id'] : null;
        if ($make_id <= 0) continue;
        $ins->bind_param('iii', $part_id, $make_id, $model_id);
        $ins->execute();
    }
    $ins->close();
}

/**
 * Fetch a single part by ID with make/model names joined.
 * Visibility filter: pass true to include private/hidden.
 */
function parts_get(mysqli $db, int $id, bool $include_hidden = false): ?array {
    parts_ensure_table($db);
    $vis = $include_hidden ? '' : " AND p.`visible` = 1";
    $stmt = $db->prepare(
        "SELECT p.`id`, p.`seller_id`, p.`make_id`, p.`model_id`, p.`title`, p.`description`,
                p.`year_from`, p.`year_to`, p.`price`, p.`condition`, p.`stock`,
                p.`oem_number`, p.`replacement_number`, p.`visible`, p.`visible_private`,
                p.`for_sale`, p.`photo_dir`, p.`created_at`, p.`updated_at`,
                m.`name` AS make_name, mo.`name` AS model_name,
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
