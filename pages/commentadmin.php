<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied. <a href='index.php?navigate=secureadmin'>Log in</a></div>";
    return;
}
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include_once 'connection.php';
include_once 'settings_helper.php';
include_once 'comment_helper.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = '<div style="color:red;">CSRF token mismatch.</div>';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'toggle_comments') {
            $cur = settings_get($CarpartsConnection, 'comments_enabled', '1');
            settings_set($CarpartsConnection, 'comments_enabled', $cur === '1' ? '0' : '1');
            $msg = '<div style="color:green;padding:4px 0;">✓ Instelling opgeslagen.</div>';
        } elseif ($action === 'toggle_video') {
            $cur = settings_get($CarpartsConnection, 'comments_video_enabled', '1');
            settings_set($CarpartsConnection, 'comments_video_enabled', $cur === '1' ? '0' : '1');
            $msg = '<div style="color:green;padding:4px 0;">✓ Instelling opgeslagen.</div>';
        } elseif ($action === 'delete' && isset($_POST['comment_id'])) {
            comment_delete($CarpartsConnection, (int)$_POST['comment_id']);
            $msg = '<div style="color:green;padding:4px 0;">✓ Reactie verwijderd.</div>';
        } elseif ($action === 'toggle' && isset($_POST['comment_id'])) {
            comment_toggle($CarpartsConnection, (int)$_POST['comment_id']);
            $msg = '<div style="color:green;padding:4px 0;">✓ Status gewijzigd.</div>';
        }
    }
}

$enabled       = settings_get($CarpartsConnection, 'comments_enabled',       '1') === '1';
$video_enabled = settings_get($CarpartsConnection, 'comments_video_enabled', '1') === '1';

// Mark all comments as seen by updating the last-seen timestamp
settings_set($CarpartsConnection, 'comments_last_seen', date('Y-m-d H:i:s'));

// Fetch all comments (including unapproved) for admin overview
comment_ensure_table($CarpartsConnection);
$_all_res = $CarpartsConnection->query(
    "SELECT id, license, author, comment, ip, created_at, approved
     FROM CAR_COMMENTS ORDER BY created_at DESC LIMIT 200"
);
$all = $_all_res ? $_all_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<div class="content-box">
<h3>Reacties beheer</h3>

<?= $msg ?>

<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px;">
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="action" value="toggle_comments" />
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                background:var(--color-input-bg);border:1px solid var(--color-content-border);
                border-radius:6px;">
        <span style="font-size:13px;font-weight:bold;min-width:160px;">Reacties systeem:</span>
        <span style="font-size:13px;color:<?= $enabled ? '#2a8a2a' : '#c04040' ?>;font-weight:bold;">
            <?= $enabled ? '✓ Ingeschakeld' : '✗ Uitgeschakeld' ?>
        </span>
        <input type="submit" value="<?= $enabled ? 'Uitschakelen' : 'Inschakelen' ?>"
               class="btn <?= $enabled ? 'btn-ghost' : '' ?>" style="padding:4px 14px;" />
    </div>
</form>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="action" value="toggle_video" />
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                background:var(--color-input-bg);border:1px solid var(--color-content-border);
                border-radius:6px;">
        <span style="font-size:13px;font-weight:bold;min-width:160px;">Video embedding:</span>
        <span style="font-size:13px;color:<?= $video_enabled ? '#2a8a2a' : '#c04040' ?>;font-weight:bold;">
            <?= $video_enabled ? '✓ Ingeschakeld' : '✗ Uitgeschakeld' ?>
        </span>
        <input type="submit" value="<?= $video_enabled ? 'Uitschakelen' : 'Inschakelen' ?>"
               class="btn <?= $video_enabled ? 'btn-ghost' : '' ?>" style="padding:4px 14px;" />
        <span style="font-size:11px;color:#888;">(YouTube &amp; Vimeo)</span>
    </div>
</form>
</div>

<?php if (empty($all)): ?>
<p style="color:#888;">Nog geen reacties geplaatst.</p>
<?php else: ?>
<div style="font-size:11px;color:#5a7a90;margin-bottom:8px;">
    <?= count($all) ?> reactie<?= count($all) !== 1 ? 's' : '' ?> gevonden
</div>
<table style="border-collapse:collapse;width:100%;font-size:12px;">
<thead>
<tr style="background:var(--color-nav-hover-bg);font-weight:bold;">
    <td style="padding:6px 10px;">Datum</td>
    <td style="padding:6px 10px;">Kenteken</td>
    <td style="padding:6px 10px;">Naam</td>
    <td style="padding:6px 10px;">Reactie</td>
    <td style="padding:6px 10px;">IP</td>
    <td style="padding:6px 10px;">Status</td>
    <td style="padding:6px 10px;">Acties</td>
</tr>
</thead>
<tbody>
<?php foreach ($all as $i => $c):
    $alt = $i % 2 === 0;
?>
<tr style="background:<?= $alt ? 'var(--color-input-bg)' : 'var(--color-surface)' ?>;
           border-bottom:1px solid var(--color-nav-border);
           <?= !$c['approved'] ? 'opacity:0.6;' : '' ?>">
    <td style="padding:6px 10px;white-space:nowrap;"><?= htmlspecialchars(substr($c['created_at'], 0, 16)) ?></td>
    <td style="padding:6px 10px;white-space:nowrap;">
        <a href="index.php?navigate=<?= urlencode($c['license']) ?>" style="color:var(--color-link);">
            <?= htmlspecialchars($c['license']) ?>
        </a>
    </td>
    <td style="padding:6px 10px;"><?= htmlspecialchars($c['author'] ?: '—') ?></td>
    <td style="padding:6px 10px;max-width:280px;"><?= nl2br(htmlspecialchars($c['comment'])) ?></td>
    <td style="padding:6px 10px;color:#888;"><?= htmlspecialchars($c['ip']) ?></td>
    <td style="padding:6px 10px;font-weight:bold;
               color:<?= $c['approved'] ? '#2a8a2a' : '#c04040' ?>;">
        <?= $c['approved'] ? '✓ Zichtbaar' : '✗ Verborgen' ?>
    </td>
    <td style="padding:6px 10px;white-space:nowrap;">
        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="action" value="toggle" />
            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>" />
            <input type="submit" value="<?= $c['approved'] ? 'Verberg' : 'Toon' ?>"
                   class="btn btn-ghost" style="padding:3px 8px;font-size:11px;" />
        </form>
        <form method="post" style="display:inline;margin-left:4px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="action" value="delete" />
            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>" />
            <input type="submit" value="Verwijder"
                   class="btn" style="padding:3px 8px;font-size:11px;background:#c04040;border-color:#c04040;"
                   onclick="return confirm('Reactie definitief verwijderen?')" />
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</div>
