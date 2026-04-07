               </div>

        </div>

        <div id="footer2">&nbsp;</div>
        </div>

</div>
<div id="footer">
<?php
// Show logout link if authenticated, otherwise show admin login
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // Admin panel link — admins only
    if (!empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1) {
        echo '<a href="index.php?navigate=adminpanel">Admin panel</a>';

        // New-comment badge for admins
        include_once 'settings_helper.php';
        include_once 'comment_helper.php';
        if (!isset($CarpartsConnection)) include_once 'connection.php';
        comment_ensure_table($CarpartsConnection);
        $last_seen = settings_get($CarpartsConnection, 'comments_last_seen', '2000-01-01 00:00:00');
        $badge_stmt = $CarpartsConnection->prepare(
            "SELECT COUNT(*) FROM CAR_COMMENTS WHERE created_at > ?"
        );
        $new_count = 0;
        if ($badge_stmt) {
            $badge_stmt->bind_param('s', $last_seen);
            $badge_stmt->execute();
            $badge_stmt->bind_result($new_count);
            $badge_stmt->fetch();
            $badge_stmt->close();
        }
        if ($new_count > 0) {
            echo ' <a href="index.php?navigate=commentadmin"'
               . ' style="display:inline-block;background:#c04040;color:#fff;border-radius:10px;'
               . 'padding:1px 8px;font-size:11px;font-weight:bold;text-decoration:none;vertical-align:middle;"'
               . ' title="Nieuwe reacties">'
               . intval($new_count) . ' nieuwe reactie' . ($new_count !== 1 ? 's' : '')
               . '</a>';
        }
        echo ' | ';
    }

    echo '<a href="index.php?navigate=userprofile&id=' . (int)($_SESSION['user_id'] ?? 0) . '">My profile</a>';
    echo ' | <a href="index.php?navigate=logout">Logout</a>';
    if (isset($_SESSION['username'])) {
        echo ' (' . htmlspecialchars($_SESSION['username']) . ')';
    }
} else {
    echo '<a href="index.php?navigate=secureadmin">Login</a>';
    echo ' | <a href="index.php?navigate=signup">Sign up</a>';
}
echo ' | <a href="index.php?navigate=privacyverklaring">Privacyverklaring</a>';
?>
</div>

<div style="height:80px;"></div>

<?php
// ── Seller unread-message notification bar ────────────────────────────────────
if (!empty($_SESSION['authenticated']) && !empty($_SESSION['user_id'])) {
    if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
    include_once 'config.php';
    if (!isset($CarpartsConnection)) {
        $CarpartsConnection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    if (!$CarpartsConnection->connect_error) {
        $seller_uid = (int)$_SESSION['user_id'];
        $unread_stmt = $CarpartsConnection->prepare(
            "SELECT COUNT(*) FROM `PART_MESSAGES` pm
             JOIN `PARTS` p ON p.`id` = pm.`part_id`
             WHERE p.`seller_id` = ? AND pm.`read_at` IS NULL AND pm.`sender_id` != ?"
        );
        $unread_count = 0;
        if ($unread_stmt) {
            $unread_stmt->bind_param('ii', $seller_uid, $seller_uid);
            $unread_stmt->execute();
            $unread_stmt->bind_result($unread_count);
            $unread_stmt->fetch();
            $unread_stmt->close();
        }
        if ($unread_count > 0):
?>
<div id="msg-notify-bar"
     style="position:fixed;bottom:0;left:0;right:0;z-index:9998;
            background:#c87020;color:#fff;text-align:center;padding:10px 16px;
            font-size:14px;font-weight:bold;cursor:pointer;box-shadow:0 -2px 8px rgba(0,0,0,0.25);"
     onclick="location.href='index.php?navigate=mymessages'">
    &#9993; You have <?= $unread_count ?> unread message<?= $unread_count !== 1 ? 's' : '' ?> &mdash; click to view
    <span style="float:right;font-size:18px;font-weight:normal;line-height:1;"
          onclick="event.stopPropagation();this.parentElement.style.display='none';">&#10005;</span>
</div>
<?php
        endif;
    }
}

// ── Theme picker widget ───────────────────────────────────────────────────────
$_picker_themes  = $GLOBALS['_public_themes'] ?? [];
$_active_user_id = $GLOBALS['_user_theme_id'] ?? 0;
if (!empty($_picker_themes)):
    // Find the site-active theme id for indicator
    $_site_active_id = 0;
    foreach ($_picker_themes as $_pt) {
        if ($_pt['is_active']) { $_site_active_id = (int)$_pt['id']; break; }
    }
    $displayed_id = $_active_user_id ?: $_site_active_id;
?>
<style>
#snl-theme-picker { position:fixed; bottom:90px; right:16px; z-index:9999; font-family:Arial,sans-serif; font-size:13px; }
#snl-picker-btn {
    width:42px; height:42px; border-radius:50%; border:none; cursor:pointer;
    background:var(--btn-bg); color:var(--btn-text);
    box-shadow:0 2px 8px rgba(0,0,0,0.25); font-size:18px; line-height:1;
    display:flex; align-items:center; justify-content:center;
    transition:opacity .15s;
}
#snl-picker-btn:hover { opacity:.85; }
#snl-picker-panel {
    display:none; position:absolute; bottom:50px; right:0;
    background:var(--color-surface); border:1px solid var(--color-content-border);
    border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,0.18);
    min-width:200px; padding:10px 0; overflow:hidden;
}
#snl-picker-panel.open { display:block; }
.snl-picker-title {
    font-size:10px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;
    color:var(--color-accent); padding:4px 14px 8px; border-bottom:1px solid var(--color-nav-border);
    margin-bottom:4px;
}
.snl-picker-item {
    display:flex; align-items:center; gap:10px; padding:7px 14px;
    cursor:pointer; transition:background .1s;
}
.snl-picker-item:hover { background:var(--color-nav-hover-bg); }
.snl-picker-item.active { font-weight:bold; }
.snl-swatches { display:flex; gap:3px; flex-shrink:0; }
.snl-swatch { width:14px; height:14px; border-radius:50%; border:1px solid rgba(0,0,0,0.12); }
.snl-picker-name { flex:1; color:var(--color-text); font-size:12px; }
.snl-picker-check { color:var(--color-accent); font-size:13px; flex-shrink:0; }
.snl-picker-reset {
    margin:6px 14px 2px; padding:5px 0; font-size:11px; text-align:center;
    color:var(--color-link); cursor:pointer; border-top:1px solid var(--color-nav-border);
    padding-top:8px;
}
.snl-picker-reset:hover { text-decoration:underline; }
</style>

<div id="snl-theme-picker">
    <button id="snl-picker-btn" title="Kies een thema">🎨</button>
    <div id="snl-picker-panel">
        <div class="snl-picker-title">Kies een thema</div>
        <?php foreach ($_picker_themes as $_pt):
            $vars    = $_pt['vars_arr'];
            $bg      = htmlspecialchars($vars['--color-body-bg']   ?? '#eee');
            $btn     = htmlspecialchars($vars['--btn-bg']           ?? '#555');
            $surface = htmlspecialchars($vars['--color-surface']    ?? '#fff');
            $active  = ((int)$_pt['id'] === $displayed_id);
        ?>
        <div class="snl-picker-item<?= $active ? ' active' : '' ?>"
             data-theme-id="<?= (int)$_pt['id'] ?>"
             data-is-dark="<?= (int)($_pt['is_dark'] ?? 0) ?>"
             onclick="snlSetTheme(<?= (int)$_pt['id'] ?>)">
            <div class="snl-swatches">
                <div class="snl-swatch" style="background:<?= $bg ?>;"   title="Achtergrond"></div>
                <div class="snl-swatch" style="background:<?= $surface ?>;border:1px solid rgba(0,0,0,.15);" title="Vlak"></div>
                <div class="snl-swatch" style="background:<?= $btn ?>;"  title="Knop"></div>
            </div>
            <span class="snl-picker-name"><?= htmlspecialchars($_pt['name']) ?></span>
            <?php if ($active): ?><span class="snl-picker-check">✓</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($_active_user_id > 0): ?>
        <div class="snl-picker-reset" onclick="snlResetTheme()">↩ Site standaard</div>
        <?php endif; ?>
    </div>
</div>

<script>
var _snlLoggedIn = <?= !empty($_SESSION['authenticated']) ? 'true' : 'false' ?>;
document.getElementById('snl-picker-btn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('snl-picker-panel').classList.toggle('open');
});
document.addEventListener('click', function() {
    document.getElementById('snl-picker-panel').classList.remove('open');
});
function snlSetTheme(id) {
    var item = document.querySelector('.snl-picker-item[data-theme-id="' + id + '"]');
    var isDark = item ? item.dataset.isDark : '0';
    document.cookie = 'snldb_theme=' + id + '; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
    document.cookie = 'snldb_theme_dark=' + isDark + '; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
    if (_snlLoggedIn) {
        fetch('index.php?navigate=savetheme&ajax=1&theme_id=' + id).catch(function(){});
    }
    location.reload();
}
function snlResetTheme() {
    document.cookie = 'snldb_theme=; path=/; max-age=0; SameSite=Lax';
    document.cookie = 'snldb_theme_dark=; path=/; max-age=0; SameSite=Lax';
    if (_snlLoggedIn) {
        fetch('index.php?navigate=savetheme&ajax=1&theme_id=0').catch(function(){});
    }
    location.reload();
}
</script>
<?php endif; ?>

</body>
</html>