<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a>.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'stats_helper.php';

parts_ensure_table($CarpartsConnection);

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
if ($id <= 0) {
    echo "<div class='content-box'><p>Invalid part ID.</p></div>";
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

$upload_error = '';
$upload_ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $upload_error = 'Security validation failed.';
    } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Upload error code: ' . (int)$_FILES['photo']['error'];
    } else {
        // Validate mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mime, $allowed_mime, true)) {
            $upload_error = 'Only JPG, PNG, GIF and WebP images are allowed.';
        } else {
            $dir = parts_photo_dir($id);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                $upload_error = 'Could not create photo directory.';
            } else {
                // Generate unique filename
                $ext    = match($mime) {
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp',
                    default      => 'jpg',
                };
                $fname  = $dir . '/' . uniqid('img_', true) . '.' . $ext;
                include_once 'image_helper.php';
                $result = snldb_save_image($_FILES['photo']['tmp_name'], $fname);

                if ($result) {
                    stats_day($CarpartsConnection, 'images_added');
                    $upload_ok = true;
                } else {
                    $upload_error = 'Image processing failed. Check file size (max 1.5 MB) and format.';
                }
            }
        }
    }
}

$photos = parts_photos($id);
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Upload photo &mdash; <?= htmlspecialchars(parts_ref($id)) ?></h3>
<p><?= htmlspecialchars($part['title']) ?></p>

<?php if ($upload_ok): ?>
<div style="background:#d4edda;border:1px solid #28a745;padding:10px;border-radius:4px;margin-bottom:12px;">
    Photo uploaded successfully.
</div>
<?php endif; ?>
<?php if ($upload_error): ?>
<div style="color:red;margin-bottom:12px;"><?= htmlspecialchars($upload_error) ?></div>
<?php endif; ?>

<form method="post" action="index.php?navigate=uploadpartimage&id=<?= $id ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="id" value="<?= $id ?>" />
    <label><strong>Select image:</strong></label><br>
    <input type="file" name="photo" accept="image/*" required style="margin:8px 0;" /><br>
    <small style="color:#666;">JPG, PNG, GIF or WebP. Max 1.5 MB. Larger images are automatically resized.</small><br><br>
    <input type="submit" value="Upload" class="btn" style="padding:7px 18px;" />
</form>

<?php if (!empty($photos)): ?>
<h4 style="margin-top:16px;">Current photos (<?= count($photos) ?>)</h4>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
    <?php foreach ($photos as $ph): ?>
    <img src="<?= htmlspecialchars($ph) ?>" alt=""
         style="width:120px;height:90px;object-fit:cover;border-radius:4px;
                border:1px solid var(--color-content-border);" />
    <?php endforeach; ?>
</div>
<p style="margin-top:8px;">
    <a href="index.php?navigate=deletepartimage&id=<?= $id ?>">Manage / delete photos</a>
</p>
<?php endif; ?>

<p style="margin-top:12px;">
    <a href="index.php?navigate=viewpart&id=<?= $id ?>">&larr; Back to part</a>
</p>
</div>
