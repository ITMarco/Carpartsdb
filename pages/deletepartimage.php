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
mysqli_close($CarpartsConnection);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = '<span style="color:red;">Security validation failed.</span>';
    } elseif (isset($_POST['filename'])) {
        // Delete a single photo
        $requested = basename($_POST['filename']);
        $target    = parts_photo_dir_for($part) . '/' . $requested;
        if (file_exists($target) && is_file($target)) {
            @unlink($target);
            $msg = '<span style="color:green;">Photo deleted.</span>';
        } else {
            $msg = '<span style="color:red;">File not found.</span>';
        }
    } elseif (isset($_POST['order'])) {
        // Reorder: rename files to match new order
        $dir    = parts_photo_dir_for($part);
        $order  = array_map('basename', (array)$_POST['order']);
        $photos_now = array_map('basename', parts_photos($id));

        // Validate: all submitted names must exist in the actual file list
        $valid = array_intersect($order, $photos_now);
        if (count($valid) === count($photos_now)) {
            // Rename to temp names first to avoid collisions
            $tmp_map = [];
            foreach ($valid as $i => $fname) {
                $src = $dir . '/' . $fname;
                $ext = pathinfo($fname, PATHINFO_EXTENSION);
                $tmp = $dir . '/tmp_' . $i . '_' . time() . '.' . $ext;
                @rename($src, $tmp);
                $tmp_map[$i] = ['tmp' => $tmp, 'ext' => $ext];
            }
            // Rename to sequential numbered names
            foreach ($tmp_map as $i => $info) {
                $new = $dir . '/img_' . sprintf('%03d', $i + 1) . '.' . $info['ext'];
                @rename($info['tmp'], $new);
            }
            $msg = '<span style="color:green;">Order saved.</span>';
        }
    }
}

$photos = parts_photos($id);
?>
<div class="content-box">
<h3>Manage photos &mdash; <?= htmlspecialchars(parts_ref($id)) ?></h3>
<p style="color:#666;font-size:13px;"><?= htmlspecialchars($part['title']) ?></p>

<?php if ($msg): ?><p><?= $msg ?></p><?php endif; ?>

<?php if (empty($photos)): ?>
<p>No photos uploaded yet.</p>
<?php else: ?>

<p style="font-size:12px;color:#888;">Drag to reorder, then click Save order. First photo is the thumbnail shown in listings.</p>

<form method="post" action="index.php?navigate=deletepartimage&id=<?= $id ?>" id="order-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <div id="photo-sortable" style="display:flex;flex-wrap:wrap;gap:14px;margin:10px 0;">
        <?php foreach ($photos as $i => $ph): ?>
        <div class="sort-item" data-file="<?= htmlspecialchars(basename($ph)) ?>"
             style="text-align:center;cursor:grab;user-select:none;">
            <?php if ($i === 0): ?>
            <div style="font-size:10px;color:var(--color-accent);font-weight:bold;margin-bottom:3px;">&#9733; Cover</div>
            <?php else: ?>
            <div style="font-size:10px;color:#aaa;margin-bottom:3px;">#<?= $i + 1 ?></div>
            <?php endif; ?>
            <img src="<?= htmlspecialchars($ph) ?>" alt=""
                 style="width:150px;height:110px;object-fit:cover;border-radius:4px;
                        border:2px solid var(--color-content-border);display:block;pointer-events:none;" />
            <div style="margin-top:6px;display:flex;gap:4px;justify-content:center;">
                <button type="button" onclick="movePhoto(this, -1)"
                        style="padding:2px 8px;font-size:13px;cursor:pointer;" title="Move left">&larr;</button>
                <button type="button" onclick="movePhoto(this, 1)"
                        style="padding:2px 8px;font-size:13px;cursor:pointer;" title="Move right">&rarr;</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="submit" name="save_order" value="1" class="btn" style="padding:6px 16px;font-size:13px;">
        Save order
    </button>
</form>

<div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:18px;padding-top:14px;border-top:1px solid var(--color-content-border);">
<?php foreach ($photos as $ph): ?>
<div style="text-align:center;">
    <img src="<?= htmlspecialchars($ph) ?>" alt=""
         style="width:100px;height:75px;object-fit:cover;border-radius:4px;
                border:1px solid var(--color-content-border);display:block;" />
    <form method="post" action="index.php?navigate=deletepartimage&id=<?= $id ?>"
          style="display:inline;margin-top:4px;"
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
    <a href="index.php?navigate=addpart&new=<?= $id ?>">+ Upload more photos</a>
    | <a href="index.php?navigate=viewpart&id=<?= $id ?>">&larr; Back to part</a>
</p>
</div>

<script>
(function() {
    // Build hidden order inputs before submit
    document.getElementById('order-form').addEventListener('submit', function() {
        document.querySelectorAll('#photo-sortable input[name="order[]"]').forEach(function(el) { el.remove(); });
        document.querySelectorAll('#photo-sortable .sort-item').forEach(function(item) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'order[]';
            inp.value = item.dataset.file;
            document.getElementById('order-form').appendChild(inp);
        });
    });
})();

function movePhoto(btn, dir) {
    var item = btn.closest('.sort-item');
    var container = item.parentElement;
    var items = Array.from(container.querySelectorAll('.sort-item'));
    var idx = items.indexOf(item);
    var target = items[idx + dir];
    if (!target) return;
    if (dir === -1) {
        container.insertBefore(item, target);
    } else {
        container.insertBefore(target, item);
    }
    // Update cover label
    Array.from(container.querySelectorAll('.sort-item')).forEach(function(el, i) {
        var label = el.querySelector('div');
        if (i === 0) {
            label.textContent = '★ Cover';
            label.style.color = 'var(--color-accent)';
            label.style.fontWeight = 'bold';
        } else {
            label.textContent = '#' + (i + 1);
            label.style.color = '#aaa';
            label.style.fontWeight = 'normal';
        }
    });
}
</script>
