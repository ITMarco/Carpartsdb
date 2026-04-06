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

parts_ensure_table($SNLDBConnection);

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
if ($id <= 0) {
    echo "<div class='content-box'><p>Invalid part ID.</p></div>";
    mysqli_close($SNLDBConnection);
    return;
}

$part = parts_get($SNLDBConnection, $id, true);
if (!$part) {
    echo "<div class='content-box'><p>Part not found.</p></div>";
    mysqli_close($SNLDBConnection);
    return;
}

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    mysqli_close($SNLDBConnection);
    return;
}
mysqli_close($SNLDBConnection);

$delete_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $delete_msg = '<span style="color:red;">Security validation failed.</span>';
    } else {
        // Sanitise: only allow files inside parts/{id}/
        $requested = basename($_POST['filename']);
        $target    = parts_photo_dir($id) . '/' . $requested;
        if (file_exists($target) && is_file($target)) {
            @unlink($target);
            // Also remove .webp companion if exists
            $webp = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $target);
            if ($webp !== $target && file_exists($webp)) @unlink($webp);
            $delete_msg = '<span style="color:green;">Photo deleted.</span>';
        } else {
            $delete_msg = '<span style="color:red;">File not found.</span>';
        }
    }
}

$photos = parts_photos($id);
?>
<div class="content-box">
<h3>Manage photos &mdash; <?= htmlspecialchars(parts_ref($id)) ?></h3>
<p><?= htmlspecialchars($part['title']) ?></p>

<?php if ($delete_msg): ?><p><?= $delete_msg ?></p><?php endif; ?>

<?php if (empty($photos)): ?>
<p>No photos uploaded yet.</p>
<?php else: ?>
<div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:10px;">
    <?php foreach ($photos as $ph): ?>
    <div style="text-align:center;">
        <img src="<?= htmlspecialchars($ph) ?>" alt=""
             style="width:150px;height:110px;object-fit:cover;border-radius:4px;
                    border:1px solid var(--color-content-border);display:block;" /><br>
        <form method="post" action="index.php?navigate=deletepartimage&id=<?= $id ?>"
              style="display:inline;"
              onsubmit="return confirm('Delete this photo?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="id"       value="<?= $id ?>" />
            <input type="hidden" name="filename" value="<?= htmlspecialchars(basename($ph)) ?>" />
            <input type="submit" value="Delete"
                   style="font-size:11px;padding:3px 10px;background:#dc3545;color:#fff;border:none;cursor:pointer;border-radius:3px;" />
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<p style="margin-top:14px;">
    <a href="index.php?navigate=uploadpartimage&id=<?= $id ?>">+ Upload another photo</a>
    | <a href="index.php?navigate=viewpart&id=<?= $id ?>">&larr; Back to part</a>
</p>
</div>
