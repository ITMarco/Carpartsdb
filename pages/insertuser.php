<div class="content-box">
    <h3>Add new user</h3>
    <br>
<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied. <a href='index.php?navigate=secureadmin'>Log in as admin</a>.</div></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<script>
function generatePassword(fieldId) {
    var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    var pw = '';
    for (var i = 0; i < 14; i++) pw += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById(fieldId).value = pw;
}
</script>

<form method="post" action="index.php?navigate=processinsertuser">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />

    <label for="email"><strong>Email address (login):</strong></label><br>
    <input type="email" id="email" name="email" maxlength="255" required style="width:300px;padding:5px;" /><br><br>

    <label for="realname"><strong>Real name:</strong></label><br>
    <input type="text" id="realname" name="realname" maxlength="255" style="width:300px;padding:5px;" />
    <small style="color:#666;">(optional)</small><br><br>

    <fieldset style="border:1px solid #ccc;padding:10px;margin:10px 0;">
        <legend><strong>Password:</strong></legend>
        <input type="text" id="password" name="password" maxlength="72" required style="width:260px;padding:5px;" />
        <button type="button" onclick="generatePassword('password')" style="margin-left:8px;">Generate</button><br>
        <small style="color:#666;">Will be stored as bcrypt hash.</small>
    </fieldset>

    <br>
    <label>
        <input type="checkbox" name="isadmin" value="1" />
        <strong>Admin user</strong>
        <small style="color:#666;">(access to admin panel)</small>
    </label><br><br>

    <label>
        <input type="checkbox" name="is_member" value="1" />
        <strong>Incrowd member</strong>
        <small style="color:#666;">(can see private listings)</small>
    </label><br><br>

    <div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;">
        <strong>Note:</strong> Record the password now — it will only be shown once.
    </div>

    <input type="submit" value="Create user" class="btn" style="padding:8px 20px;" />
    <button type="button" onclick="location.href='index.php?navigate=adminpanel'"
            style="padding:8px 20px;margin-left:10px;">Cancel</button>
</form>
</div>
