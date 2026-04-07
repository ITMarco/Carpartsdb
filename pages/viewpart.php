<?php
include 'connection.php';
include_once 'parts_helper.php';

parts_ensure_table($CarpartsConnection);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Invalid part ID.</p><p><a href='index.php?navigate=browse'>Back to browse</a></p></div>";
    return;
}

$is_member = !empty($_SESSION['is_member']) || !empty($_SESSION['isadmin']);

$part = parts_get($CarpartsConnection, $id, true);

$is_seller = $part && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];

// Check access for hidden/public/private parts
if ($part) {
    if (!$part['visible'] && !$is_seller && empty($_SESSION['isadmin'])) {
        $part = null;
    } elseif ($part['visible_private'] && !$is_member) {
        $part = null;
    }
}

if (!$part) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Part not found.</p><p><a href='index.php?navigate=browse'>Back to browse</a></p></div>";
    return;
}

// Load messages
$messages = [];
$mq = $CarpartsConnection->prepare(
    "SELECT pm.`id`, pm.`name`, pm.`email`, pm.`message`, pm.`created_at`,
            u.`realname` AS sender_name
     FROM `PART_MESSAGES` pm
     LEFT JOIN `USERS` u ON u.`id` = pm.`sender_id`
     WHERE pm.`part_id` = ?
     ORDER BY pm.`created_at` ASC"
);
if ($mq) {
    $mq->bind_param('i', $id);
    $mq->execute();
    $messages = $mq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mq->close();
}

// Increment view counter — skip for the seller and admins
if (!$is_seller && empty($_SESSION['isadmin'])) {
    $CarpartsConnection->query("UPDATE `PARTS` SET `view_count` = `view_count` + 1 WHERE `id` = {$id}");
}

$photos = parts_photos($id);
$compat = parts_compat_get($CarpartsConnection, $id);
mysqli_close($CarpartsConnection);

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
$can_edit  = $is_seller || !empty($_SESSION['isadmin']);
$is_sold   = !empty($part['is_sold']);
?>

<div class="content-box">
<h3><?= htmlspecialchars($part['title']) ?>
<?php if ($is_sold): ?>
  <span style="display:inline-block;background:#c04040;color:#fff;font-size:13px;font-weight:normal;
               padding:2px 10px;border-radius:3px;vertical-align:middle;margin-left:8px;">SOLD</span>
<?php endif; ?>
</h3>
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
                 onclick="document.getElementById('main-photo').src=this.src;" />
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
                    <?php if ($part['price'] !== null): ?>
                    &euro;<?= number_format((float)$part['price'], 2, ',', '.') ?>
                    <?php else: ?><span style="font-size:14px;color:#888;font-weight:normal;">Price on request</span><?php endif; ?>
                    <?php if ($is_sold): ?><span style="font-size:13px;color:#c04040;"> &mdash; sold</span><?php endif; ?>
                </td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Status:</td>
                <td style="padding:5px 0;">
                    <?php if ($is_sold): ?>
                        <span style="color:#c04040;font-weight:bold;">Sold</span>
                    <?php else: ?>
                        <?= $part['for_sale'] ? 'For sale' : 'Display only' ?>
                    <?php endif; ?>
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
                    <?php if (!empty($_SESSION['authenticated'])): ?>
                    <a href="index.php?navigate=userprofile&id=<?= (int)$part['seller_id'] ?>">
                        <?= htmlspecialchars($part['seller_name'] ?: $part['seller_email']) ?>
                    </a>
                    <?php else: ?>
                    <?= htmlspecialchars($part['seller_name'] ?: 'Member') ?>
                    <?php endif; ?>
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

        <?php if (!empty($compat)): ?>
        <h4 style="margin-top:14px;">Also fits</h4>
        <ul style="margin:4px 0 0 0;padding-left:18px;font-size:13px;line-height:1.8;">
            <?php foreach ($compat as $c): ?>
            <li><?= htmlspecialchars($c['make_name']) ?>
                <?= $c['model_name'] ? ' &mdash; ' . htmlspecialchars($c['model_name']) : '' ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!$is_seller && !$is_sold): ?>
        <p style="margin-top:14px;">
            <a href="#qa-section"
               onclick="document.getElementById('msg-message').value='Hi, I am interested in your <?= htmlspecialchars(addslashes(parts_ref($id))) ?> — <?= htmlspecialchars(addslashes($part['title'])) ?>.\n\n';document.getElementById('qa-section').scrollIntoView({behavior:'smooth'});document.getElementById('msg-message').focus();return false;"
               class="btn" style="padding:6px 14px;">
                &#9993; Contact seller
            </a>
        </p>
        <?php endif; ?>

        <?php if ($can_edit): ?>
        <p style="margin-top:14px;">
            <a href="index.php?navigate=editpart&id=<?= $id ?>" class="btn" style="padding:6px 14px;">Edit</a>
            <?php if (!$is_sold): ?>
            <a href="index.php?navigate=markpartsold&id=<?= $id ?>"
               style="padding:6px 14px;background:#c87020;color:#fff;text-decoration:none;border-radius:3px;margin-left:8px;font-size:13px;"
               onclick="return confirm('Mark this part as sold? It will be hidden from public listings.');">Mark as sold</a>
            <?php else: ?>
            <a href="index.php?navigate=markpartsold&id=<?= $id ?>&undo=1"
               style="padding:6px 14px;background:#5588bb;color:#fff;text-decoration:none;border-radius:3px;margin-left:8px;font-size:13px;"
               onclick="return confirm('Re-list this part as available?');">Re-list</a>
            <?php endif; ?>
            <a href="index.php?navigate=deletepart&id=<?= $id ?>"
               style="padding:6px 14px;background:#dc3545;color:#fff;text-decoration:none;border-radius:3px;margin-left:8px;font-size:13px;"
               onclick="return confirm('Delete this part listing permanently?');">Delete</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<p style="margin-top:16px;">
    <a href="index.php?navigate=browse">&larr; Back to browse</a>
    <?php if (!empty($_SESSION['authenticated']) && !$is_seller): ?>
    &nbsp;&nbsp;
    <a href="index.php?navigate=flagpart&id=<?= $id ?>"
       style="font-size:11px;color:#aaa;" title="Report this listing">&#9873; Report</a>
    <?php endif; ?>
</p>
</div>

<!-- Q&A / Messages section -->
<div class="content-box" id="qa-section">
<h3>Questions &amp; messages</h3>

<?php if (!empty($messages)): ?>
<?php foreach ($messages as $msg): ?>
<div style="border:1px solid var(--color-content-border);border-radius:4px;padding:10px 14px;margin-bottom:10px;background:var(--color-surface);">
    <div style="font-size:12px;color:#888;margin-bottom:4px;">
        <strong><?= htmlspecialchars($msg['sender_name'] ?: $msg['name'] ?: 'Anonymous') ?></strong>
        &mdash; <?= htmlspecialchars(substr($msg['created_at'], 0, 16)) ?>
    </div>
    <div style="font-size:13px;line-height:1.5;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
    <?php if ($can_edit): ?>
    <div style="margin-top:6px;">
        <a href="index.php?navigate=processpartmessage&action=delete&id=<?= (int)$msg['id'] ?>&part_id=<?= $id ?>&csrf=<?= urlencode($_SESSION['csrf_token']) ?>"
           style="font-size:11px;color:#c04040;"
           onclick="return confirm('Delete this message?');">Delete</a>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<p style="color:#888;font-size:13px;">No messages yet.</p>
<?php endif; ?>

<?php if (!$is_seller): ?>
<h4 style="margin-top:16px;">Send a message to the seller</h4>
<?php
$msg_sent  = !empty($_GET['msg_sent']);
$msg_error = $_GET['msg_error'] ?? '';
if ($msg_sent): ?>
<div style="background:#d4edda;border:1px solid #28a745;padding:10px;border-radius:4px;margin-bottom:12px;">
    Message sent! The seller will get back to you.
</div>
<?php elseif ($msg_error): ?>
<div style="color:red;margin-bottom:10px;"><?= htmlspecialchars($msg_error) ?></div>
<?php endif; ?>

<form method="post" action="index.php?navigate=processpartmessage">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="part_id"   value="<?= $id ?>" />
    <?php if (empty($_SESSION['authenticated'])): ?>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
            <label style="font-size:12px;"><strong>Your name:</strong></label><br>
            <input type="text" name="name" maxlength="80" required style="width:200px;padding:5px;" />
        </div>
        <div>
            <label style="font-size:12px;"><strong>Your email:</strong></label><br>
            <input type="email" name="email" maxlength="255" required style="width:220px;padding:5px;" />
        </div>
    </div>
    <?php endif; ?>
    <label style="font-size:12px;"><strong>Message / question / bid:</strong></label><br>
    <textarea name="message" id="msg-message" rows="4" required maxlength="2000"
              style="width:100%;max-width:480px;padding:5px;margin-top:4px;"
              placeholder="Ask a question, make an offer…"></textarea><br><br>
    <input type="submit" value="Send message" class="btn" style="padding:7px 18px;" />
</form>
<?php endif; ?>
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
