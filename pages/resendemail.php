<?php
if (!empty($_SESSION['authenticated'])) {
    echo "<script>window.location.replace('index.php?navigate=browse');</script>";
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$prefill = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
$error   = isset($_GET['error'])   ? htmlspecialchars($_GET['error'])   : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            include 'connection.php';
            include_once 'users_helper.php';
            include_once 'settings_helper.php';

            users_ensure_table($CarpartsConnection);
            $user = users_get_by_email($CarpartsConnection, $email);

            if (!$user || (int)($user['is_confirmed'] ?? 1) !== 0) {
                // Don't reveal whether email exists; show generic message
                $success = "If that address is registered and unconfirmed, we've sent a new confirmation email.";
            } else {
                // Generate a new token and resend
                $token = bin2hex(random_bytes(32));
                $upd = $CarpartsConnection->prepare(
                    "UPDATE `USERS` SET `confirmation_token` = ? WHERE `id` = ?"
                );
                $upd->bind_param('si', $token, $user['id']);
                $upd->execute();
                $upd->close();

                $site_url   = _resend_site_url();
                $confirm_url = $site_url . '/index.php?navigate=confirmemail&token=' . urlencode($token);
                $site_name  = settings_get($CarpartsConnection, 'site_name', 'Car Parts DB');
                $mail_from  = settings_get($CarpartsConnection, 'mail_from',  'noreply@supraclub.nl');

                $subject = "Confirm your {$site_name} account";
                $body    = "Hi " . ($user['realname'] ?: $email) . ",\r\n\r\n"
                         . "Here is a new confirmation link for your {$site_name} account:\r\n\r\n"
                         . "{$confirm_url}\r\n\r\n"
                         . "If you did not sign up, you can safely ignore this email.\r\n\r\n"
                         . "— {$site_name}";
                $headers = implode("\r\n", [
                    "From: {$site_name} <{$mail_from}>",
                    "Reply-To: {$mail_from}",
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=UTF-8',
                ]);

                mail($email, $subject, $body, $headers);
                $success = "A new confirmation email has been sent to {$email}. Please check your inbox.";
            }

            mysqli_close($CarpartsConnection);
        }
    }
    $prefill = htmlspecialchars($_POST['email'] ?? '');
}

function _resend_site_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return "{$scheme}://{$host}{$script}";
}
?>
<div class="content-box">
    <h3>Resend confirmation email</h3>

    <?php if ($error): ?>
    <div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px 14px;
                border-radius:4px;margin-bottom:14px;font-size:13px;"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:10px 14px;
                border-radius:4px;margin-bottom:14px;font-size:13px;"><?= $success ?></div>
    <?php else: ?>

    <p style="font-size:13px;color:#666;">Enter your email address and we'll send you a new confirmation link.</p>

    <form method="post" action="index.php?navigate=resendemail">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <label for="re_email"><strong>Email address:</strong></label><br>
        <input type="email" id="re_email" name="email" required maxlength="255"
               value="<?= $prefill ?>"
               style="width:300px;padding:6px;font-size:14px;" /><br><br>
        <input type="submit" value="Resend confirmation" class="btn" style="padding:8px 22px;" />
    </form>

    <?php endif; ?>

    <p style="margin-top:14px;font-size:13px;">
        <a href="index.php?navigate=secureadmin">&larr; Back to login</a>
    </p>
</div>
