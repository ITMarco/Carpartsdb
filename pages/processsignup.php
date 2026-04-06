<?php
if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php?navigate=browse');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?navigate=signup');
    exit();
}

// CSRF
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: index.php?navigate=signup&error=' . rawurlencode('Security validation failed. Please try again.'));
    exit();
}

// Honeypot — if filled, silently act like success (fool bots)
if (!empty($_POST['website'])) {
    header('Location: index.php?navigate=signup&success=' . rawurlencode('Account created! Please check your email to confirm.'));
    exit();
}

// ── Collect & validate ────────────────────────────────────────────────────────
$realname  = trim($_POST['realname']  ?? '');
$email     = strtolower(trim($_POST['email']     ?? ''));
$password  = $_POST['password']  ?? '';
$password2 = $_POST['password2'] ?? '';

$errors = [];

if (mb_strlen($realname) < 2) {
    $errors[] = 'Please enter your name (at least 2 characters).';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (mb_strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if ($password !== $password2) {
    $errors[] = 'Passwords do not match.';
}

if ($errors) {
    $msg = implode(' ', $errors);
    header('Location: index.php?navigate=signup&error=' . rawurlencode($msg) . '&email=' . rawurlencode($email));
    exit();
}

// ── Database ──────────────────────────────────────────────────────────────────
include 'connection.php';
include_once 'users_helper.php';
include_once 'settings_helper.php';

users_ensure_table($CarpartsConnection);

// Check if email is already registered
$existing = users_get_by_email($CarpartsConnection, $email);
if ($existing) {
    mysqli_close($CarpartsConnection);
    if ((int)($existing['is_confirmed'] ?? 1) === 0) {
        // Already registered but not confirmed — offer to resend
        $msg = 'This email is already registered but not yet confirmed. '
             . '<a href="index.php?navigate=resendemail&email=' . rawurlencode($email) . '">Resend confirmation email</a>.';
    } else {
        $msg = 'This email address is already registered. <a href="index.php?navigate=secureadmin">Log in</a> or use a different address.';
    }
    header('Location: index.php?navigate=signup&error=' . rawurlencode($msg) . '&email=' . rawurlencode($email));
    exit();
}

// Insert new unconfirmed user
$hash  = password_hash($password, PASSWORD_BCRYPT);
$token = bin2hex(random_bytes(32));  // 64-char hex token

$stmt = $CarpartsConnection->prepare(
    "INSERT INTO `USERS` (`email`,`realname`,`password`,`isadmin`,`is_member`,`is_confirmed`,`confirmation_token`)
     VALUES (?,?,?,0,0,0,?)"
);
$stmt->bind_param('ssss', $email, $realname, $hash, $token);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    mysqli_close($CarpartsConnection);
    header('Location: index.php?navigate=signup&error=' . rawurlencode('Registration failed. Please try again.') . '&email=' . rawurlencode($email));
    exit();
}
$stmt->close();

// ── Build confirmation URL ────────────────────────────────────────────────────
$site_url = _signup_site_url();
$confirm_url = $site_url . '/index.php?navigate=confirmemail&token=' . urlencode($token);

// ── Send confirmation email ───────────────────────────────────────────────────
$site_name  = settings_get($CarpartsConnection, 'site_name', 'Car Parts DB');
$mail_from  = settings_get($CarpartsConnection, 'mail_from',  'noreply@supraclub.nl');

mysqli_close($CarpartsConnection);

$subject = "Confirm your {$site_name} account";
$body    = "Hi {$realname},\r\n\r\n"
         . "Thank you for signing up at {$site_name}!\r\n\r\n"
         . "Please confirm your email address by clicking the link below:\r\n\r\n"
         . "{$confirm_url}\r\n\r\n"
         . "If you did not sign up, you can safely ignore this email.\r\n\r\n"
         . "— {$site_name}";

$headers = implode("\r\n", [
    "From: {$site_name} <{$mail_from}>",
    "Reply-To: {$mail_from}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$sent = mail($email, $subject, $body, $headers);

if (!$sent) {
    // Mail failed — account exists but user can't confirm. Show the link directly.
    $success_msg = "Account created! We could not send an email automatically. "
                 . "Please use this link to confirm your account: {$confirm_url}";
} else {
    $success_msg = "Account created! We sent a confirmation email to {$email}. "
                 . "Please click the link in the email to activate your account.";
}

header('Location: index.php?navigate=signup&success=' . rawurlencode($success_msg));
exit();

// ── Helper ────────────────────────────────────────────────────────────────────
function _signup_site_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip /index.php and anything after it from the request URI
    $script = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return "{$scheme}://{$host}{$script}";
}
