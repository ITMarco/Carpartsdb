<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo '<p>Access denied.</p>';
    return;
}

define('SNLDBCARPARTS_ACCESS', 1);
include_once 'connection.php';
include_once 'theme_helper.php';

$db = $CarpartsConnection;

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$msg = '';
$edit_theme = null;

// All editable CSS variables with human labels
$var_defs = [
    '--color-body-bg'          => 'Page background',
    '--color-text'             => 'Body text',
    '--color-link'             => 'Link color',
    '--color-container-border' => 'Container border',
    '--color-surface'          => 'Content surface (panels)',
    '--color-accent'           => 'Accent / headings',
    '--color-accent-dark'      => 'Accent dark (hamburger border)',
    '--color-nav-bg'           => 'Nav background',
    '--color-nav-text'         => 'Nav text',
    '--color-nav-border'       => 'Nav border lines',
    '--color-nav-hover-bg'     => 'Nav hover background',
    '--color-nav-hover-text'   => 'Nav hover text',
    '--color-input-bg'         => 'Input background',
    '--color-input-border'     => 'Input border',
    '--color-content-border'   => 'Content-box border',
    '--btn-bg'                 => 'Button background',
    '--btn-text'               => 'Button text',
    '--btn-border'             => 'Button border',
    '--color-box-header-bg'    => 'Content box header background',
    '--color-box-header-text'  => 'Content box header text',
    '--color-news-bg-1'        => 'Nieuwsachtergrond 1 (oneven)',
    '--color-news-bg-2'        => 'Nieuwsachtergrond 2 (even)',
];

$radius_options = [
    '0px'  => 'Square (0px)',
    '4px'  => 'Slight (4px)',
    '8px'  => 'Rounded (8px)',
    '24px' => 'Pill (24px)',
];

function var_to_field(string $var): string {
    return str_replace('-', '_', ltrim($var, '-'));
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $msg = '<span style="color:red">CSRF validation failed.</span>';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'activate') {
            $id = (int)($_POST['theme_id'] ?? 0);
            if ($id > 0) { theme_activate($db, $id); $msg = 'Theme activated.'; }

        } elseif ($action === 'toggle_public') {
            $id  = (int)($_POST['theme_id'] ?? 0);
            $pub = (int)($_POST['current_public'] ?? 0);
            if ($id > 0) { theme_set_public($db, $id, !$pub); $msg = 'Visibility updated.'; }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['theme_id'] ?? 0);
            if ($id > 0) { theme_delete($db, $id); $msg = 'Theme deleted. (Active themes cannot be deleted.)'; }

        } elseif ($action === 'save' || $action === 'update') {
            $name = trim($_POST['theme_name'] ?? '');
            if ($name === '') {
                $msg = '<span style="color:red">Theme name is required.</span>';
            } else {
                $vars = [];
                foreach ($var_defs as $v => $_) {
                    $field = var_to_field($v);
                    $val = trim($_POST[$field] ?? '');
                    if ($val !== '') $vars[$v] = $val;
                }
                // Button radius from select
                $radius = $_POST['btn_radius'] ?? '4px';
                if (array_key_exists($radius, $radius_options)) {
                    $vars['--btn-radius'] = $radius;
                }

                $is_dark = !empty($_POST['is_dark']);
                $edit_id = (int)($_POST['edit_id'] ?? 0);
                if ($edit_id > 0) {
                    theme_update($db, $edit_id, $name, $vars, $is_dark);
                    $msg = 'Theme &ldquo;' . htmlspecialchars($name) . '&rdquo; updated.';
                } else {
                    theme_save($db, $name, $vars, $is_dark);
                    $msg = 'Theme &ldquo;' . htmlspecialchars($name) . '&rdquo; saved.';
                }
            }
        }
    }
}

// Load theme for editing (GET)
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $edit_theme = theme_get($db, $eid);
    if ($edit_theme) {
        $edit_theme['vars_arr'] = json_decode($edit_theme['vars'], true) ?: [];
    }
}

$themes = theme_list($db);

// Helper: get default value for a var (from Classic Gray or current edit)
function field_val(array $edit, string $var, string $fallback): string {
    if ($edit) return $edit['vars_arr'][$var] ?? $fallback;
    return $fallback;
}

$defaults = [
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
];
?>

<h2>Theme &amp; Color Manager</h2>

<?php if ($msg): ?>
<p style="background:var(--color-input-bg);border:1px solid var(--color-content-border);padding:8px;margin-bottom:10px;">
    <?php echo $msg; ?>
</p>
<?php endif; ?>

<!-- ===== Saved themes ===== -->
<div class="content-box">
    <strong>Saved themes</strong>
    <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:12px;">
        <thead>
            <tr style="background:var(--color-nav-hover-bg);">
                <th style="text-align:left;padding:4px 8px;">Name</th>
                <th style="padding:4px 8px;">Status</th>
                <th style="padding:4px 8px;">Public</th>
                <th style="padding:4px 8px;">Dark</th>
                <th style="padding:4px 8px;">Created</th>
                <th style="padding:4px 8px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($themes as $t): ?>
        <tr style="border-top:1px solid var(--color-nav-border);">
            <td style="padding:4px 8px;"><?php echo htmlspecialchars($t['name']); ?></td>
            <td style="text-align:center;padding:4px 8px;">
                <?php if ($t['is_active']): ?>
                    <strong style="color:green;">&#10003; Active</strong>
                <?php else: ?>
                    <span style="color:#999;">inactive</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;padding:4px 8px;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="toggle_public">
                    <input type="hidden" name="theme_id" value="<?php echo (int)$t['id']; ?>">
                    <input type="hidden" name="current_public" value="<?php echo (int)$t['is_public']; ?>">
                    <button type="submit" class="btn<?php echo $t['is_public'] ? '' : ' btn-ghost'; ?>"
                            style="font-size:11px;padding:3px 8px;"
                            title="<?php echo $t['is_public'] ? 'Klik om te verbergen' : 'Klik om zichtbaar te maken'; ?>">
                        <?php echo $t['is_public'] ? '👁 Zichtbaar' : '🚫 Verborgen'; ?>
                    </button>
                </form>
            </td>
            <td style="text-align:center;padding:4px 8px;"><?php echo $t['is_dark'] ? '🌙 Donker' : '☀ Licht'; ?></td>
            <td style="text-align:center;padding:4px 8px;"><?php echo htmlspecialchars(substr($t['created_at'], 0, 10)); ?></td>
            <td style="text-align:center;padding:4px 6px;white-space:nowrap;">
                <!-- Activate -->
                <?php if (!$t['is_active']): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="theme_id" value="<?php echo (int)$t['id']; ?>">
                    <button type="submit" class="btn" style="font-size:11px;padding:3px 8px;">Activate</button>
                </form>
                <?php endif; ?>
                <!-- Edit -->
                <a href="?navigate=themeadmin&amp;edit_id=<?php echo (int)$t['id']; ?>" class="btn btn-ghost" style="font-size:11px;padding:3px 8px;">Edit</a>
                <!-- Delete -->
                <?php if (!$t['is_active']): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this theme?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="theme_id" value="<?php echo (int)$t['id']; ?>">
                    <button type="submit" class="btn" style="font-size:11px;padding:3px 8px;background:#c0392b;border-color:#c0392b;">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ===== Editor + Live preview ===== -->
<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">

<!-- Left: form -->
<div style="flex:1 1 340px;min-width:280px;">
<div class="content-box">
    <strong><?php echo $edit_theme ? 'Edit theme: ' . htmlspecialchars($edit_theme['name']) : 'Create new theme'; ?></strong>

    <form method="post" style="margin-top:10px;" id="theme-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="<?php echo $edit_theme ? 'update' : 'save'; ?>">
        <?php if ($edit_theme): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int)$edit_theme['id']; ?>">
        <?php endif; ?>

        <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <tr>
            <td style="padding:4px 6px 4px 0;width:160px;"><label>Theme name</label></td>
            <td style="padding:4px 0;">
                <input type="text" name="theme_name"
                    value="<?php echo htmlspecialchars($edit_theme ? $edit_theme['name'] : ''); ?>"
                    style="width:100%;font-size:12px;" placeholder="My Theme">
            </td>
        </tr>

        <?php foreach ($var_defs as $var => $label):
            $field = var_to_field($var);
            $val   = field_val($edit_theme ?: [], $var, $defaults[$var]);
        ?>
        <tr>
            <td style="padding:4px 6px 4px 0;">
                <label for="<?php echo htmlspecialchars($field); ?>"><?php echo htmlspecialchars($label); ?></label>
            </td>
            <td style="padding:4px 0;display:flex;gap:6px;align-items:center;">
                <input type="color" id="<?php echo htmlspecialchars($field); ?>"
                    name="<?php echo htmlspecialchars($field); ?>"
                    value="<?php echo htmlspecialchars($val); ?>"
                    data-var="<?php echo htmlspecialchars($var); ?>"
                    class="color-input"
                    style="width:36px;height:26px;border:1px solid var(--color-input-border);cursor:pointer;">
                <input type="text" name="<?php echo htmlspecialchars($field); ?>_hex"
                    value="<?php echo htmlspecialchars($val); ?>"
                    maxlength="7" style="width:72px;font-size:11px;font-family:monospace;"
                    data-color-field="<?php echo htmlspecialchars($field); ?>"
                    class="hex-input">
            </td>
        </tr>
        <?php endforeach; ?>

        <!-- Button radius -->
        <?php
        $cur_radius = field_val($edit_theme ?: [], '--btn-radius', $defaults['--btn-radius']);
        ?>
        <tr>
            <td style="padding:4px 6px 4px 0;"><label>Button shape</label></td>
            <td style="padding:4px 0;">
                <select name="btn_radius" id="btn_radius_select" class="radius-select" style="font-size:12px;">
                    <?php foreach ($radius_options as $val => $label): ?>
                    <option value="<?php echo htmlspecialchars($val); ?>"
                        <?php echo ($cur_radius === $val) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <!-- is_dark toggle -->
        <tr>
            <td style="padding:8px 6px 4px 0;"><label for="is_dark_cb">Donker thema</label></td>
            <td style="padding:8px 0 4px 0;">
                <input type="checkbox" id="is_dark_cb" name="is_dark" value="1"
                    <?php echo (!empty($edit_theme['is_dark'])) ? 'checked' : ''; ?>>
                <span style="font-size:11px;color:#888;margin-left:4px;">mix-blend-mode: screen op afbeeldingen</span>
            </td>
        </tr>
        </table>

        <div style="margin-top:10px;">
            <button type="submit" class="btn"><?php echo $edit_theme ? 'Update theme' : 'Save as new theme'; ?></button>
            <?php if ($edit_theme): ?>
            <a href="?navigate=themeadmin" class="btn btn-ghost" style="margin-left:6px;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>
</div>

<!-- Right: live preview -->
<div style="flex:1 1 300px;min-width:250px;">
<div class="content-box">
    <strong>Live preview</strong>
    <div id="theme-preview" style="margin-top:10px;border:1px solid var(--color-container-border);padding:10px;font-size:12px;">
        <div style="background:var(--color-body-bg);padding:8px;margin-bottom:8px;">
            Page background &amp; text color
            <span style="color:var(--color-link);">· link</span>
        </div>
        <div style="background:var(--color-nav-bg);border:1px solid var(--color-nav-border);padding:0;margin-bottom:8px;width:120px;">
            <div style="padding:5px 10px;color:var(--color-nav-text);font-family:verdana;font-size:10px;">Nav item</div>
            <div style="padding:5px 10px;background:var(--color-nav-hover-bg);color:var(--color-nav-hover-text);font-family:verdana;font-size:10px;">Nav hover</div>
        </div>
        <div style="background:var(--color-surface);border:1px solid var(--color-content-border);padding:0;margin-bottom:8px;overflow:hidden;">
            <div style="background:var(--color-box-header-bg);color:var(--color-box-header-text);padding:4px 8px;font-size:11px;font-weight:bold;border-bottom:1px solid var(--color-content-border);">Content box header</div>
            <div style="padding:6px;color:var(--color-accent);">Accent text · <span style="color:var(--color-text);">body text</span></div>
        </div>
        <div style="background:var(--color-input-bg);border:1px solid var(--color-input-border);padding:4px 6px;margin-bottom:8px;color:var(--color-text);font-size:11px;">
            Input field
        </div>
        <div style="margin-bottom:6px;">
            <span style="display:inline-block;background:var(--btn-bg);color:var(--btn-text);border:1px solid var(--btn-border);border-radius:var(--btn-radius);padding:6px 14px;font-size:13px;font-family:Arial,sans-serif;cursor:default;">
                Button
            </span>
            &nbsp;
            <span style="display:inline-block;background:transparent;color:var(--btn-border);border:1px solid var(--btn-border);border-radius:var(--btn-radius);padding:6px 14px;font-size:13px;font-family:Arial,sans-serif;cursor:default;">
                Ghost
            </span>
        </div>
    </div>
</div>
</div>

</div><!-- end flex row -->

<script>
(function() {
    // Sync hex text inputs with color pickers
    document.querySelectorAll('.color-input').forEach(function(picker) {
        var field = picker.id;
        var hex = document.querySelector('.hex-input[data-color-field="' + field + '"]');
        if (!hex) return;
        picker.addEventListener('input', function() {
            hex.value = picker.value;
            updatePreview();
        });
        hex.addEventListener('input', function() {
            var v = hex.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                picker.value = v;
                updatePreview();
            }
        });
    });

    document.getElementById('btn_radius_select').addEventListener('change', updatePreview);

    function updatePreview() {
        var preview = document.getElementById('theme-preview');
        document.querySelectorAll('.color-input').forEach(function(picker) {
            preview.style.setProperty(picker.dataset.var, picker.value);
        });
        var radius = document.getElementById('btn_radius_select').value;
        preview.style.setProperty('--btn-radius', radius);
    }

    // Init preview with current values
    updatePreview();
})();
</script>
