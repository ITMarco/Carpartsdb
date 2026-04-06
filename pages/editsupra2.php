<?php
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);

// ── helpers ──────────────────────────────────────────────────────────────────
function es2_opt(string $val, string $label, string $cur): string {
    $sel = $cur === $val ? ' selected' : '';
    return '<option value="' . htmlspecialchars($val) . '"' . $sel . '>'
         . htmlspecialchars($label) . '</option>';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error      = '';
$car        = null;
$pw_success = false;
$pw_error   = '';

// ── handle password change POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'changepassword') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $pw_error = 'Beveiligingscontrole mislukt.';
    } elseif (!isset($_SESSION['authenticated'], $_SESSION['user_license'])) {
        $pw_error = 'Niet ingelogd.';
    } else {
        include_once 'connection.php';
        $lic      = strtoupper($_SESSION['user_license']);
        $cur_pass = $_POST['current_password'] ?? '';
        $new_pass = trim($_POST['new_password'] ?? '');

        if (strlen($new_pass) < 6) {
            $pw_error = 'Nieuw wachtwoord moet minimaal 6 tekens zijn.';
        } else {
            $st = $CarpartsConnection->prepare("SELECT userpass FROM PASSWRDS WHERE carlicense = ?");
            $st->bind_param('s', $lic);
            $st->execute();
            $pw_row = $st->get_result()->fetch_assoc();
            $st->close();

            $ok = false;
            if ($pw_row && !empty($pw_row['userpass'])) {
                if (str_starts_with($pw_row['userpass'], '$2y$')) {
                    $ok = password_verify($cur_pass, $pw_row['userpass']);
                } else {
                    $ok = ($pw_row['userpass'] === $cur_pass);
                }
            }

            if (!$ok) {
                $pw_error = 'Huidig wachtwoord is onjuist.';
            } else {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $upd = $CarpartsConnection->prepare("UPDATE PASSWRDS SET userpass = ? WHERE carlicense = ?");
                $upd->bind_param('ss', $hashed, $lic);
                $pw_success = $upd->execute();
                $upd->close();
                if (!$pw_success) $pw_error = 'Fout bij opslaan wachtwoord.';
            }
        }
    }
}

// ── handle login POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userLicense'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Beveiligingscontrole mislukt. Probeer opnieuw.';
    } else {
        include_once 'connection.php';
        $lic  = strtoupper(trim($_POST['userLicense'] ?? ''));
        $pass = $_POST['userpassword'] ?? '';

        $stmt = $CarpartsConnection->prepare("SELECT * FROM PASSWRDS WHERE carlicense = ?");
        if ($stmt) {
            $stmt->bind_param('s', $lic);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $ok = false;
            if ($row) {
                foreach (['userpass', 'fullaccesspass'] as $col) {
                    if (empty($row[$col])) continue;
                    if (str_starts_with($row[$col], '$2y$')) {
                        if (password_verify($pass, $row[$col])) { $ok = true; break; }
                    } else {
                        if ($row[$col] === $pass) { $ok = true; break; }
                    }
                }
            }

            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['authenticated']  = true;
                $_SESSION['user_license']   = $row['carlicense'];
                $_SESSION['LAST_ACTIVITY']  = time();
            } else {
                $error = 'Kenteken of wachtwoord onjuist.';
            }
        } else {
            $error = 'Database fout.';
        }
    }
}

// ── load car if authenticated ─────────────────────────────────────────────────
if (!$error && isset($_SESSION['authenticated'], $_SESSION['user_license'])) {
    if (!isset($CarpartsConnection)) include_once 'connection.php';
    $ul   = $_SESSION['user_license'];
    $stmt = $CarpartsConnection->prepare(
        "SELECT License, Owner_display, Owner_show, Choise_Model, Milage, Choise_Status,
                Choise_Engine, Registration_date, Build_date, History, Mods,
                MA, VIN_Colorcode, Choise_Transmission, RECNO
         FROM SNLDB WHERE License = ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $ul);
        $stmt->execute();
        $car = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$car) {
        $error = 'Geen auto gevonden voor kenteken ' . htmlspecialchars($ul) . '.';
        unset($_SESSION['authenticated'], $_SESSION['user_license']);
    }
}
?>
<div class="content-box">
<h3>Bewerk je supra</h3>

<?php if ($error): ?>
<div style="color:#c04040;padding:8px 12px;background:var(--color-input-bg);
            border:1px solid #c04040;border-radius:4px;margin-bottom:14px;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (!$car): ?>
<!-- ── login form ── -->
<div style="max-width:340px;">
    <p style="font-size:13px;color:var(--color-muted);margin-bottom:16px;">
        Log in met je kenteken en wachtwoord om je supra te bewerken.
    </p>
    <form method="post" action="index.php?navigate=editsupra2">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <table style="border-collapse:collapse;width:100%;">
            <tr>
                <td style="padding:6px 10px 6px 0;font-size:12px;font-weight:bold;white-space:nowrap;">Kenteken</td>
                <td style="padding:6px 0;">
                    <input type="text" name="userLicense" required autofocus
                           placeholder="bijv. 37-HXR-9"
                           style="padding:7px 10px;font-size:13px;width:200px;
                                  border:1px solid var(--color-content-border);
                                  border-radius:4px;background:var(--color-input-bg);
                                  color:var(--color-text);" />
                </td>
            </tr>
            <tr>
                <td style="padding:6px 10px 6px 0;font-size:12px;font-weight:bold;">Wachtwoord</td>
                <td style="padding:6px 0;">
                    <input type="password" name="userpassword" required
                           style="padding:7px 10px;font-size:13px;width:200px;
                                  border:1px solid var(--color-content-border);
                                  border-radius:4px;background:var(--color-input-bg);
                                  color:var(--color-text);" />
                </td>
            </tr>
            <tr>
                <td></td>
                <td style="padding:10px 0 0 0;">
                    <input type="submit" value="Inloggen" class="btn" />
                </td>
            </tr>
        </table>
    </form>
</div>

<?php else: ?>
<!-- ── edit form ── -->
<div style="font-size:12px;color:var(--color-muted);margin-bottom:16px;">
    Ingelogd als <strong><?= htmlspecialchars($car['License']) ?></strong>
    &nbsp;·&nbsp;
    <a href="index.php?navigate=editsupra2&logout=1" style="color:var(--color-link);">Uitloggen</a>
    &nbsp;·&nbsp;
    <a href="#snl-pw-change" style="color:var(--color-link);"
       onclick="var d=document.getElementById('snl-pw-change');d.open=true;">Wachtwoord wijzigen</a>
</div>

<?php
// handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['authenticated'], $_SESSION['user_license']);
    echo '<script>location.href="index.php?navigate=editsupra2";</script>';
}
?>

<form method="post" action="index.php?navigate=procesedit2" style="max-width:640px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="recno"   value="<?= (int)$car['RECNO'] ?>" />
    <input type="hidden" name="history" value="<?= htmlspecialchars($car['History']) ?>" />
    <input type="hidden" name="License" value="<?= htmlspecialchars($car['License']) ?>" />

    <?php
    $field_style = 'padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box;
                    border:1px solid var(--color-content-border);border-radius:4px;
                    background:var(--color-input-bg);color:var(--color-text);';
    $label_style = 'padding:7px 10px 7px 0;font-size:12px;font-weight:bold;
                    white-space:nowrap;vertical-align:top;width:160px;';
    $row_style   = 'border-bottom:1px solid var(--color-nav-border);';
    ?>

    <table style="border-collapse:collapse;width:100%;margin-bottom:18px;">

        <!-- Basisgegevens -->
        <tr><td colspan="2" style="padding:10px 0 4px;font-size:11px;font-weight:bold;
                text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
            Basisgegevens
        </td></tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Eigenaar</td>
            <td style="padding:6px 0;">
                <input type="text" name="owner" maxlength="80"
                       value="<?= htmlspecialchars($car['Owner_display']) ?>"
                       style="<?= $field_style ?>" />
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Naam zichtbaar</td>
            <td style="padding:8px 0;font-size:13px;">
                <label>
                    <input type="checkbox" name="owner_show" value="1"
                           <?= !empty($car['Owner_show']) ? 'checked' : '' ?> />
                    Toon mijn naam op de site
                </label>
                <div style="font-size:11px;color:var(--color-muted);margin-top:3px;">
                    Standaard verborgen. Als je dit aanvinkt staat je naam publiek zichtbaar.
                </div>
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Bouwjaar</td>
            <td style="padding:6px 0;">
                <input type="text" name="bouwjaar" maxlength="20"
                       value="<?= htmlspecialchars($car['Build_date']) ?>"
                       style="<?= $field_style ?>" />
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Registratiedatum</td>
            <td style="padding:6px 0;">
                <input type="text" name="regdate" maxlength="20"
                       value="<?= htmlspecialchars($car['Registration_date']) ?>"
                       style="<?= $field_style ?>" />
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Kilometerstand</td>
            <td style="padding:6px 0;">
                <input type="text" name="milage" maxlength="20"
                       value="<?= htmlspecialchars($car['Milage']) ?>"
                       style="<?= $field_style ?>" />
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Kleurcode</td>
            <td style="padding:6px 0;">
                <input type="text" name="color" maxlength="20"
                       value="<?= htmlspecialchars($car['VIN_Colorcode']) ?>"
                       style="<?= $field_style ?>" />
            </td>
        </tr>

        <!-- Technisch -->
        <tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;font-weight:bold;
                text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
            Technisch
        </td></tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Model</td>
            <td style="padding:6px 0;">
                <select name="mark" style="<?= $field_style ?>">
                    <?= es2_opt('MA-46 (MKI)',    'Celica Supra MKI',    $car['Choise_Model']) ?>
                    <?= es2_opt('MA-60 (MKII)',   'Celica Supra MKII',   $car['Choise_Model']) ?>
                    <?= es2_opt('MA-70 (MKIII)',  'Supra MKIII MA',      $car['Choise_Model']) ?>
                    <?= es2_opt('JZA70',          'Supra MKIII JZA',     $car['Choise_Model']) ?>
                    <?= es2_opt('JA-80 (MKIV)',   'Supra MKIV',          $car['Choise_Model']) ?>
                    <?= es2_opt('A-90 (MKV)',     'Supra MKV',           $car['Choise_Model']) ?>
                </select>
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Motor</td>
            <td style="padding:6px 0;">
                <select name="engine" style="<?= $field_style ?>">
                    <?= es2_opt('4M-E',           '4M-E',                $car['Choise_Engine']) ?>
                    <?= es2_opt('5M-GE',          '5M-GE',               $car['Choise_Engine']) ?>
                    <?= es2_opt('7M-GE',          '7M-GE',               $car['Choise_Engine']) ?>
                    <?= es2_opt('7M-GTE',         '7M-GTE',              $car['Choise_Engine']) ?>
                    <?= es2_opt('1JZ-GTE',        '1JZ-GTE',             $car['Choise_Engine']) ?>
                    <?= es2_opt('1JZ-GTE-VVTI',   '1JZ-GTE VVT-I',       $car['Choise_Engine']) ?>
                    <?= es2_opt('1.5JZ-GTE',      '1.5JZ-GTE',           $car['Choise_Engine']) ?>
                    <?= es2_opt('2JZ-GE',         '2JZ-GE',              $car['Choise_Engine']) ?>
                    <?= es2_opt('2JZ-GTE',        '2JZ-GTE',             $car['Choise_Engine']) ?>
                    <?= es2_opt('1G-GTE',         '1G-GTE',              $car['Choise_Engine']) ?>
                    <?= es2_opt('BMW-B48',        'BMW B48',             $car['Choise_Engine']) ?>
                    <?= es2_opt('BMW-B58',        'BMW B58',             $car['Choise_Engine']) ?>
                    <?= es2_opt('Unknown',        'Unknown',             $car['Choise_Engine']) ?>
                </select>
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Versnellingsbak</td>
            <td style="padding:6px 0;">
                <select name="transmission" style="<?= $field_style ?>">
                    <?= es2_opt('W50 (5 Speed manual 4M)',       'W50 — 5-bak (4M)',         $car['Choise_Transmission']) ?>
                    <?= es2_opt('W58 (5 speed manual 5M)',       'W58 — 5-bak (5M)',         $car['Choise_Transmission']) ?>
                    <?= es2_opt('W58 (5 speed manual 7M-GE)',    'W58 — 5-bak (7M-GE)',      $car['Choise_Transmission']) ?>
                    <?= es2_opt('W58 (5 speed manual 2JZ)',      'W58 — 5-bak (2JZ)',        $car['Choise_Transmission']) ?>
                    <?= es2_opt('V160 (6 speed manual 2JZ)',     'V160 — 6-bak (2JZ)',       $car['Choise_Transmission']) ?>
                    <?= es2_opt('V161 (6 speed manual 2JZ)',     'V161 — 6-bak (2JZ)',       $car['Choise_Transmission']) ?>
                    <?= es2_opt('R154 (5 Speed manual 7M-GTE)',  'R154 — 5-bak (7M-GTE)',    $car['Choise_Transmission']) ?>
                    <?= es2_opt('A43DE (4 Speed Auto 5M)',       'A43DE — 4-traps auto (5M)', $car['Choise_Transmission']) ?>
                    <?= es2_opt('A340E (4 Speed Auto 7M)',       'A340E — 4-traps auto (7M)', $car['Choise_Transmission']) ?>
                    <?= es2_opt('A342E (4 speed Auto 2JZ)',      'A342E — 4-traps auto (2JZ)',$car['Choise_Transmission']) ?>
                    <?= es2_opt('T56 (Upgrade kit for JZ)',      'T56 — 6-bak (2JZ upgrade)',$car['Choise_Transmission']) ?>
                    <?= es2_opt('ZF 8HP (8 speed Auto MK5)',     'ZF 8HP — 8-traps auto (MK5)',$car['Choise_Transmission']) ?>
                    <?= es2_opt('ZF S6-53 (6 speed manual MK5)', 'ZF S6-53 — 6-bak (MK5)',  $car['Choise_Transmission']) ?>
                    <?= es2_opt('Other',                         'Other',                    $car['Choise_Transmission']) ?>
                </select>
            </td>
        </tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Handbak / automaat</td>
            <td style="padding:8px 0;font-size:13px;">
                <label style="margin-right:16px;">
                    <input type="radio" name="trans" value="M" <?= $car['MA'] === 'M' ? 'checked' : '' ?> />
                    Handbak
                </label>
                <label>
                    <input type="radio" name="trans" value="A" <?= $car['MA'] !== 'M' ? 'checked' : '' ?> />
                    Automaat
                </label>
            </td>
        </tr>

        <!-- Status -->
        <tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;font-weight:bold;
                text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
            Status
        </td></tr>
        <tr style="<?= $row_style ?>">
            <td style="<?= $label_style ?>">Status</td>
            <td style="padding:6px 0;">
                <select name="status" style="<?= $field_style ?>">
                    <?= es2_opt('Running',         'Running',          $car['Choise_Status']) ?>
                    <?= es2_opt('No Road License', 'Geen kenteken',    $car['Choise_Status']) ?>
                    <?= es2_opt('Wrecked',         'Wrecked',          $car['Choise_Status']) ?>
                    <?= es2_opt('Garage',          'Garage',           $car['Choise_Status']) ?>
                    <?= es2_opt('Forsale',         'For sale',         $car['Choise_Status']) ?>
                    <?= es2_opt('Not Available',   'Not Available',    $car['Choise_Status']) ?>
                </select>
            </td>
        </tr>

        <!-- Modificaties -->
        <tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;font-weight:bold;
                text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
            Modificaties
        </td></tr>
        <tr style="<?= $row_style ?>">
            <td colspan="2" style="padding:6px 0;">
                <textarea name="mods" rows="8"
                          style="<?= $field_style ?> resize:vertical;"
                          ><?= htmlspecialchars($car['Mods']) ?></textarea>
            </td>
        </tr>

        <!-- Geschiedenis -->
        <tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;font-weight:bold;
                text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
            Geschiedenis toevoegen
        </td></tr>
        <tr>
            <td colspan="2" style="padding:4px 0 2px;font-size:11px;color:var(--color-muted);">
                Wat je hier invult wordt bovenaan de geschiedenis toegevoegd. Laat leeg als je niets wilt toevoegen.
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:6px 0;">
                <textarea name="history2" rows="6"
                          style="<?= $field_style ?> resize:vertical;"
                          placeholder="Bijv: Motor vervangen, nieuw uitlaatsysteem, ..."></textarea>
            </td>
        </tr>

    </table>

    <div style="display:flex;gap:12px;align-items:center;">
        <input type="submit" value="Wijzigingen opslaan" class="btn" />
        <a href="index.php?navigate=<?= urlencode($car['License']) ?>"
           style="font-size:12px;color:var(--color-link);">Bekijk auto</a>
        &nbsp;·&nbsp;
        <a href="index.php?navigate=uploadimage"
           style="font-size:12px;color:var(--color-link);">Foto's toevoegen</a>
    </div>

</form>

<!-- ── password change ── -->
<details id="snl-pw-change" style="margin-top:28px;max-width:480px;" <?= $pw_success || $pw_error ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-size:13px;font-weight:bold;
                    color:var(--color-accent);padding:6px 0;user-select:none;">
        🔑 Wachtwoord wijzigen
    </summary>
    <div style="margin-top:12px;">
        <?php if ($pw_success): ?>
        <div style="color:#2a8a2a;padding:8px 12px;background:var(--color-input-bg);
                    border:1px solid #2a8a2a;border-radius:4px;margin-bottom:12px;font-size:13px;">
            ✓ Wachtwoord gewijzigd!
        </div>
        <?php elseif ($pw_error): ?>
        <div style="color:#c04040;padding:8px 12px;background:var(--color-input-bg);
                    border:1px solid #c04040;border-radius:4px;margin-bottom:12px;font-size:13px;">
            <?= htmlspecialchars($pw_error) ?>
        </div>
        <?php endif; ?>
        <form method="post" action="index.php?navigate=editsupra2">
            <input type="hidden" name="action"     value="changepassword" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <table style="border-collapse:collapse;width:100%;margin-bottom:12px;">
                <tr style="<?= $row_style ?>">
                    <td style="<?= $label_style ?>">Huidig wachtwoord</td>
                    <td style="padding:6px 0;">
                        <input type="password" name="current_password" required
                               style="<?= $field_style ?>" />
                    </td>
                </tr>
                <tr>
                    <td style="<?= $label_style ?>">Nieuw wachtwoord</td>
                    <td style="padding:6px 0;">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="snl_new_pw" name="new_password" required
                                   minlength="6" style="<?= $field_style ?>flex:1;min-width:160px;" />
                            <button type="button" onclick="snlGenPw()"
                                    style="white-space:nowrap;padding:6px 10px;font-size:12px;
                                           cursor:pointer;border-radius:4px;border:1px solid var(--color-content-border);
                                           background:var(--btn-bg);color:var(--btn-text);">
                                🎲 Random
                            </button>
                        </div>
                        <div style="font-size:11px;color:var(--color-muted);margin-top:3px;">Minimaal 6 tekens.</div>
                    </td>
                </tr>
            </table>
            <input type="submit" value="Wachtwoord opslaan" class="btn" />
        </form>
    </div>
</details>
<script>
function snlGenPw() {
    var c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    var p = '';
    for (var i = 0; i < 12; i++) p += c[Math.floor(Math.random() * c.length)];
    var f = document.getElementById('snl_new_pw');
    f.value = p;
    f.type  = 'text';
}
</script>

<!-- upload image hidden form for photo link -->
<form id="goto-upload" method="post" action="index.php?navigate=uploadimage" style="display:none;">
    <input type="hidden" name="License" value="<?= htmlspecialchars($car['License']) ?>" />
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
</form>

<?php endif; ?>
</div>
