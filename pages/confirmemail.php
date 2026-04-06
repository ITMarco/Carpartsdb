<?php
include 'connection.php';
include_once 'users_helper.php';

users_ensure_table($CarpartsConnection);

$token = trim($_GET['token'] ?? '');

if (mb_strlen($token) !== 64 || !ctype_xdigit($token)) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'>
            <h3>Invalid confirmation link</h3>
            <p>This confirmation link is not valid. It may have been copied incorrectly.</p>
            <p><a href='index.php?navigate=signup'>Sign up again</a></p>
          </div>";
    return;
}

$user = users_get_by_token($CarpartsConnection, $token);

if (!$user) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'>
            <h3>Link already used or not found</h3>
            <p>This confirmation link has already been used, or it does not exist.</p>
            <p>If your account is already confirmed, you can <a href='index.php?navigate=secureadmin'>log in</a>.</p>
          </div>";
    return;
}

users_confirm($CarpartsConnection, (int)$user['id']);
mysqli_close($CarpartsConnection);

// Set a one-time flash in session so the login page can show a welcome message
$_SESSION['signup_confirmed'] = htmlspecialchars($user['realname'] ?: $user['email']);
?>
<div class="content-box">
    <h3 style="color:var(--color-accent);">&#10003; Email confirmed!</h3>
    <p>
        Welcome, <strong><?= htmlspecialchars($user['realname'] ?: $user['email']) ?></strong>!<br>
        Your account is now active. You can log in and start listing parts.
    </p>
    <p style="margin-top:14px;">
        <a href="index.php?navigate=secureadmin" class="btn" style="padding:9px 24px;">Log in</a>
    </p>
</div>
