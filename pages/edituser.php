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
users_ensure_table($SNLDBConnection);

$selected_id = isset($_POST['userid']) ? intval($_POST['userid']) : 0;

if ($selected_id > 0) {
    $user = users_get_by_id($SNLDBConnection, $selected_id);

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

    <p><strong>User ID:</strong> <?= (int)$user['id'] ?></p>

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
    // Show user selection list
    $result = $SNLDBConnection->query(
        "SELECT `id`,`email`,`realname`,`isadmin`,`is_member` FROM `USERS` ORDER BY `email` ASC"
    );
?>
<h4>Select a user to edit:</h4>
<form method="post" action="index.php?navigate=edituser">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <select name="userid" required style="width:420px;padding:5px;font-size:14px;">
        <option value="">-- Select user --</option>
        <?php while ($row = $result->fetch_assoc()): ?>
        <option value="<?= (int)$row['id'] ?>">
            <?= htmlspecialchars($row['email']) ?>
            <?= $row['realname'] ? ' — ' . htmlspecialchars($row['realname']) : '' ?>
            <?= $row['isadmin'] ? ' [ADMIN]' : '' ?>
            <?= $row['is_member'] ? ' [MEMBER]' : '' ?>
        </option>
        <?php endwhile; ?>
    </select>
    <br><br>
    <input type="submit" value="Edit" class="btn" style="padding:8px 18px;" />
    <button type="button" onclick="location.href='index.php?navigate=adminpanel'"
            style="padding:8px 18px;margin-left:10px;">Back to admin panel</button>
</form>
<?php } ?>

<?php mysqli_close($SNLDBConnection); ?>
</div>
