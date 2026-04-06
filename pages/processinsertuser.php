<div class="content-box">
    <h3>Create user — result</h3>
    <br>
<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied.</div></div>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div style='color:red;'>Invalid request.</div>";
    echo "<p><a href='index.php?navigate=adminpanel'>Back to admin panel</a></p></div>";
    return;
}

// CSRF
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo "<div style='color:red;'>Security validation failed. Please try again.</div></div>";
    return;
}

include 'connection.php';
include_once 'users_helper.php';
users_ensure_table($CarpartsConnection);

$email     = strtolower(trim($_POST['email'] ?? ''));
$realname  = trim($_POST['realname'] ?? '');
$password  = $_POST['password'] ?? '';
$isadmin   = isset($_POST['isadmin'])   && $_POST['isadmin']   == '1' ? 1 : 0;
$is_member = isset($_POST['is_member']) && $_POST['is_member'] == '1' ? 1 : 0;

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<div style='color:red;'>Valid email address is required.</div>";
    echo "<p><a href='index.php?navigate=insertuser'>Back</a></p></div>";
    mysqli_close($CarpartsConnection);
    return;
}
if (empty($password) || strlen($password) < 6) {
    echo "<div style='color:red;'>Password must be at least 6 characters.</div>";
    echo "<p><a href='index.php?navigate=insertuser'>Back</a></p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

// Duplicate check
$chk = $CarpartsConnection->prepare("SELECT `id` FROM `USERS` WHERE `email` = ? LIMIT 1");
$chk->bind_param('s', $email);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo "<div style='color:red;'>This email address is already in use.</div>";
    echo "<p><a href='index.php?navigate=insertuser'>Back</a></p></div>";
    $chk->close();
    mysqli_close($CarpartsConnection);
    return;
}
$chk->close();

$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $CarpartsConnection->prepare(
    "INSERT INTO `USERS` (`email`,`realname`,`password`,`isadmin`,`is_member`) VALUES (?,?,?,?,?)"
);
$stmt->bind_param('sssii', $email, $realname, $hashed, $isadmin, $is_member);

if ($stmt->execute()) {
    $new_id = $CarpartsConnection->insert_id;
    echo "<div style='background:#d4edda;border:1px solid #28a745;padding:15px;border-radius:4px;'>";
    echo "<h3 style='color:green;'>User created successfully!</h3>";
    echo "<table style='border-collapse:collapse;margin:10px 0;'>";
    echo "<tr><td style='padding:4px 10px;font-weight:bold;'>ID:</td><td style='padding:4px 10px;'>" . intval($new_id) . "</td></tr>";
    echo "<tr><td style='padding:4px 10px;font-weight:bold;'>Email:</td><td style='padding:4px 10px;'>" . htmlspecialchars($email) . "</td></tr>";
    if ($realname !== '') {
        echo "<tr><td style='padding:4px 10px;font-weight:bold;'>Name:</td><td style='padding:4px 10px;'>" . htmlspecialchars($realname) . "</td></tr>";
    }
    echo "<tr><td style='padding:4px 10px;font-weight:bold;'>Password (plain):</td><td style='padding:4px 10px;background:#fff3cd;'><code>" . htmlspecialchars($password) . "</code></td></tr>";
    echo "<tr><td style='padding:4px 10px;font-weight:bold;'>Admin:</td><td style='padding:4px 10px;'>" . ($isadmin ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td style='padding:4px 10px;font-weight:bold;'>Incrowd member:</td><td style='padding:4px 10px;'>" . ($is_member ? 'Yes' : 'No') . "</td></tr>";
    echo "</table>";
    echo "<p style='color:#856404;'><strong>Save this password now — it will not be shown again.</strong></p>";
    echo "</div>";
} else {
    echo "<div style='color:red;'>Error creating user: " . htmlspecialchars($stmt->error) . "</div>";
}
$stmt->close();
mysqli_close($CarpartsConnection);
?>
<p>
    <a href="index.php?navigate=insertuser">Add another user</a> |
    <a href="index.php?navigate=edituser">Edit users</a> |
    <a href="index.php?navigate=adminpanel">Admin panel</a>
</p>
</div>
