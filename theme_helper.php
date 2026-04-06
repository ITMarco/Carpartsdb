<?php
// Theme system — CSS custom property theming backed by THEMES table.

function theme_ensure_table($db) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $db->query("CREATE TABLE IF NOT EXISTS THEMES (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(64)  NOT NULL,
        vars       TEXT         NOT NULL,
        is_active  TINYINT(1)   DEFAULT 0,
        created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
    )");

    // Add is_public column if it doesn't exist yet (silent if already present)
    $db->query("ALTER TABLE THEMES ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0");

    // Add is_dark column if it doesn't exist yet
    $db->query("ALTER TABLE THEMES ADD COLUMN is_dark TINYINT(1) NOT NULL DEFAULT 0");

    $r = $db->query("SELECT COUNT(*) AS cnt FROM THEMES");
    if (!$r || $r->fetch_assoc()['cnt'] > 0) return;

    // Seed built-in themes
    $classic = $db->real_escape_string(json_encode([
        '--color-body-bg'          => '#EEEEEE',
        '--color-text'             => '#333333',
        '--color-link'             => '#000000',
        '--color-container-border' => '#C2C2C2',
        '--color-surface'          => '#FFFFFF',
        '--color-accent'           => '#3B495A',
        '--color-accent-dark'      => '#576C85',
        '--color-nav-bg'           => '#FFFFFF',
        '--color-nav-text'         => '#3B495A',
        '--color-nav-border'       => '#A3A3A3',
        '--color-nav-hover-bg'     => '#E0E0E0',
        '--color-nav-hover-text'   => '#FFFFFF',
        '--color-input-bg'         => '#ECF1FF',
        '--color-input-border'     => '#000000',
        '--color-content-border'   => '#576C85',
        '--btn-bg'                 => '#576C85',
        '--btn-text'               => '#FFFFFF',
        '--btn-border'             => '#576C85',
        '--btn-radius'             => '4px',
        '--color-box-header-bg'    => '#E0E0E0',
        '--color-box-header-text'  => '#3B495A',
        '--color-news-bg-1'        => '#F0F0EC',
        '--color-news-bg-2'        => '#E6EAF0',
    ]));
    $unseen = $db->real_escape_string(json_encode([
        '--color-body-bg'          => '#E8D5CC',
        '--color-text'             => '#1C1C1C',
        '--color-link'             => '#4A3028',
        '--color-container-border' => '#D4B5A8',
        '--color-surface'          => '#F5EBE6',
        '--color-accent'           => '#C4907A',
        '--color-accent-dark'      => '#B07868',
        '--color-nav-bg'           => '#F5EBE6',
        '--color-nav-text'         => '#1C1C1C',
        '--color-nav-border'       => '#D4B5A8',
        '--color-nav-hover-bg'     => '#E0C8BF',
        '--color-nav-hover-text'   => '#1C1C1C',
        '--color-input-bg'         => '#FAF3F0',
        '--color-input-border'     => '#C4907A',
        '--color-content-border'   => '#C4907A',
        '--btn-bg'                 => '#1C1C1C',
        '--btn-text'               => '#F5EBE6',
        '--btn-border'             => '#1C1C1C',
        '--btn-radius'             => '24px',
        '--color-box-header-bg'    => '#E0C8BF',
        '--color-box-header-text'  => '#1C1C1C',
        '--color-news-bg-1'        => '#F0E8E4',
        '--color-news-bg-2'        => '#E6DDD8',
    ]));

    $db->query("INSERT INTO THEMES (name, vars, is_active, is_public, is_dark) VALUES
        ('Classic Gray',   '$classic', 1, 1, 0),
        ('Unseen Studio',  '$unseen',  0, 1, 0)");
}

// Shared: convert vars array to :root { } CSS string.
function _theme_vars_to_css(array $vars): string {
    if (empty($vars)) return '';
    $lines = ":root {\n";
    foreach ($vars as $k => $v) {
        $k = preg_replace('/[^a-z0-9\-]/', '', $k);
        $v = preg_replace('/[^a-zA-Z0-9#%.()\/, ]/', '', $v);
        $lines .= "  $k: $v;\n";
    }
    $lines .= "}\n";
    return $lines;
}

function theme_get_active($db) {
    theme_ensure_table($db);
    $r = $db->query("SELECT vars FROM THEMES WHERE is_active = 1 LIMIT 1");
    if ($r && ($row = $r->fetch_assoc())) {
        return json_decode($row['vars'], true) ?: [];
    }
    return [];
}

// Returns CSS for the site-active theme.
function theme_get_css($db) {
    return _theme_vars_to_css(theme_get_active($db));
}

// Returns CSS for a specific theme by ID — only if it is public (or active).
// Returns empty string if the ID is invalid or not accessible.
function theme_get_css_for_user($db, int $id): string {
    theme_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT vars FROM THEMES WHERE id = ? AND (is_public = 1 OR is_active = 1) LIMIT 1"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return '';
    return _theme_vars_to_css(json_decode($row['vars'], true) ?: []);
}

// Returns all public themes (plus the active one) for the picker widget.
function theme_list_public($db): array {
    theme_ensure_table($db);
    $r = $db->query(
        "SELECT id, name, vars, is_active, is_public, is_dark
         FROM THEMES WHERE is_public = 1 OR is_active = 1 ORDER BY id"
    );
    $out = [];
    while ($r && ($row = $r->fetch_assoc())) {
        $row['vars_arr'] = json_decode($row['vars'], true) ?: [];
        $out[] = $row;
    }
    return $out;
}

function theme_list($db) {
    theme_ensure_table($db);
    $r = $db->query("SELECT id, name, is_active, is_public, is_dark, created_at FROM THEMES ORDER BY id");
    $out = [];
    while ($r && ($row = $r->fetch_assoc())) $out[] = $row;
    return $out;
}

function theme_get($db, $id) {
    theme_ensure_table($db);
    $stmt = $db->prepare("SELECT id, name, vars, is_active, is_public, is_dark FROM THEMES WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function theme_save($db, $name, array $vars, bool $is_dark = false) {
    theme_ensure_table($db);
    $json = json_encode($vars);
    $dark = $is_dark ? 1 : 0;
    $stmt = $db->prepare("INSERT INTO THEMES (name, vars, is_active, is_dark) VALUES (?, ?, 0, ?)");
    $stmt->bind_param("ssi", $name, $json, $dark);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    return $id;
}

function theme_update($db, $id, $name, array $vars, bool $is_dark = false) {
    $json = json_encode($vars);
    $dark = $is_dark ? 1 : 0;
    $stmt = $db->prepare("UPDATE THEMES SET name=?, vars=?, is_dark=? WHERE id=?");
    $stmt->bind_param("ssii", $name, $json, $dark, $id);
    $stmt->execute();
    $stmt->close();
}

function theme_get_active_is_dark($db): bool {
    theme_ensure_table($db);
    $r = $db->query("SELECT is_dark FROM THEMES WHERE is_active = 1 LIMIT 1");
    if ($r && ($row = $r->fetch_assoc())) {
        return (int)$row['is_dark'] === 1;
    }
    return false;
}

function theme_activate($db, $id) {
    $db->query("UPDATE THEMES SET is_active = 0");
    $stmt = $db->prepare("UPDATE THEMES SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

function theme_set_public($db, $id, bool $public) {
    $v = $public ? 1 : 0;
    $stmt = $db->prepare("UPDATE THEMES SET is_public = ? WHERE id = ?");
    $stmt->bind_param("ii", $v, $id);
    $stmt->execute();
    $stmt->close();
}

function theme_delete($db, $id) {
    // Cannot delete the active theme
    $stmt = $db->prepare("DELETE FROM THEMES WHERE id = ? AND is_active = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
