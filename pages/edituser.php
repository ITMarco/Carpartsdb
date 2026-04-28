<div class="content-box">
    <h3>Edit user</h3>
    <br>
<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied. <a href='index.php?navigate=secureadmin'>Log in as admin</a>.</div></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'users_helper.php';
users_ensure_table($CarpartsConnection);

$selected_id = isset($_POST['userid']) ? intval($_POST['userid']) : 0;

if ($selected_id > 0) {
    // Fetch full row including confirmation status
    $ustmt = $CarpartsConnection->prepare(
        "SELECT `id`,`email`,`realname`,`isadmin`,`is_member`,
                COALESCE(`inbox_unlimited`,0) AS `inbox_unlimited`,
                COALESCE(`is_confirmed`,1) AS `is_confirmed`, `created_at`
         FROM `USERS` WHERE `id` = ? LIMIT 1"
    );
    $ustmt->bind_param('i', $selected_id);
    $ustmt->execute();
    $user = $ustmt->get_result()->fetch_assoc();
    $ustmt->close();

    if ($user):
?>
<script>
function generatePassword(fieldId) {
    var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    var pw = '';
    for (var i = 0; i < 14; i++) pw += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById(fieldId).value = pw;
}
function confirmDelete() {
    return confirm('Delete this user? This cannot be undone.');
}
</script>

<h3>Edit: <?= htmlspecialchars($user['email']) ?></h3>

<form method="post" action="index.php?navigate=processedituser">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="userid" value="<?= (int)$user['id'] ?>" />

    <p><strong>User ID:</strong> <?= (int)$user['id'] ?> &nbsp;&nbsp;
    <strong>Registered:</strong> <?= htmlspecialchars(substr($user['created_at'], 0, 10)) ?> &nbsp;&nbsp;
    <strong>Status:</strong>
    <?php if ($user['is_confirmed']): ?>
        <span style="color:#2a7a2a;font-weight:bold;">&#10003; Confirmed</span>
    <?php else: ?>
        <span style="color:#c87020;font-weight:bold;">&#9888; Pending confirmation</span>
        &nbsp;
        <form method="post" action="index.php?navigate=processedituser" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$user['id'] ?>" />
            <button type="submit" name="action" value="Confirm"
                    style="padding:2px 10px;font-size:12px;background:#2a7a2a;color:#fff;border:none;cursor:pointer;">
                Confirm now
            </button>
        </form>
        <form method="post" action="index.php?navigate=processedituser" style="display:inline;margin-left:4px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$user['id'] ?>" />
            <button type="submit" name="action" value="Resend"
                    style="padding:2px 10px;font-size:12px;background:#5588bb;color:#fff;border:none;cursor:pointer;">
                Resend email
            </button>
        </form>
    <?php endif; ?>
    </p>

    <label for="email"><strong>Email address:</strong></label><br>
    <input type="email" id="email" name="email" maxlength="255" required
           value="<?= htmlspecialchars($user['email']) ?>" style="width:300px;padding:5px;" /><br><br>

    <label for="realname"><strong>Real name:</strong></label><br>
    <input type="text" id="realname" name="realname" maxlength="255"
           value="<?= htmlspecialchars($user['realname'] ?? '') ?>" style="width:300px;padding:5px;" /><br><br>

    <fieldset style="border:1px solid #ccc;padding:10px;margin:10px 0;">
        <legend><strong>New password:</strong></legend>
        <input type="text" id="password" name="password" maxlength="72" style="width:260px;padding:5px;"
               placeholder="Leave blank to keep current password" />
        <button type="button" onclick="generatePassword('password')" style="margin-left:8px;">Generate</button>
    </fieldset>

    <br>
    <label>
        <input type="checkbox" name="isadmin" value="1" <?= ($user['isadmin'] == 1) ? 'checked' : '' ?> />
        <strong>Admin user</strong>
    </label><br><br>

    <label>
        <input type="checkbox" name="is_member" value="1" <?= ($user['is_member'] == 1) ? 'checked' : '' ?> />
        <strong>Incrowd member</strong>
    </label><br><br>

    <label>
        <input type="checkbox" name="inbox_unlimited" value="1" <?= ($user['inbox_unlimited'] == 1) ? 'checked' : '' ?> />
        <strong>Unlimited inbox</strong> <small style="color:#666;font-weight:normal;">— inbox never fills up regardless of global limits</small>
    </label><br><br>

    <input type="submit" name="action" value="Save" class="btn" style="padding:8px 20px;" />
    <input type="submit" name="action" value="Delete" onclick="return confirmDelete();"
           style="padding:8px 20px;margin-left:10px;background:#dc3545;color:#fff;border:none;cursor:pointer;" />
    <button type="button" onclick="location.href='index.php?navigate=edituser'"
            style="padding:8px 20px;margin-left:10px;">Cancel</button>
</form>

<?php
    else:
        echo "<div style='color:red;'>User not found.</div>";
        echo "<p><a href='index.php?navigate=edituser'>Back to user list</a></p>";
    endif;

} else {
    // Show user list with enrollment status
    $result = $CarpartsConnection->query(
        "SELECT `id`,`email`,`realname`,`isadmin`,`is_member`,
                COALESCE(`is_confirmed`,1) AS `is_confirmed`,
                `created_at`
         FROM `USERS` ORDER BY `is_confirmed` ASC, `created_at` DESC"
    );
    $flash = $_GET['msg'] ?? '';
?>
<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #28a745;color:#155724;padding:10px 14px;
            border-radius:4px;margin-bottom:14px;font-size:13px;"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<h4 style="margin-bottom:10px;">Users</h4>
<table style="width:100%;border-collapse:collapse;font-size:13px;">
<tr style="background:var(--color-nav-hover-bg);">
    <th style="padding:6px 10px;text-align:left;">Email</th>
    <th style="padding:6px 10px;text-align:left;">Name</th>
    <th style="padding:6px 10px;text-align:center;">Status</th>
    <th style="padding:6px 10px;text-align:center;">Role</th>
    <th style="padding:6px 10px;text-align:left;">Registered</th>
    <th style="padding:6px 10px;text-align:center;">Actions</th>
</tr>
<?php $i=1; while ($row = $result->fetch_assoc()):
    $confirmed = (int)$row['is_confirmed'];
    $bg = $i % 2 ? 'var(--color-input-bg)' : 'var(--color-surface)';
?>
<tr style="background:<?= $bg ?>;border-top:1px solid var(--color-nav-border);">
    <td style="padding:6px 10px;"><?= htmlspecialchars($row['email']) ?></td>
    <td style="padding:6px 10px;color:#666;"><?= htmlspecialchars($row['realname'] ?? '') ?></td>
    <td style="padding:6px 10px;text-align:center;">
        <?php if ($confirmed): ?>
        <span style="color:#2a7a2a;font-weight:bold;font-size:12px;">&#10003; Confirmed</span>
        <?php else: ?>
        <span style="color:#c87020;font-weight:bold;font-size:12px;">&#9888; Pending</span>
        <?php endif; ?>
    </td>
    <td style="padding:6px 10px;text-align:center;font-size:11px;">
        <?= $row['isadmin']   ? '<span style="color:#c04040;font-weight:bold;">ADMIN</span> ' : '' ?>
        <?= $row['is_member'] ? '<span style="color:#448;font-weight:bold;">MEMBER</span>' : '' ?>
        <?= (!$row['isadmin'] && !$row['is_member']) ? '<span style="color:#999;">User</span>' : '' ?>
    </td>
    <td style="padding:6px 10px;color:#888;font-size:12px;"><?= htmlspecialchars(substr($row['created_at'], 0, 10)) ?></td>
    <td style="padding:6px 10px;text-align:center;white-space:nowrap;">
        <!-- Edit -->
        <form method="post" action="index.php?navigate=edituser" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$row['id'] ?>" />
            <button type="submit" style="padding:3px 10px;font-size:12px;cursor:pointer;">Edit</button>
        </form>
        <?php if (!$confirmed): ?>
        <!-- Confirm by hand -->
        <form method="post" action="index.php?navigate=processedituser" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$row['id'] ?>" />
            <button type="submit" name="action" value="Confirm"
                    style="padding:3px 10px;font-size:12px;cursor:pointer;background:#2a7a2a;color:#fff;border:none;">
                Confirm
            </button>
        </form>
        <!-- Resend email -->
        <form method="post" action="index.php?navigate=processedituser" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$row['id'] ?>" />
            <button type="submit" name="action" value="Resend"
                    style="padding:3px 10px;font-size:12px;cursor:pointer;background:#5588bb;color:#fff;border:none;">
                Resend email
            </button>
        </form>
        <!-- Delete unconfirmed -->
        <form method="post" action="index.php?navigate=processedituser" style="display:inline;"
              onsubmit="return confirm('Delete this unconfirmed user?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="userid" value="<?= (int)$row['id'] ?>" />
            <button type="submit" name="action" value="Delete"
                    style="padding:3px 10px;font-size:12px;cursor:pointer;background:#dc3545;color:#fff;border:none;">
                Delete
            </button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php $i++; endwhile; ?>
</table>
<p style="margin-top:16px;">
    <a href="index.php?navigate=insertuser" class="btn" style="padding:7px 18px;">+ Add user</a>
    <button type="button" onclick="location.href='index.php?navigate=adminpanel'"
            style="padding:7px 18px;margin-left:10px;">Back to admin panel</button>
</p>
<?php } ?>

<?php mysqli_close($CarpartsConnection); ?>
</div>
