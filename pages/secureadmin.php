<?php
// Session is already started by index.php
require_once __DIR__ . '/../login_helper.php';

// Redirect if already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $dest = (!empty($_SESSION['isadmin'])) ? 'adminpanel' : 'browse';
    header("Location: index.php?navigate={$dest}");
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate = check_rate_limit();
    if ($rate['blocked']) {
        $mins        = ceil($rate['remaining_time'] / 60);
        $login_error = "Too many failed attempts. Try again in {$mins} minute(s).";
    } else {
        $csrf_valid = isset($_POST['csrf_token'], $_SESSION['csrf_token'])
                   && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

        if (!$csrf_valid) {
            $login_error = "Security validation failed. Please refresh and try again.";
        } else {
            if (!defined('SNLDBCARPARTS_ACCESS')) define('SNLDBCARPARTS_ACCESS', true);
            require_once __DIR__ . '/../connection.php';
            include_once __DIR__ . '/../users_helper.php';
            users_ensure_table($CarpartsConnection);

            $email        = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
            $userpassword = $_POST['userpassword'] ?? '';

            $myrow = users_get_by_email($CarpartsConnection, $email);

            if ($myrow && password_verify($userpassword, $myrow['password'])) {
                reset_login_attempts();
                session_regenerate_id(true);

                $_SESSION['authenticated']  = true;
                $_SESSION['user_id']        = (int)$myrow['id'];
                $_SESSION['user_email']     = $myrow['email'];
                $_SESSION['username']       = $myrow['realname'] ?: $myrow['email'];
                $_SESSION['isadmin']        = (int)$myrow['isadmin'];
                $_SESSION['is_member']      = (int)$myrow['is_member'];
                $_SESSION['LAST_ACTIVITY']  = time();
                $_SESSION['HTTP_USER_AGENT']= $_SERVER['HTTP_USER_AGENT'];

                mysqli_close($CarpartsConnection);
                $dest = ($myrow['isadmin'] == 1) ? 'adminpanel' : 'browse';
                header("Location: index.php?navigate={$dest}");
                exit();
            } else {
                record_failed_login();
                sleep(2);
                $remaining   = get_remaining_attempts();
                $login_error = "Incorrect email or password.";
                if ($remaining > 0) {
                    $login_error .= " ({$remaining} attempt(s) remaining)";
                } else {
                    $login_error .= " Account temporarily locked.";
                }
            }

            if (isset($CarpartsConnection)) mysqli_close($CarpartsConnection);
        }
    }
}
?>
<div class="content-box">
    <h3>Login</h3>
    <br>
<?php if ($login_error): ?>
    <div style="color:red;margin-bottom:12px;"><?= htmlspecialchars($login_error) ?></div>
<?php endif; ?>
    <form name="secure" id="secure" action="index.php?navigate=secureadmin" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <label for="email"><strong>Email:</strong></label><br>
        <input type="email" id="email" name="email" required autocomplete="email"
               value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
               style="width:260px;padding:6px;font-size:14px;" /><br><br>
        <label for="userpassword"><strong>Password:</strong></label><br>
        <input type="password" id="userpassword" name="userpassword" required autocomplete="current-password"
               style="width:260px;padding:6px;font-size:14px;" /><br><br>
        <input type="submit" value="Login" class="btn" style="padding:8px 22px;font-size:14px;" />
    </form>
</div>
