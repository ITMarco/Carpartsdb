<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied. <a href='index.php?navigate=secureadmin'>Log in as admin</a>.</div>";
    return;
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'settings_helper.php';
include_once 'comment_helper.php';

parts_ensure_table($CarpartsConnection);

// Unread flag count
$flag_count = 0;
$fr = $CarpartsConnection->query("SELECT COUNT(*) FROM `PART_FLAGS` WHERE `resolved`=0");
if ($fr) { $flag_count = (int)$fr->fetch_row()[0]; }

// Unread comment count
comment_ensure_table($CarpartsConnection);
$last_seen = settings_get($CarpartsConnection, 'comments_last_seen', '2000-01-01 00:00:00');
$comment_count = 0;
$cr = $CarpartsConnection->prepare("SELECT COUNT(*) FROM `CAR_COMMENTS` WHERE `created_at` > ?");
if ($cr) {
    $cr->bind_param('s', $last_seen);
    $cr->execute();
    $cr->bind_result($comment_count);
    $cr->fetch();
    $cr->close();
}

// Unread message count
$msg_count = 0;
try {
    $mr = $CarpartsConnection->prepare(
        "SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `parent_id` IS NULL AND `is_read` = 0"
    );
    if ($mr) {
        $mr->execute();
        $mr->bind_result($msg_count);
        $mr->fetch();
        $mr->close();
    }
} catch (\Throwable $e) { /* table not yet migrated */ }

mysqli_close($CarpartsConnection);
?>
<div class="content-box">
    <h3>Admin Panel</h3>

    <div style="display:flex;gap:24px;margin-top:10px;flex-wrap:wrap;">

        <div style="flex:1;min-width:200px;">
            <h2>Parts Management:</h2>
            <ul>
                <li><a href="index.php?navigate=addpart">Add new part listing</a></li>
                <li><a href="index.php?navigate=browse">Browse all parts</a></li>
            </ul>

            <h2>User Management:</h2>
            <ul>
                <li><a href="index.php?navigate=insertuser">Add new user</a></li>
                <li><a href="index.php?navigate=edituser">Edit / delete user</a></li>
            </ul>

            <h2>Catalogue:</h2>
            <ul>
                <li><a href="index.php?navigate=adminmakes">Manage car makes &amp; models</a></li>
            </ul>
        </div>

        <div style="flex:1;min-width:200px;">
            <h2>Statistics:</h2>
            <ul>
                <li><a href="index.php?navigate=carstats">View statistics</a></li>
                <li><a href="index.php?navigate=exportparts">Export parts to CSV</a></li>
                <li><a href="index.php?navigate=ipwhitelist">IP whitelist</a></li>
            </ul>

            <h2>Moderation:</h2>
            <ul>
                <li>
                    <a href="index.php?navigate=flagadmin">Reported listings</a>
                    <?php if ($flag_count > 0): ?>
                    <span style="display:inline-block;background:#c04040;color:#fff;border-radius:10px;
                                 padding:1px 8px;font-size:11px;font-weight:bold;margin-left:4px;">
                        <?= $flag_count ?>
                    </span>
                    <?php endif; ?>
                </li>
                <li>
                    <a href="index.php?navigate=commentadmin">Comment moderation</a>
                    <?php if ($comment_count > 0): ?>
                    <span style="display:inline-block;background:#c04040;color:#fff;border-radius:10px;
                                 padding:1px 8px;font-size:11px;font-weight:bold;margin-left:4px;">
                        <?= $comment_count ?>
                    </span>
                    <?php endif; ?>
                </li>
                <li>
                    <a href="index.php?navigate=adminmessages">Message moderation &amp; limits</a>
                    <?php if ($msg_count > 0): ?>
                    <span style="display:inline-block;background:#c04040;color:#fff;border-radius:10px;
                                 padding:1px 8px;font-size:11px;font-weight:bold;margin-left:4px;">
                        <?= $msg_count ?>
                    </span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>Appearance:</h2>
            <ul>
                <li><a href="index.php?navigate=themeadmin">Theme &amp; colour management</a></li>
            </ul>

            <h2>Content:</h2>
            <ul>
                <li><a href="index.php?navigate=homenews">Home page news items</a></li>
            </ul>
        </div>

    </div>
</div>
