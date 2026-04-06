<?php
include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($SNLDBConnection);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    mysqli_close($SNLDBConnection);
    echo "<div class='content-box'><p>Invalid part ID.</p><p><a href='index.php?navigate=browse'>Back to browse</a></p></div>";
    return;
}

$is_member = !empty($_SESSION['is_member']) || !empty($_SESSION['isadmin']);

$part = parts_get($SNLDBConnection, $id, true);

$is_seller = $part && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];

// Check access for hidden/public/private parts
if ($part) {
    if (!$part['visible'] && !$is_seller && empty($_SESSION['isadmin'])) {
        $part = null;
    } elseif ($part['visible_private'] && !$is_member) {
        $part = null;
    }
}

mysqli_close($SNLDBConnection);

if (!$part) {
    echo "<div class='content-box'><p>Part not found.</p><p><a href='index.php?navigate=browse'>Back to browse</a></p></div>";
    return;
}

$photos    = parts_photos($id);
$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
$can_edit  = $is_seller || !empty($_SESSION['isadmin']);
?>

<div class="content-box">
<h3><?= htmlspecialchars($part['title']) ?></h3>
<small style="color:#888;"><?= htmlspecialchars(parts_ref($id)) ?></small>

<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:14px;">

    <!-- Photos -->
    <div style="flex:0 0 auto;max-width:320px;">
        <?php if (!empty($photos)): ?>
        <div id="photo-main" style="margin-bottom:8px;">
            <img id="main-photo" src="<?= htmlspecialchars($photos[0]) ?>"
                 alt="Part photo"
                 style="width:300px;max-height:220px;object-fit:cover;border-radius:5px;
                        border:1px solid var(--color-content-border);cursor:pointer;"
                 onclick="openLightbox(this.src)" />
        </div>
        <?php if (count($photos) > 1): ?>
        <div style="display:flex;flex-wrap:wrap;gap:5px;">
            <?php foreach ($photos as $ph): ?>
            <img src="<?= htmlspecialchars($ph) ?>" alt=""
                 style="width:60px;height:45px;object-fit:cover;cursor:pointer;
                        border-radius:3px;border:1px solid var(--color-content-border);"
                 onclick="document.getElementById('main-photo').src=this.src;openLightbox(this.src)" />
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div style="width:300px;height:180px;background:var(--color-surface);
                    border:1px dashed var(--color-content-border);border-radius:5px;
                    display:flex;align-items:center;justify-content:center;color:#aaa;font-size:13px;">
            No photos
        </div>
        <?php endif; ?>

        <?php if ($can_edit): ?>
        <p style="margin-top:8px;font-size:12px;">
            <a href="index.php?navigate=uploadpartimage&id=<?= $id ?>">+ Upload photo</a>
            <?php if (!empty($photos)): ?>
            | <a href="index.php?navigate=deletepartimage&id=<?= $id ?>">Manage photos</a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Details -->
    <div style="flex:1;min-width:200px;">
        <table style="border-collapse:collapse;font-size:13px;width:100%;">
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;white-space:nowrap;">Price:</td>
                <td style="padding:5px 0;font-size:20px;font-weight:bold;color:var(--color-accent);">
                    &euro;<?= number_format((float)$part['price'], 2, ',', '.') ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Status:</td>
                <td style="padding:5px 0;">
                    <?= $part['for_sale'] ? 'For sale' : 'Display only' ?>
                    <?php if (!$part['visible']): ?> <span style="color:#c04040;">(Private)</span><?php endif; ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Make:</td>
                <td style="padding:5px 0;"><?= htmlspecialchars($part['make_name']) ?></td></tr>
            <?php if ($part['model_name']): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Model:</td>
                <td style="padding:5px 0;"><?= htmlspecialchars($part['model_name']) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Year(s):</td>
                <td style="padding:5px 0;">
                    <?= (int)$part['year_from'] ?><?= $part['year_to'] ? '&ndash;' . (int)$part['year_to'] : '' ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Condition:</td>
                <td style="padding:5px 0;">
                    <?= (int)$part['condition'] ?>/5 &mdash; <?= htmlspecialchars(parts_condition_label((int)$part['condition'])) ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">In stock:</td>
                <td style="padding:5px 0;"><?= (int)$part['stock'] ?></td></tr>
            <?php if (!empty($part['oem_number'])): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">OEM number:</td>
                <td style="padding:5px 0;font-family:monospace;"><?= htmlspecialchars($part['oem_number']) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($part['replacement_number'])): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Replacement OEM:</td>
                <td style="padding:5px 0;font-family:monospace;"><?= htmlspecialchars($part['replacement_number']) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Seller:</td>
                <td style="padding:5px 0;">
                    <?= htmlspecialchars($part['seller_name'] ?: $part['seller_email']) ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Listed:</td>
                <td style="padding:5px 0;font-size:12px;color:#888;">
                    <?= htmlspecialchars(substr($part['created_at'], 0, 10)) ?>
                </td></tr>
        </table>

        <?php if (!empty($part['description'])): ?>
        <h4 style="margin-top:14px;">Description</h4>
        <div style="font-size:13px;line-height:1.6;"><?= nl2br(htmlspecialchars($part['description'])) ?></div>
        <?php endif; ?>

        <?php if ($can_edit): ?>
        <p style="margin-top:14px;">
            <a href="index.php?navigate=editpart&id=<?= $id ?>" class="btn" style="padding:6px 14px;">Edit</a>
            <a href="index.php?navigate=deletepart&id=<?= $id ?>"
               style="padding:6px 14px;background:#dc3545;color:#fff;text-decoration:none;border-radius:3px;margin-left:8px;font-size:13px;"
               onclick="return confirm('Delete this part listing?');">Delete</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<p style="margin-top:16px;">
    <a href="index.php?navigate=browse">&larr; Back to browse</a>
</p>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="this.style.display='none'"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.85);z-index:9999;
            align-items:center;justify-content:center;cursor:zoom-out;">
    <img id="lightbox-img" src="" alt="" style="max-width:90%;max-height:90%;object-fit:contain;" />
</div>
<script>
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}
</script>
