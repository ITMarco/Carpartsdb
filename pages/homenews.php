<?php
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);

if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div class='content-box'><p style='color:red;'>Geen toegang. <a href='index.php?navigate=secureadmin'>Log in als admin</a>.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include_once 'connection.php';

// Auto-create table
$CarpartsConnection->query("CREATE TABLE IF NOT EXISTS HOME_NEWS (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200)  NOT NULL,
    body       TEXT          NOT NULL,
    news_date  DATE          NOT NULL,
    sort_order INT           NOT NULL DEFAULT 0,
    visible    TINYINT(1)    NOT NULL DEFAULT 1
)");

$msg      = '';
$msg_ok   = true;
$edit_item = null;

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = 'Beveiligingsfout. Probeer opnieuw.';
        $msg_ok = false;
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title      = trim($_POST['title']      ?? '');
            $body       = $_POST['body']             ?? '';
            $news_date  = $_POST['news_date']        ?? date('Y-m-d');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            if ($title === '') {
                $msg = 'Titel is verplicht.'; $msg_ok = false;
            } else {
                $st = $CarpartsConnection->prepare(
                    "INSERT INTO HOME_NEWS (title, body, news_date, sort_order, visible) VALUES (?,?,?,?,1)"
                );
                $st->bind_param('sssi', $title, $body, $news_date, $sort_order);
                $st->execute();
                $st->close();
                $msg = '✓ Nieuwsitem toegevoegd.';
            }

        } elseif ($action === 'save') {
            $id         = (int)($_POST['id']         ?? 0);
            $title      = trim($_POST['title']       ?? '');
            $body       = $_POST['body']              ?? '';
            $news_date  = $_POST['news_date']         ?? date('Y-m-d');
            $sort_order = (int)($_POST['sort_order']  ?? 0);
            if (!$id || $title === '') {
                $msg = 'Ongeldige invoer.'; $msg_ok = false;
            } else {
                $st = $CarpartsConnection->prepare(
                    "UPDATE HOME_NEWS SET title=?, body=?, news_date=?, sort_order=? WHERE id=?"
                );
                $st->bind_param('sssii', $title, $body, $news_date, $sort_order, $id);
                $st->execute();
                $st->close();
                $msg = '✓ Opgeslagen.';
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $st = $CarpartsConnection->prepare("DELETE FROM HOME_NEWS WHERE id=?");
                $st->bind_param('i', $id);
                $st->execute();
                $st->close();
                $msg = '✓ Verwijderd.';
            }

        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $st = $CarpartsConnection->prepare("UPDATE HOME_NEWS SET visible = 1 - visible WHERE id=?");
                $st->bind_param('i', $id);
                $st->execute();
                $st->close();
                $msg = '✓ Zichtbaarheid gewijzigd.';
            }
        }
    }
}

// ── Edit mode: load item ──────────────────────────────────────────────────────
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $est = $CarpartsConnection->prepare("SELECT * FROM HOME_NEWS WHERE id=?");
    $est->bind_param('i', $edit_id);
    $est->execute();
    $edit_item = $est->get_result()->fetch_assoc();
    $est->close();
}

// ── Fetch all items ───────────────────────────────────────────────────────────
$items = [];
$res = $CarpartsConnection->query("SELECT * FROM HOME_NEWS ORDER BY sort_order DESC, news_date DESC");
if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;

$fs = 'padding:6px 10px;font-size:13px;width:100%;box-sizing:border-box;
       border:1px solid var(--color-content-border);border-radius:4px;
       background:var(--color-input-bg);color:var(--color-text);';
$ls = 'display:block;font-size:12px;font-weight:bold;margin-bottom:4px;color:var(--color-text);';
?>
<div class="content-box">
<h3>Frontpage nieuws beheer</h3>

<?php if ($msg): ?>
<div style="padding:8px 12px;background:var(--color-input-bg);
            border:1px solid <?= $msg_ok ? 'var(--color-content-border)' : '#c04040' ?>;
            border-radius:4px;margin-bottom:16px;font-size:13px;
            color:<?= $msg_ok ? 'var(--color-text)' : '#c04040' ?>;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Item list ── -->
<table style="border-collapse:collapse;width:100%;font-size:13px;margin-bottom:28px;">
<thead>
<tr style="border-bottom:2px solid var(--color-content-border);font-size:11px;
           text-transform:uppercase;letter-spacing:1px;color:var(--color-accent);">
    <th style="padding:6px 10px 6px 0;text-align:left;">Titel</th>
    <th style="padding:6px 10px;text-align:left;">Datum</th>
    <th style="padding:6px 10px;text-align:center;">Volgorde</th>
    <th style="padding:6px 10px;text-align:center;">Zichtbaar</th>
    <th style="padding:6px 10px;text-align:right;"></th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $it): ?>
<tr style="border-bottom:1px solid var(--color-nav-border);">
    <td style="padding:7px 10px 7px 0;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= htmlspecialchars($it['title']) ?>
    </td>
    <td style="padding:7px 10px;color:var(--color-muted);white-space:nowrap;">
        <?= htmlspecialchars($it['news_date']) ?>
    </td>
    <td style="padding:7px 10px;text-align:center;color:var(--color-muted);">
        <?= (int)$it['sort_order'] ?>
    </td>
    <td style="padding:7px 10px;text-align:center;">
        <form method="post" action="index.php?navigate=homenews" style="display:inline;margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action"     value="toggle">
            <input type="hidden" name="id"         value="<?= (int)$it['id'] ?>">
            <button type="submit"
                    style="border:none;background:none;cursor:pointer;font-size:16px;padding:0;line-height:1;"
                    title="<?= $it['visible'] ? 'Klik om te verbergen' : 'Klik om te tonen' ?>">
                <?= $it['visible'] ? '✅' : '⬜' ?>
            </button>
        </form>
    </td>
    <td style="padding:7px 0 7px 10px;text-align:right;white-space:nowrap;">
        <a href="index.php?navigate=homenews&edit=<?= (int)$it['id'] ?>"
           style="font-size:12px;color:var(--color-link);margin-right:14px;">Bewerk</a>
        <form method="post" action="index.php?navigate=homenews" style="display:inline;margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action"     value="delete">
            <input type="hidden" name="id"         value="<?= (int)$it['id'] ?>">
            <button type="submit"
                    onclick="return confirm('Item \'<?= addslashes(htmlspecialchars($it['title'])) ?>\' verwijderen?')"
                    style="border:none;background:none;cursor:pointer;font-size:12px;
                           color:#c04040;padding:0;">
                Verwijder
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($items)): ?>
<tr><td colspan="5" style="padding:14px 0;color:var(--color-muted);font-size:13px;">
    Nog geen nieuwsitems.
</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- ── Add / Edit form ── -->
<h4 style="margin:0 0 14px;color:var(--color-text);">
    <?= $edit_item ? 'Bewerk item' : 'Nieuw item toevoegen' ?>
</h4>

<form method="post" action="index.php?navigate=homenews" style="max-width:640px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action"     value="<?= $edit_item ? 'save' : 'add' ?>">
    <?php if ($edit_item): ?>
    <input type="hidden" name="id" value="<?= (int)$edit_item['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom:12px;">
        <label style="<?= $ls ?>">Titel</label>
        <input type="text" name="title" maxlength="200" required style="<?= $fs ?>"
               value="<?= htmlspecialchars($edit_item['title'] ?? '') ?>" />
    </div>

    <div style="display:flex;gap:16px;margin-bottom:12px;flex-wrap:wrap;">
        <div style="flex:1;min-width:140px;">
            <label style="<?= $ls ?>">Datum</label>
            <input type="date" name="news_date" required style="<?= $fs ?>"
                   value="<?= htmlspecialchars($edit_item['news_date'] ?? date('Y-m-d')) ?>" />
        </div>
        <div style="width:160px;">
            <label style="<?= $ls ?>">Volgorde <span style="font-weight:normal;">(hoog = bovenaan)</span></label>
            <input type="number" name="sort_order" style="<?= $fs ?>"
                   value="<?= (int)($edit_item['sort_order'] ?? 0) ?>" />
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <label style="<?= $ls ?>">Inhoud <span style="font-weight:normal;color:var(--color-muted);">(HTML toegestaan)</span></label>
        <textarea name="body" rows="10"
                  style="<?= $fs ?>resize:vertical;"><?= htmlspecialchars($edit_item['body'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:12px;align-items:center;">
        <input type="submit" value="<?= $edit_item ? 'Opslaan' : 'Toevoegen' ?>" class="btn" />
        <?php if ($edit_item): ?>
        <a href="index.php?navigate=homenews" style="font-size:13px;color:var(--color-link);">Annuleer</a>
        <?php endif; ?>
    </div>
</form>

</div>
