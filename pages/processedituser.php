<div class="content-box">
    <h3>Edit user — result</h3>
    <br>
<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied.</div></div>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div style='color:red;'>Invalid request.</div></div>";
    return;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo "<div style='color:red;'>Security validation failed.</div></div>";
    return;
}

include 'connection.php';
include_once 'users_helper.php';
include_once 'settings_helper.php';
users_ensure_table($CarpartsConnection);

$userid = isset($_POST['userid']) ? intval($_POST['userid']) : 0;
$action = $_POST['action'] ?? '';

if ($userid <= 0) {
    echo "<div style='color:red;'>Invalid user ID.</div>";
    echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

if ($action === 'Confirm') {
    users_confirm($CarpartsConnection, $userid);
    mysqli_close($CarpartsConnection);
    header('Location: index.php?navigate=edituser&msg=' . urlencode('User confirmed successfully.'));
    exit();

} elseif ($action === 'Resend') {
    // Fetch the user to get email/name and regenerate token
    $u = users_get_by_id($CarpartsConnection, $userid);
    if (!$u) {
        echo "<div style='color:red;'>User not found.</div><p><a href='index.php?navigate=edituser'>Back</a></p></div>";
        mysqli_close($CarpartsConnection);
        return;
    }
    $token = bin2hex(random_bytes(32));
    $upd = $CarpartsConnection->prepare("UPDATE `USERS` SET `confirmation_token`=?, `is_confirmed`=0 WHERE `id`=?");
    $upd->bind_param('si', $token, $userid);
    $upd->execute();
    $upd->close();

    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base        = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $confirm_url = "{$scheme}://{$host}{$base}/index.php?navigate=confirmemail&token=" . urlencode($token);
    $site_name   = settings_get($CarpartsConnection, 'site_name', 'Car Parts DB');
    $mail_from   = settings_get($CarpartsConnection, 'mail_from',  'noreply@supraclub.nl');

    $subject = "Confirm your {$site_name} account";
    $body    = "Hi " . ($u['realname'] ?: $u['email']) . ",\r\n\r\n"
             . "An admin has resent your confirmation link for {$site_name}:\r\n\r\n"
             . "{$confirm_url}\r\n\r\n"
             . "— {$site_name}";
    $headers = implode("\r\n", [
        "From: {$site_name} <{$mail_from}>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ]);
    mail($u['email'], $subject, $body, $headers);

    mysqli_close($CarpartsConnection);
    header('Location: index.php?navigate=edituser&msg=' . urlencode('Confirmation email resent to ' . $u['email'] . '.'));
    exit();

} elseif ($action === 'Delete') {
    $stmt = $CarpartsConnection->prepare("DELETE FROM `USERS` WHERE `id` = ?");
    $stmt->bind_param('i', $userid);
    if ($stmt->execute()) {
        $stmt->close();
        mysqli_close($CarpartsConnection);
        header('Location: index.php?navigate=edituser&msg=' . urlencode('User deleted.'));
        exit();
    } else {
        echo "<div style='color:red;'>Error deleting user: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();

} elseif ($action === 'Save') {
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $realname         = trim($_POST['realname'] ?? '');
    $password         = $_POST['password'] ?? '';
    $isadmin          = isset($_POST['isadmin'])          && $_POST['isadmin']          == '1' ? 1 : 0;
    $is_member        = isset($_POST['is_member'])        && $_POST['is_member']        == '1' ? 1 : 0;
    $inbox_unlimited  = isset($_POST['inbox_unlimited'])  && $_POST['inbox_unlimited']  == '1' ? 1 : 0;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<div style='color:red;'>Valid email address is required.</div>";
        echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
        mysqli_close($CarpartsConnection);
        return;
    }

    // Duplicate email check (excluding current user)
    $chk = $CarpartsConnection->prepare("SELECT `id` FROM `USERS` WHERE `email` = ? AND `id` != ? LIMIT 1");
    $chk->bind_param('si', $email, $userid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo "<div style='color:red;'>This email address is already in use by another user.</div>";
        echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
        $chk->close();
        mysqli_close($CarpartsConnection);
        return;
    }
    $chk->close();

    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo "<div style='color:red;'>Password must be at least 6 characters.</div>";
            echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
            mysqli_close($CarpartsConnection);
            return;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $CarpartsConnection->prepare(
            "UPDATE `USERS` SET `email`=?,`realname`=?,`password`=?,`isadmin`=?,`is_member`=?,`inbox_unlimited`=? WHERE `id`=?"
        );
        $stmt->bind_param('sssiiii', $email, $realname, $hashed, $isadmin, $is_member, $inbox_unlimited, $userid);
    } else {
        $stmt = $CarpartsConnection->prepare(
            "UPDATE `USERS` SET `email`=?,`realname`=?,`isadmin`=?,`is_member`=?,`inbox_unlimited`=? WHERE `id`=?"
        );
        $stmt->bind_param('ssiiii', $email, $realname, $isadmin, $is_member, $inbox_unlimited, $userid);
    }

    if ($stmt->execute()) {
        echo "<div style='background:#d4edda;border:1px solid #28a745;padding:12px;border-radius:4px;'>";
        echo "<strong>User updated successfully.</strong><br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Admin: " . ($isadmin ? 'Yes' : 'No') . " | Member: " . ($is_member ? 'Yes' : 'No') . " | Unlimited inbox: " . ($inbox_unlimited ? 'Yes' : 'No');
        echo "</div>";
    } else {
        echo "<div style='color:red;'>Error updating user: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();

} else {
    echo "<div style='color:red;'>Unknown action.</div>";
}

mysqli_close($CarpartsConnection);
?>
<p>
    <a href="index.php?navigate=edituser">Edit another user</a> |
    <a href="index.php?navigate=insertuser">Add user</a> |
    <a href="index.php?navigate=adminpanel">Admin panel</a>
</p>
</div>
