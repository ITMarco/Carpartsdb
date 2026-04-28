<?php
// Logged-in users only (not the seller, not anon)
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to report a listing.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='content-box'><p>Invalid listing. <a href='index.php?navigate=browse'>Browse parts</a></p></div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'parts_helper.php';
parts_ensure_table($CarpartsConnection);

$part = parts_get($CarpartsConnection, $id);
if (!$part) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Part not found.</p></div>";
    return;
}

$user_id   = (int)$_SESSION['user_id'];
$is_seller = $user_id === (int)$part['seller_id'];
if ($is_seller) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>You cannot flag your own listing.</p><p><a href='index.php?navigate=viewpart&id={$id}'>Back</a></p></div>";
    return;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            $error = 'Please provide a reason.';
        } else {
            // Check if already flagged by this user
            $chk = $CarpartsConnection->prepare(
                "SELECT `id` FROM `PART_FLAGS` WHERE `part_id`=? AND `reporter_id`=? LIMIT 1"
            );
            $chk->bind_param('ii', $id, $user_id);
            $chk->execute();
            $already = $chk->get_result()->num_rows > 0;
            $chk->close();

            if ($already) {
                $error = 'You have already reported this listing.';
            } else {
                $ins = $CarpartsConnection->prepare(
                    "INSERT INTO `PART_FLAGS` (`part_id`,`reporter_id`,`reason`) VALUES (?,?,?)"
                );
                $ins->bind_param('iis', $id, $user_id, $reason);
                $ins->execute();
                $ins->close();
                $success = true;
            }
        }
    }
}

mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Report listing</h3>
<p style="font-size:13px;color:#666;"><?= htmlspecialchars($part['title']) ?> &mdash; <?= htmlspecialchars(parts_ref($id)) ?></p>

<?php if ($success): ?>
<div style="background:#d4edda;border:1px solid #28a745;color:#155724;padding:10px 14px;border-radius:4px;">
    Thank you. The admin will review your report.
</div>
<p style="margin-top:12px;"><a href="index.php?navigate=viewpart&id=<?= $id ?>">&larr; Back to listing</a></p>

<?php else: ?>
<?php if ($error): ?>
<p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="index.php?navigate=flagpart&id=<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <label><strong>Reason for reporting:</strong></label><br>
    <select name="reason" required style="padding:5px;min-width:280px;margin:6px 0 10px;">
        <option value="">-- Select a reason --</option>
        <option value="Spam or duplicate listing">Spam or duplicate listing</option>
        <option value="Incorrect or misleading information">Incorrect or misleading information</option>
        <option value="Inappropriate content or photos">Inappropriate content or photos</option>
        <option value="Wrong category or make/model">Wrong category or make/model</option>
        <option value="Other">Other</option>
    </select><br>
    <input type="submit" value="Submit report" class="btn" style="padding:7px 18px;" />
    <a href="index.php?navigate=viewpart&id=<?= $id ?>" style="padding:7px 18px;margin-left:10px;">Cancel</a>
</form>
<?php endif; ?>
</div>
