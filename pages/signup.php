<?php
// Redirect already-logged-in users
if (!empty($_SESSION['authenticated'])) {
    echo "<script>window.location.replace('index.php?navigate=browse');</script>";
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = isset($_GET['error'])   ? htmlspecialchars($_GET['error'])   : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$email   = isset($_GET['email'])   ? htmlspecialchars($_GET['email'])   : '';
?>
<div class="content-box">
    <h3>Create an account</h3>
    <p style="font-size:13px;color:#666;">
        Sign up to list your spare parts and manage your collection.<br>
        You will receive a confirmation email — please check your inbox (and spam folder).
    </p>

    <?php if ($error): ?>
    <div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px 14px;
                border-radius:4px;margin-bottom:14px;font-size:13px;"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:10px 14px;
                border-radius:4px;margin-bottom:14px;font-size:13px;"><?= $success ?></div>
    <?php else: ?>

    <form method="post" action="index.php?navigate=processsignup" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <!-- Honeypot: bots fill this, humans don't see it -->
        <input type="text" name="website" value="" tabindex="-1"
               style="display:none;position:absolute;left:-9999px;" aria-hidden="true" autocomplete="off" />

        <label for="su_name"><strong>Your name: *</strong></label><br>
        <input type="text" id="su_name" name="realname" maxlength="100" required
               style="width:300px;padding:6px;font-size:14px;" placeholder="How you want to be known" /><br><br>

        <label for="su_email"><strong>Email address: *</strong></label><br>
        <input type="email" id="su_email" name="email" maxlength="255" required autocomplete="email"
               value="<?= $email ?>"
               style="width:300px;padding:6px;font-size:14px;" placeholder="you@example.com" /><br><br>

        <label for="su_pass"><strong>Password: *</strong> <small style="color:#666;font-weight:normal;">(min 8 characters)</small></label><br>
        <input type="password" id="su_pass" name="password" minlength="8" required autocomplete="new-password"
               style="width:300px;padding:6px;font-size:14px;" /><br><br>

        <label for="su_pass2"><strong>Confirm password: *</strong></label><br>
        <input type="password" id="su_pass2" name="password2" minlength="8" required autocomplete="new-password"
               style="width:300px;padding:6px;font-size:14px;" /><br><br>

        <input type="submit" value="Sign up" class="btn" style="padding:9px 28px;font-size:15px;" />
    </form>

    <?php endif; ?>

    <p style="margin-top:16px;font-size:13px;">
        Already have an account? <a href="index.php?navigate=secureadmin">Log in</a>
    </p>
</div>
