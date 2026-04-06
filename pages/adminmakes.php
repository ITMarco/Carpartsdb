<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied.</div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'makes_helper.php';

makes_ensure_tables($CarpartsConnection);

$msg = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = '<span style="color:red;">Security validation failed.</span>';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_make') {
            $name = trim($_POST['make_name'] ?? '');
            if ($name !== '') {
                $stmt = $CarpartsConnection->prepare("INSERT IGNORE INTO `CAR_MAKES` (`name`) VALUES (?)");
                $stmt->bind_param('s', $name);
                $msg = $stmt->execute() && $stmt->affected_rows > 0
                     ? '<span style="color:green;">Make "' . htmlspecialchars($name) . '" added.</span>'
                     : '<span style="color:orange;">Make already exists or could not be added.</span>';
                $stmt->close();
            }

        } elseif ($action === 'delete_make') {
            $make_id = intval($_POST['make_id'] ?? 0);
            if ($make_id > 0) {
                $stmt = $CarpartsConnection->prepare("DELETE FROM `CAR_MAKES` WHERE `id` = ?");
                $stmt->bind_param('i', $make_id);
                $stmt->execute();
                $msg = '<span style="color:green;">Make deleted (and its models).</span>';
                $stmt->close();
            }

        } elseif ($action === 'add_model') {
            $make_id    = intval($_POST['make_id'] ?? 0);
            $model_name = trim($_POST['model_name'] ?? '');
            $year_from  = intval($_POST['year_from'] ?? 0) ?: null;
            $year_to    = intval($_POST['year_to']   ?? 0) ?: null;
            if ($make_id > 0 && $model_name !== '') {
                $stmt = $CarpartsConnection->prepare(
                    "INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES (?,?,?,?)"
                );
                $stmt->bind_param('isii', $make_id, $model_name, $year_from, $year_to);
                $msg = $stmt->execute() && $stmt->affected_rows > 0
                     ? '<span style="color:green;">Model "' . htmlspecialchars($model_name) . '" added.</span>'
                     : '<span style="color:orange;">Model already exists or could not be added.</span>';
                $stmt->close();
            }

        } elseif ($action === 'delete_model') {
            $model_id = intval($_POST['model_id'] ?? 0);
            if ($model_id > 0) {
                $stmt = $CarpartsConnection->prepare("DELETE FROM `CAR_MODELS` WHERE `id` = ?");
                $stmt->bind_param('i', $model_id);
                $stmt->execute();
                $msg = '<span style="color:green;">Model deleted.</span>';
                $stmt->close();
            }
        }
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$makes  = makes_list($CarpartsConnection);
$filter_make = isset($_GET['make']) ? intval($_GET['make']) : (int)(array_key_first($makes) ?? 0);
$models = $filter_make > 0 ? makes_models_for($CarpartsConnection, $filter_make) : [];
mysqli_close($CarpartsConnection);
?>

<div class="content-box">
<h3>Car Makes &amp; Models</h3>
<?php if ($msg): ?><p><?= $msg ?></p><?php endif; ?>

<div style="display:flex;gap:30px;flex-wrap:wrap;margin-top:10px;">

    <!-- Makes column -->
    <div style="flex:1;min-width:220px;">
        <h4>Car Makes</h4>

        <form method="post" action="index.php?navigate=adminmakes" style="display:flex;gap:6px;margin-bottom:12px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="action" value="add_make" />
            <input type="text" name="make_name" required placeholder="New make name"
                   style="padding:5px;flex:1;" maxlength="100" />
            <input type="submit" value="Add" class="btn" style="padding:5px 12px;" />
        </form>

        <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
            <td style="padding:4px 8px;">Make</td>
            <td style="padding:4px 8px;text-align:right;">Models</td>
            <td style="padding:4px 8px;"></td>
        </tr>
        <?php foreach ($makes as $mid => $mname):
            $model_count_r = null; // we'll count inline
        ?>
        <tr style="border-bottom:1px solid var(--color-content-border);<?= ($mid === $filter_make) ? 'background:var(--color-nav-hover-bg,#eef);' : '' ?>">
            <td style="padding:4px 8px;">
                <a href="index.php?navigate=adminmakes&make=<?= $mid ?>" style="font-weight:<?= ($mid === $filter_make) ? 'bold' : 'normal' ?>">
                    <?= htmlspecialchars($mname) ?>
                </a>
            </td>
            <td style="padding:4px 8px;text-align:right;font-size:11px;color:#888;"></td>
            <td style="padding:4px 8px;text-align:right;">
                <form method="post" action="index.php?navigate=adminmakes&make=<?= $filter_make ?>"
                      style="display:inline;" onsubmit="return confirm('Delete make and all its models?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                    <input type="hidden" name="action"  value="delete_make" />
                    <input type="hidden" name="make_id" value="<?= $mid ?>" />
                    <input type="submit" value="&times;" style="background:#dc3545;color:#fff;border:none;cursor:pointer;padding:2px 7px;border-radius:3px;" />
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div>

    <!-- Models column -->
    <div style="flex:1;min-width:260px;">
        <h4>Models for: <em><?= htmlspecialchars($makes[$filter_make] ?? '—') ?></em></h4>

        <?php if ($filter_make > 0): ?>
        <form method="post" action="index.php?navigate=adminmakes&make=<?= $filter_make ?>"
              style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="action"  value="add_model" />
            <input type="hidden" name="make_id" value="<?= $filter_make ?>" />
            <div>
                <label style="font-size:11px;">Model name</label><br>
                <input type="text" name="model_name" required placeholder="e.g. Cressida"
                       style="padding:5px;width:140px;" maxlength="100" />
            </div>
            <div>
                <label style="font-size:11px;">Year from</label><br>
                <input type="number" name="year_from" min="1900" max="<?= date('Y') ?>"
                       placeholder="1990" style="width:80px;padding:5px;" />
            </div>
            <div>
                <label style="font-size:11px;">Year to</label><br>
                <input type="number" name="year_to" min="1900" max="<?= date('Y') + 5 ?>"
                       placeholder="(blank=current)" style="width:100px;padding:5px;" />
            </div>
            <div>
                <input type="submit" value="Add model" class="btn" style="padding:6px 12px;" />
            </div>
        </form>

        <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);">
            <td style="padding:4px 8px;">Model</td>
            <td style="padding:4px 8px;">Years</td>
            <td style="padding:4px 8px;"></td>
        </tr>
        <?php foreach ($models as $model): ?>
        <tr style="border-bottom:1px solid var(--color-content-border);">
            <td style="padding:4px 8px;"><?= htmlspecialchars($model['name']) ?></td>
            <td style="padding:4px 8px;font-size:11px;color:#666;">
                <?= $model['year_from'] ? (int)$model['year_from'] : '?' ?>
                &ndash;
                <?= $model['year_to'] ? (int)$model['year_to'] : 'present' ?>
            </td>
            <td style="padding:4px 8px;text-align:right;">
                <form method="post" action="index.php?navigate=adminmakes&make=<?= $filter_make ?>"
                      style="display:inline;" onsubmit="return confirm('Delete this model?');">
                    <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                    <input type="hidden" name="action"    value="delete_model" />
                    <input type="hidden" name="model_id"  value="<?= (int)$model['id'] ?>" />
                    <input type="submit" value="&times;" style="background:#dc3545;color:#fff;border:none;cursor:pointer;padding:2px 7px;border-radius:3px;" />
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($models)): ?><tr><td colspan="3" style="padding:8px;color:#888;">No models yet.</td></tr><?php endif; ?>
        </table>
        <?php else: ?>
        <p style="color:#888;">Select a make on the left to manage its models.</p>
        <?php endif; ?>
    </div>

</div>

<p style="margin-top:16px;"><a href="index.php?navigate=adminpanel">&larr; Admin panel</a></p>
</div>
