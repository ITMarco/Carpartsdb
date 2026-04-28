<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'settings_helper.php';

parts_ensure_table($CarpartsConnection);

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'save_settings') {
        $inbox_limit  = max(0, intval($_POST['msg_inbox_limit']  ?? 50));
        $thread_limit = max(0, intval($_POST['msg_thread_limit'] ?? 20));
        settings_set($CarpartsConnection, 'msg_inbox_limit',  (string)$inbox_limit);
        settings_set($CarpartsConnection, 'msg_thread_limit', (string)$thread_limit);
        mysqli_close($CarpartsConnection);
        header('Location: index.php?navigate=adminmessages&saved=1');
        exit();
    }

    if ($post_action === 'delete_message') {
        $msg_id = intval($_POST['msg_id'] ?? 0);
        if ($msg_id > 0) {
            // Deleting a top-level message also cascades to its replies (via DELETE + parent_id)
            $del = $CarpartsConnection->prepare(
                "DELETE FROM `PART_MESSAGES` WHERE `id`=? OR `parent_id`=?"
            );
            if ($del) { $del->bind_param('ii', $msg_id, $msg_id); $del->execute(); $del->close(); }
        }
        $back_part = intval($_POST['part_id'] ?? 0);
        mysqli_close($CarpartsConnection);
        header('Location: index.php?navigate=adminmessages&deleted=1' . ($back_part ? '&part=' . $back_part : ''));
        exit();
    }
}

// ── Load settings ─────────────────────────────────────────────────────────────
$inbox_limit  = (int)settings_get($CarpartsConnection, 'msg_inbox_limit',  50);
$thread_limit = (int)settings_get($CarpartsConnection, 'msg_thread_limit', 20);

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_q      = trim($_GET['q']       ?? '');
$filter_part   = intval($_GET['part']  ?? 0);
$filter_user   = intval($_GET['user']  ?? 0);
$filter_unread = isset($_GET['unread']) ? (int)$_GET['unread'] : -1;
$page          = max(1, intval($_GET['pg'] ?? 1));
$per_page      = 30;

$where  = ["pm.`parent_id` IS NULL"];
$params = [];
$types  = '';

if ($filter_q !== '') {
    $like = '%' . $filter_q . '%';
    $where[] = "(pm.`message` LIKE ? OR pm.`name` LIKE ? OR pm.`email` LIKE ? OR p.`title` LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'ssss';
}
if ($filter_part > 0)  { $where[] = "pm.`part_id`    = ?"; $params[] = $filter_part;  $types .= 'i'; }
if ($filter_user > 0)  { $where[] = "(pm.`sender_id` = ? OR pm.`recipient_id` = ?)"; $params[] = $filter_user; $params[] = $filter_user; $types .= 'ii'; }
if ($filter_unread === 1) { $where[] = "pm.`is_read` = 0"; }

$where_sql = implode(' AND ', $where);
$base_sql = "FROM `PART_MESSAGES` pm
             JOIN `PARTS` p  ON p.`id` = pm.`part_id`
             JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
             LEFT JOIN `USERS` us ON us.`id` = pm.`sender_id`
             LEFT JOIN `USERS` ur ON ur.`id` = pm.`recipient_id`
             WHERE {$where_sql}";

// Count
$total_rows = 0;
$cstmt = $CarpartsConnection->prepare("SELECT COUNT(*) {$base_sql}");
if ($cstmt) {
    if ($types) $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $cstmt->bind_result($total_rows);
    $cstmt->fetch();
    $cstmt->close();
}
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$offset = ($page - 1) * $per_page;

// Fetch threads
$threads = [];
$tstmt = $CarpartsConnection->prepare(
    "SELECT pm.`id`, pm.`part_id`, pm.`sender_id`, pm.`recipient_id`, pm.`is_read`,
            pm.`name` AS sender_name_raw, pm.`email` AS sender_email, pm.`message`, pm.`created_at`,
            us.`realname` AS sender_realname, us.`email` AS sender_reg_email,
            ur.`realname` AS recipient_name, ur.`email` AS recipient_email,
            p.`title` AS part_title, m.`name` AS make_name,
            (SELECT COUNT(*) FROM `PART_MESSAGES` r WHERE r.`parent_id` = pm.`id`) AS reply_count
     {$base_sql}
     ORDER BY pm.`created_at` DESC
     LIMIT ? OFFSET ?"
);
if ($tstmt) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $tstmt->bind_param($types . 'ii', ...$all_params);
    $tstmt->execute();
    $_tres = $tstmt->get_result();
    $threads = $_tres ? $_tres->fetch_all(MYSQLI_ASSOC) : [];
    $tstmt->close();
}

// Pre-load expanded thread if requested
$expand_id = intval($_GET['expand'] ?? 0);
$thread_messages = [];
if ($expand_id > 0) {
    $exq = $CarpartsConnection->prepare(
        "SELECT pm.`id`, pm.`parent_id`, pm.`sender_id`, pm.`name`, pm.`email`,
                pm.`message`, pm.`created_at`, pm.`is_read`,
                u.`realname` AS sender_realname
         FROM `PART_MESSAGES` pm
         LEFT JOIN `USERS` u ON u.`id` = pm.`sender_id`
         WHERE pm.`id` = ? OR pm.`parent_id` = ?
         ORDER BY pm.`created_at` ASC"
    );
    if ($exq) {
        $exq->bind_param('ii', $expand_id, $expand_id);
        $exq->execute();
        $_exres = $exq->get_result();
        $thread_messages = $_exres ? $_exres->fetch_all(MYSQLI_ASSOC) : [];
        $exq->close();
    }
}

mysqli_close($CarpartsConnection);

function admin_msg_url(array $overrides = []): string {
    global $filter_q, $filter_part, $filter_user, $filter_unread, $page;
    $cur = array_filter([
        'navigate'  => 'adminmessages',
        'q'         => $filter_q ?: null,
        'part'      => $filter_part  ?: null,
        'user'      => $filter_user  ?: null,
        'unread'    => $filter_unread >= 0 ? $filter_unread : null,
        'pg'        => $page > 1 ? $page : null,
    ], fn($v) => $v !== null);
    return 'index.php?' . http_build_query(array_merge($cur, $overrides));
}

$csrf = htmlspecialchars($_SESSION['csrf_token']);
?>

<div class="content-box">
<h3>Message Moderation</h3>

<?php if (!empty($_GET['saved'])): ?>
<div style="background:#d4edda;border:1px solid #28a745;padding:10px 14px;border-radius:4px;margin-bottom:14px;">
    Settings saved.
</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
<div style="background:#d4edda;border:1px solid #28a745;padding:10px 14px;border-radius:4px;margin-bottom:14px;">
    Message deleted.
</div>
<?php endif; ?>

<!-- ── Settings ─────────────────────────────────────────────────────────────── -->
<details style="margin-bottom:18px;border:1px solid var(--color-content-border);border-radius:5px;">
<summary style="padding:10px 14px;cursor:pointer;font-weight:bold;background:var(--color-nav-hover-bg);">
    &#9881; Message limits &amp; settings
</summary>
<div style="padding:14px;">
<form method="post" action="index.php?navigate=adminmessages">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
    <input type="hidden" name="action"     value="save_settings" />
    <div style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label style="font-size:12px;font-weight:bold;">Inbox limit per seller</label>
            <small style="display:block;color:#888;font-size:11px;margin-bottom:4px;">Max conversations a seller can receive. 0 = unlimited.</small>
            <input type="number" name="msg_inbox_limit" min="0" value="<?= $inbox_limit ?>"
                   style="width:100px;padding:5px;" />
        </div>
        <div>
            <label style="font-size:12px;font-weight:bold;">Thread depth limit</label>
            <small style="display:block;color:#888;font-size:11px;margin-bottom:4px;">Max messages per conversation (incl. first). 0 = unlimited.</small>
            <input type="number" name="msg_thread_limit" min="0" value="<?= $thread_limit ?>"
                   style="width:100px;padding:5px;" />
        </div>
        <div>
            <input type="submit" value="Save settings" class="btn" style="padding:7px 18px;" />
        </div>
    </div>
    <p style="font-size:11px;color:#888;margin-top:10px;">
        To give a specific user an unlimited inbox regardless of these limits, edit their account via
        <a href="index.php?navigate=edituser">User Management</a> and check "Unlimited inbox".
    </p>
</form>
</div>
</details>

<!-- ── Filter bar ────────────────────────────────────────────────────────────── -->
<form method="get" action="index.php" style="margin-bottom:12px;">
    <input type="hidden" name="navigate" value="adminmessages" />
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Search</label><br>
            <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>"
                   placeholder="Message / name / email / part…" style="width:200px;padding:5px;" />
        </div>
        <div>
            <label style="font-size:12px;">Only unread</label><br>
            <select name="unread" style="padding:5px;">
                <option value="-1" <?= $filter_unread < 0  ? 'selected' : '' ?>>All</option>
                <option value="1"  <?= $filter_unread === 1 ? 'selected' : '' ?>>Unread only</option>
            </select>
        </div>
        <div>
            <input type="submit" value="Filter" class="btn" style="padding:6px 14px;" />
            <a href="index.php?navigate=adminmessages" style="margin-left:8px;font-size:12px;">Reset</a>
        </div>
    </div>
</form>

<p style="font-size:12px;color:#666;margin-bottom:8px;">
    <?= number_format($total_rows) ?> conversation<?= $total_rows !== 1 ? 's' : '' ?>
    <?php if ($total_pages > 1): ?> &mdash; page <?= $page ?> of <?= $total_pages ?><?php endif; ?>
</p>

<!-- ── Conversation list ─────────────────────────────────────────────────────── -->
<?php if (empty($threads)): ?>
<p style="color:#888;">No conversations found.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="font-weight:bold;border-bottom:2px solid var(--color-content-border);background:var(--color-nav-hover-bg);">
    <td style="padding:6px 10px;">Part</td>
    <td style="padding:6px 10px;">From</td>
    <td style="padding:6px 10px;">To (seller)</td>
    <td style="padding:6px 10px;">Message</td>
    <td style="padding:6px 10px;white-space:nowrap;">Date</td>
    <td style="padding:6px 10px;text-align:center;">Replies</td>
    <td style="padding:6px 10px;">Actions</td>
</tr>
<?php foreach ($threads as $t):
    $unread   = !(bool)$t['is_read'];
    $bold     = $unread ? 'font-weight:bold;' : '';
    $from     = $t['sender_realname'] ?: $t['sender_name_raw'] ?: 'Guest';
    $from_em  = $t['sender_reg_email'] ?: $t['sender_email'] ?: '';
    $to_name  = $t['recipient_name']  ?: $t['recipient_email'] ?: '—';
    $preview  = mb_strimwidth($t['message'], 0, 70, '…');
    $expanded = ($expand_id === (int)$t['id']);
?>
<tr style="border-bottom:1px solid var(--color-content-border);<?= $bold ?><?= $expanded ? 'background:var(--color-nav-hover-bg);' : '' ?>">
    <td style="padding:5px 10px;">
        <a href="index.php?navigate=viewpart&id=<?= (int)$t['part_id'] ?>"><?= htmlspecialchars($t['make_name'] . ' — ' . $t['part_title']) ?></a>
        <br><small style="color:#aaa;font-weight:normal;"><?= sprintf('PART-%05d', $t['part_id']) ?></small>
    </td>
    <td style="padding:5px 10px;">
        <?= htmlspecialchars($from) ?>
        <?php if ($from_em): ?><br><small style="color:#888;font-weight:normal;"><?= htmlspecialchars($from_em) ?></small><?php endif; ?>
    </td>
    <td style="padding:5px 10px;"><?= htmlspecialchars($to_name) ?></td>
    <td style="padding:5px 10px;">
        <?= htmlspecialchars($preview) ?>
        <?php if ($unread): ?>
        <span style="display:inline-block;background:#c87020;color:#fff;border-radius:8px;
                     padding:0 6px;font-size:10px;font-weight:bold;vertical-align:middle;margin-left:4px;">new</span>
        <?php endif; ?>
    </td>
    <td style="padding:5px 10px;white-space:nowrap;font-size:12px;color:#888;font-weight:normal;">
        <?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?>
    </td>
    <td style="padding:5px 10px;text-align:center;font-weight:normal;"><?= (int)$t['reply_count'] ?></td>
    <td style="padding:5px 10px;white-space:nowrap;font-weight:normal;">
        <a href="<?= htmlspecialchars(admin_msg_url(['expand' => $expanded ? 0 : $t['id'], 'pg' => $page])) ?>"
           style="font-size:12px;"><?= $expanded ? 'Hide' : 'View' ?></a>
        &nbsp;
        <form method="post" action="index.php?navigate=adminmessages" style="display:inline;"
              onsubmit="return confirm('Delete this conversation and all its replies?');">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
            <input type="hidden" name="action"  value="delete_message" />
            <input type="hidden" name="msg_id"  value="<?= (int)$t['id'] ?>" />
            <input type="hidden" name="part_id" value="<?= (int)$t['part_id'] ?>" />
            <input type="submit" value="Delete"
                   style="font-size:12px;color:#c04040;background:none;border:none;cursor:pointer;padding:0;" />
        </form>
    </td>
</tr>
<?php if ($expanded && !empty($thread_messages)): ?>
<tr>
    <td colspan="7" style="padding:0;border-bottom:2px solid var(--color-content-border);">
        <div style="padding:10px 18px 14px;background:var(--color-input-bg,var(--color-surface));">
        <?php foreach ($thread_messages as $tm):
            $is_reply = ($tm['parent_id'] !== null);
            $tm_name  = $tm['sender_realname'] ?: $tm['name'] ?: 'Anonymous';
        ?>
        <div style="margin-bottom:8px;padding:8px 12px;
                    background:var(--color-surface);border-radius:4px;
                    border:1px solid var(--color-content-border);
                    <?= $is_reply ? 'margin-left:24px;border-left:3px solid #5588bb;' : '' ?>">
            <div style="font-size:11px;color:#888;margin-bottom:4px;">
                <?php if ($is_reply): ?><span style="color:#5588bb;">&#8618; Reply — </span><?php endif; ?>
                <strong><?= htmlspecialchars($tm_name) ?></strong>
                &mdash; <?= htmlspecialchars(substr($tm['created_at'], 0, 16)) ?>
                <?= $tm['is_read'] ? '' : ' <span style="color:#c87020;font-size:10px;">[unread]</span>' ?>
            </div>
            <div style="font-size:13px;line-height:1.5;"><?= nl2br(htmlspecialchars($tm['message'])) ?></div>
        </div>
        <?php endforeach; ?>
        </div>
    </td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</table>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="margin-top:12px;display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
    <?php if ($page > 1): ?>
    <a href="<?= htmlspecialchars(admin_msg_url(['pg' => $page - 1])) ?>"
       style="padding:4px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;">&#8592; Prev</a>
    <?php endif; ?>
    <?php
    for ($pi = 1; $pi <= $total_pages; $pi++):
        if ($pi === 1 || $pi === $total_pages || abs($pi - $page) <= 2):
    ?>
    <a href="<?= htmlspecialchars(admin_msg_url(['pg' => $pi])) ?>"
       style="padding:4px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;
              <?= $pi === $page ? 'font-weight:bold;background:var(--color-nav-hover-bg);' : '' ?>"><?= $pi ?></a>
    <?php elseif (abs($pi - $page) === 3): ?>
    <span style="padding:4px 4px;font-size:12px;color:#888;">&hellip;</span>
    <?php endif; endfor; ?>
    <?php if ($page < $total_pages): ?>
    <a href="<?= htmlspecialchars(admin_msg_url(['pg' => $page + 1])) ?>"
       style="padding:4px 10px;border:1px solid var(--color-content-border);border-radius:3px;font-size:12px;">Next &#8594;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<p style="margin-top:14px;">
    <a href="index.php?navigate=adminpanel" style="font-size:12px;">&#8592; Admin panel</a>
</p>
</div>
