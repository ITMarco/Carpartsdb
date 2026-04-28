<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a>.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'users_helper.php';
users_ensure_table($CarpartsConnection);

$user_id = (int)$_SESSION['user_id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "<div class='content-box'><p style='color:red;'>Security validation failed.</p></div>";
        mysqli_close($CarpartsConnection);
        return;
    }

    $realname  = trim($_POST['realname']  ?? '');
    $phone     = trim($_POST['phone']     ?? '') ?: null;
    $address   = trim($_POST['address']   ?? '') ?: null;
    $bio       = trim($_POST['bio']       ?? '') ?: null;
    $show_cont = isset($_POST['show_contact_to_members']) ? 1 : 0;

    $stmt = $CarpartsConnection->prepare(
        "UPDATE `USERS`
         SET `realname`=?, `phone`=?, `address`=?, `bio`=?, `show_contact_to_members`=?
         WHERE `id`=?"
    );
    $stmt->bind_param('ssssii', $realname, $phone, $address, $bio, $show_cont, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update session display name
    $_SESSION['username'] = $realname ?: $_SESSION['user_email'];

    mysqli_close($CarpartsConnection);
    $dest = 'index.php?navigate=userprofile&id=' . $user_id . '&saved=1';
    echo "<div class='content-box'><p>Profile saved. <a href='" . htmlspecialchars($dest) . "'>Back to profile &rarr;</a></p>"
       . "<script>window.location.replace('" . addslashes($dest) . "');</script></div>";
    exit();
}

// Load current values
$stmt = $CarpartsConnection->prepare(
    "SELECT `realname`,`email`,`phone`,`address`,`bio`,`show_contact_to_members`
     FROM `USERS` WHERE `id`=? LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Edit my profile</h3>

<form method="post" action="index.php?navigate=edituserprofile">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />

    <label><strong>Display name:</strong></label><br>
    <input type="text" name="realname" maxlength="255" style="width:320px;padding:5px;"
           value="<?= htmlspecialchars($u['realname'] ?? '') ?>" /><br><br>

    <label><strong>Phone number:</strong> <small style="color:#888;font-weight:normal;">shown to incrowd members (and optionally to all members)</small></label><br>
    <input type="text" name="phone" maxlength="40" style="width:220px;padding:5px;"
           value="<?= htmlspecialchars($u['phone'] ?? '') ?>" placeholder="e.g. +31 6 12345678" /><br><br>

    <label><strong>Address / location:</strong></label><br>
    <input type="text" name="address" maxlength="255" style="width:380px;padding:5px;"
           value="<?= htmlspecialchars($u['address'] ?? '') ?>" placeholder="City, region or full address" /><br><br>

    <label><strong>About me / bio:</strong> <small style="color:#888;font-weight:normal;">shown to all logged-in users</small></label><br>
    <textarea name="bio" rows="4" style="width:100%;max-width:480px;padding:5px;"
              placeholder="Tell something about yourself, your cars, what you collect…"><?= htmlspecialchars($u['bio'] ?? '') ?></textarea><br><br>

    <label>
        <input type="checkbox" name="show_contact_to_members" value="1"
               <?= !empty($u['show_contact_to_members']) ? 'checked' : '' ?> />
        <strong>Show phone &amp; address to all logged-in members</strong>
    </label><br>
    <small style="color:#666;margin-left:20px;">
        Phone and address are <em>always</em> visible to incrowd members and admins, regardless of this setting.<br>
        Anonymous visitors can <em>never</em> see contact info.
    </small><br><br>

    <input type="submit" value="Save profile" class="btn" style="padding:8px 22px;" />
    <a href="index.php?navigate=userprofile&id=<?= $user_id ?>"
       style="padding:8px 18px;margin-left:10px;">Cancel</a>
</form>
</div>
