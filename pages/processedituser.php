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
users_ensure_table($SNLDBConnection);

$userid = isset($_POST['userid']) ? intval($_POST['userid']) : 0;
$action = $_POST['action'] ?? '';

if ($userid <= 0) {
    echo "<div style='color:red;'>Invalid user ID.</div>";
    echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
    mysqli_close($SNLDBConnection);
    return;
}

if ($action === 'Delete') {
    $stmt = $SNLDBConnection->prepare("DELETE FROM `USERS` WHERE `id` = ?");
    $stmt->bind_param('i', $userid);
    if ($stmt->execute()) {
        echo "<div style='background:#d4edda;border:1px solid #28a745;padding:12px;border-radius:4px;'>";
        echo "<strong>User deleted successfully.</strong></div>";
    } else {
        echo "<div style='color:red;'>Error deleting user: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();

} elseif ($action === 'Save') {
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $realname  = trim($_POST['realname'] ?? '');
    $password  = $_POST['password'] ?? '';
    $isadmin   = isset($_POST['isadmin'])   && $_POST['isadmin']   == '1' ? 1 : 0;
    $is_member = isset($_POST['is_member']) && $_POST['is_member'] == '1' ? 1 : 0;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<div style='color:red;'>Valid email address is required.</div>";
        echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
        mysqli_close($SNLDBConnection);
        return;
    }

    // Duplicate email check (excluding current user)
    $chk = $SNLDBConnection->prepare("SELECT `id` FROM `USERS` WHERE `email` = ? AND `id` != ? LIMIT 1");
    $chk->bind_param('si', $email, $userid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo "<div style='color:red;'>This email address is already in use by another user.</div>";
        echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
        $chk->close();
        mysqli_close($SNLDBConnection);
        return;
    }
    $chk->close();

    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo "<div style='color:red;'>Password must be at least 6 characters.</div>";
            echo "<p><a href='index.php?navigate=edituser'>Back</a></p></div>";
            mysqli_close($SNLDBConnection);
            return;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $SNLDBConnection->prepare(
            "UPDATE `USERS` SET `email`=?,`realname`=?,`password`=?,`isadmin`=?,`is_member`=? WHERE `id`=?"
        );
        $stmt->bind_param('sssiii', $email, $realname, $hashed, $isadmin, $is_member, $userid);
    } else {
        $stmt = $SNLDBConnection->prepare(
            "UPDATE `USERS` SET `email`=?,`realname`=?,`isadmin`=?,`is_member`=? WHERE `id`=?"
        );
        $stmt->bind_param('ssiii', $email, $realname, $isadmin, $is_member, $userid);
    }

    if ($stmt->execute()) {
        echo "<div style='background:#d4edda;border:1px solid #28a745;padding:12px;border-radius:4px;'>";
        echo "<strong>User updated successfully.</strong><br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Admin: " . ($isadmin ? 'Yes' : 'No') . " | Member: " . ($is_member ? 'Yes' : 'No');
        echo "</div>";
    } else {
        echo "<div style='color:red;'>Error updating user: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();

} else {
    echo "<div style='color:red;'>Unknown action.</div>";
}

mysqli_close($SNLDBConnection);
?>
<p>
    <a href="index.php?navigate=edituser">Edit another user</a> |
    <a href="index.php?navigate=insertuser">Add user</a> |
    <a href="index.php?navigate=adminpanel">Admin panel</a>
</p>
</div>
