<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Not authenticated.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    echo "<div class='content-box'><p>Invalid ID.</p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

$part = parts_get($CarpartsConnection, $id, true);
if (!$part) {
    echo "<div class='content-box'><p>Part not found.</p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    mysqli_close($CarpartsConnection);
    return;
}

// POST = confirmed delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "<div class='content-box'><p style='color:red;'>Security validation failed.</p></div>";
        mysqli_close($CarpartsConnection);
        return;
    }

    $stmt = $CarpartsConnection->prepare("DELETE FROM `PARTS` WHERE `id` = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    mysqli_close($CarpartsConnection);

    if ($ok) {
        // Remove photo directory
        $dir = parts_photo_dir($id);
        if (is_dir($dir)) {
            foreach (glob("{$dir}/*") ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        echo "<div class='content-box'><p>Part <strong>" . htmlspecialchars(parts_ref($id))
             . "</strong> deleted.</p><p><a href='index.php?navigate=browse'>Browse parts</a></p></div>";
    } else {
        echo "<div class='content-box'><p style='color:red;'>Error deleting part.</p></div>";
    }
    return;
}

mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Delete part?</h3>
<p>Are you sure you want to delete <strong><?= htmlspecialchars(parts_ref($id)) ?></strong>
   &mdash; <?= htmlspecialchars($part['title']) ?>?</p>
<p style="color:#c04040;">This will also remove all photos. This cannot be undone.</p>

<form method="post" action="index.php?navigate=deletepart">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="id" value="<?= $id ?>" />
    <input type="submit" value="Yes, delete" style="padding:8px 20px;background:#dc3545;color:#fff;border:none;cursor:pointer;border-radius:3px;" />
    <a href="index.php?navigate=viewpart&id=<?= $id ?>"
       style="padding:8px 18px;margin-left:10px;">Cancel</a>
</form>
</div>
