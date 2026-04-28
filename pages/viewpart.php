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

// Load messages — all rows for this part, ordered so replies follow their parent
$_all_msgs = [];
$mq = $CarpartsConnection->prepare(
    "SELECT pm.`id`, pm.`parent_id`, pm.`sender_id`, pm.`recipient_id`, pm.`is_read`,
            pm.`name`, pm.`email`, pm.`message`, pm.`created_at`,
            u.`realname` AS sender_name
     FROM `PART_MESSAGES` pm
     LEFT JOIN `USERS` u ON u.`id` = pm.`sender_id`
     WHERE pm.`part_id` = ?
     ORDER BY COALESCE(pm.`parent_id`, pm.`id`) ASC, pm.`created_at` ASC"
);
if ($mq) {
    $mq->bind_param('i', $id);
    $mq->execute();
    $_res = $mq->get_result();
    $_all_msgs = $_res ? $_res->fetch_all(MYSQLI_ASSOC) : [];
    $mq->close();
}

// Organise into threads: top-level keyed by id, replies nested
$threads = [];
foreach ($_all_msgs as $m) {
    if ($m['parent_id'] === null) {
        $threads[$m['id']] = array_merge($m, ['replies' => []]);
    }
}
foreach ($_all_msgs as $m) {
    if ($m['parent_id'] !== null && isset($threads[$m['parent_id']])) {
        $threads[$m['parent_id']]['replies'][] = $m;
    }
}

// Mark messages directed at the current user as read
if (!empty($_SESSION['user_id'])) {
    $cur_uid = (int)$_SESSION['user_id'];
    $mrq = $CarpartsConnection->prepare(
        "UPDATE `PART_MESSAGES` SET `is_read` = 1, `read_at` = NOW()
         WHERE `part_id` = ? AND `recipient_id` = ? AND `is_read` = 0"
    );
    if ($mrq) { $mrq->bind_param('ii', $id, $cur_uid); $mrq->execute(); $mrq->close(); }
}

// Increment view counter — skip for the seller and admins
if (!$is_seller && empty($_SESSION['isadmin'])) {
    $vc = $CarpartsConnection->prepare("UPDATE `PARTS` SET `view_count` = `view_count` + 1 WHERE `id` = ?");
    if ($vc) { $vc->bind_param('i', $id); $vc->execute(); $vc->close(); }
}

$photos = parts_photos($id);
$compat = parts_compat_get($CarpartsConnection, $id);

// Check if seller's inbox is full (for showing/hiding the contact form)
$seller_inbox_full = false;
if (!$is_seller && empty($_SESSION['isadmin']) && isset($part['seller_id'])) {
    include_once 'settings_helper.php';
    $inbox_limit = (int)settings_get($CarpartsConnection, 'msg_inbox_limit', 50);
    if ($inbox_limit > 0) {
        $ul = $CarpartsConnection->prepare(
            "SELECT COALESCE(`inbox_unlimited`,0) FROM `USERS` WHERE `id`=? LIMIT 1"
        );
        $ul_val = 0;
        if ($ul) {
            $ul->bind_param('i', $part['seller_id']);
            $ul->execute(); $ul->bind_result($ul_val); $ul->fetch(); $ul->close();
        }
        if (!$ul_val) {
            $cnt = $CarpartsConnection->prepare(
                "SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `recipient_id`=? AND `parent_id` IS NULL"
            );
            $cnt_val = 0;
            if ($cnt) {
                $cnt->bind_param('i', $part['seller_id']);
                $cnt->execute(); $cnt->bind_result($cnt_val); $cnt->fetch(); $cnt->close();
            }
            $seller_inbox_full = ($cnt_val >= $inbox_limit);
        }
    }
}

// Related parts — same make, different part, visible, not sold, max 4
$related = [];
$rq = $CarpartsConnection->prepare(
    "SELECT p.`id`, p.`title`, p.`price`, p.`price_type`, m.`name` AS make_name, mo.`name` AS model_name
     FROM `PARTS` p
     JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
     LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
     WHERE p.`make_id` = ? AND p.`id` != ? AND p.`visible` = 1 AND COALESCE(p.`is_sold`,0) = 0
     ORDER BY p.`created_at` DESC LIMIT 4"
);
if ($rq) {
    $rq->bind_param('ii', $part['make_id'], $id);
    $rq->execute();
    $_rres = $rq->get_result();
    $related = $_rres ? $_rres->fetch_all(MYSQLI_ASSOC) : [];
    $rq->close();
}

mysqli_close($CarpartsConnection);

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
$can_edit  = $is_seller || !empty($_SESSION['isadmin']);
$is_sold   = !empty($part['is_sold']);
$csrf      = htmlspecialchars($_SESSION['csrf_token']);

// Amayama: supported makes — Toyota, Nissan, Lexus, Infiniti, Mitsubishi, Honda, Mazda,
//          Subaru, Suzuki, BMW, Mercedes, SEAT, Volkswagen, Skoda
$_amayama_makes = ['toyota','nissan','lexus','infiniti','infinity','mitsubishi','honda',
                   'mazda','subaru','suzuki','bmw','mercedes','mercedes-benz',
                   'seat','volkswagen','vw','skoda','škoda'];
$show_amayama = in_array(strtolower($part['make_name']), $_amayama_makes, true);
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
        <div id="thumb-strip" style="display:flex;flex-wrap:wrap;gap:5px;">
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
        <!-- Inline photo upload -->
        <div style="margin-top:12px;">
            <div id="vp-drop-zone"
                 style="border:2px dashed var(--color-content-border);border-radius:6px;
                        padding:14px 10px;text-align:center;cursor:pointer;
                        background:var(--color-surface);transition:background .15s;">
                <div style="font-size:22px;margin-bottom:4px;">&#128247;</div>
                <div style="font-size:12px;color:var(--color-accent);margin-bottom:8px;">
                    Drop photos here or choose:
                </div>
                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" onclick="document.getElementById('vp-file-gallery').click()"
                            style="padding:5px 12px;font-size:12px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
                        &#128193; Gallery
                    </button>
                    <button type="button" onclick="document.getElementById('vp-file-camera').click()"
                            style="padding:5px 12px;font-size:12px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
                        &#128247; Camera
                    </button>
                </div>
                <input type="file" id="vp-file-gallery" accept="image/*" multiple style="display:none;" />
                <input type="file" id="vp-file-camera" accept="image/*" capture="environment" style="display:none;" />
            </div>
            <div id="vp-upload-progress" style="font-size:11px;margin-top:6px;"></div>
            <?php if (!empty($photos)): ?>
            <p style="margin-top:6px;font-size:12px;text-align:center;">
                <a href="index.php?navigate=deletepartimage&id=<?= $id ?>">Manage / reorder photos</a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Details -->
    <div style="flex:1;min-width:200px;">
        <table style="border-collapse:collapse;font-size:13px;width:100%;">
            <tr>
                <td style="padding:5px 12px 5px 0;font-weight:bold;white-space:nowrap;">Price:</td>
                <td style="padding:5px 0;font-size:20px;font-weight:bold;color:var(--color-accent);" id="field-price">
                    <?php
                    $pt = $part['price_type'] ?? 'fixed';
                    if ($pt === 'bid'): ?>
                    <span style="font-size:15px;color:var(--color-accent);">Make a bid</span>
                    <?php elseif ($part['price'] !== null): ?>
                    &euro;<?= number_format((float)$part['price'], 2, ',', '.') ?>
                    <?php else: ?><span style="font-size:14px;color:#888;font-weight:normal;">On request</span><?php endif; ?>
                    <?php if ($is_sold): ?><span style="font-size:13px;color:#c04040;"> &mdash; sold</span><?php endif; ?>
                    <?php if ($can_edit && !$is_sold): ?>
                    <span class="edit-pencil" onclick="inlineEdit('price','field-price','number','<?= $part['price'] !== null ? htmlspecialchars($part['price']) : '' ?>')"
                          title="Edit price" style="cursor:pointer;font-size:14px;color:#bbb;margin-left:6px;vertical-align:middle;">&#9998;</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding:5px 12px 5px 0;font-weight:bold;">Status:</td>
                <td style="padding:5px 0;">
                    <?php if ($is_sold): ?>
                        <span style="color:#c04040;font-weight:bold;">Sold</span>
                    <?php else: ?>
                        <?= $part['for_sale'] ? 'For sale' : 'Display only' ?>
                    <?php endif; ?>
                    <?php if (!$part['visible']): ?> <span style="color:#c04040;">(Private)</span><?php endif; ?>
                </td>
            </tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Make:</td>
                <td style="padding:5px 0;"><?= htmlspecialchars($part['make_name']) ?></td></tr>
            <?php if ($part['model_name']): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Model:</td>
                <td style="padding:5px 0;"><?= htmlspecialchars($part['model_name']) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Year(s):</td>
                <td style="padding:5px 0;"><?= (int)$part['year_from'] ?><?= $part['year_to'] ? '&ndash;' . (int)$part['year_to'] : '' ?></td></tr>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Condition:</td>
                <td style="padding:5px 0;"><?= (int)$part['condition'] ?>/5 &mdash; <?= htmlspecialchars(parts_condition_label((int)$part['condition'])) ?></td></tr>
            <tr>
                <td style="padding:5px 12px 5px 0;font-weight:bold;white-space:nowrap;">In stock:</td>
                <td style="padding:5px 0;" id="field-stock">
                    <?= (int)$part['stock'] ?>
                    <?php if ($can_edit): ?>
                    <span class="edit-pencil" onclick="inlineEdit('stock','field-stock','number','<?= (int)$part['stock'] ?>')"
                          title="Edit stock" style="cursor:pointer;font-size:14px;color:#bbb;margin-left:6px;vertical-align:middle;">&#9998;</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($part['oem_number'])): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;white-space:nowrap;">OEM number:</td>
                <td style="padding:5px 0;">
                    <span style="font-family:monospace;"><?= htmlspecialchars($part['oem_number']) ?></span>
                    <?php if ($show_amayama): ?>
                    <a href="https://www.amayama.com/en/find?q=<?= urlencode($part['oem_number']) ?>"
                       target="_blank" rel="noopener"
                       style="display:inline-block;margin-left:8px;padding:2px 8px;font-size:11px;
                              background:#e8f0f8;border:1px solid #b0c8e0;border-radius:3px;
                              color:#2255aa;text-decoration:none;vertical-align:middle;white-space:nowrap;"
                       title="Search Amayama parts catalogue">Amayama &#8599;</a>
                    <?php endif; ?>
                </td></tr>
            <?php endif; ?>
            <?php if (!empty($part['replacement_number'])): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;white-space:nowrap;">Replacement OEM:</td>
                <td style="padding:5px 0;">
                    <span style="font-family:monospace;"><?= htmlspecialchars($part['replacement_number']) ?></span>
                    <?php if ($show_amayama): ?>
                    <a href="https://www.amayama.com/en/find?q=<?= urlencode($part['replacement_number']) ?>"
                       target="_blank" rel="noopener"
                       style="display:inline-block;margin-left:8px;padding:2px 8px;font-size:11px;
                              background:#e8f0f8;border:1px solid #b0c8e0;border-radius:3px;
                              color:#2255aa;text-decoration:none;vertical-align:middle;white-space:nowrap;"
                       title="Search Amayama parts catalogue">Amayama &#8599;</a>
                    <?php endif; ?>
                </td></tr>
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
                <td style="padding:5px 0;font-size:12px;color:#888;"><?= htmlspecialchars(substr($part['created_at'], 0, 10)) ?></td></tr>
            <?php if ($can_edit): ?>
            <tr><td style="padding:5px 12px 5px 0;font-weight:bold;">Views:</td>
                <td style="padding:5px 0;font-size:12px;color:#888;"><?= number_format((int)($part['view_count'] ?? 0)) ?></td></tr>
            <?php endif; ?>
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
            <a href="index.php?navigate=editpart&id=<?= $id ?>" class="btn" style="padding:6px 14px;">Edit full listing</a>
            <?php if (!$is_sold): ?>
            <form method="post" action="index.php?navigate=markpartsold" style="display:inline;margin-left:8px;"
                  onsubmit="return confirm('Mark this part as sold? It will be hidden from public listings.');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
                <input type="hidden" name="id" value="<?= $id ?>" />
                <input type="submit" value="Mark as sold"
                       style="padding:6px 14px;background:#c87020;color:#fff;border:none;cursor:pointer;border-radius:3px;font-size:13px;" />
            </form>
            <?php else: ?>
            <form method="post" action="index.php?navigate=markpartsold" style="display:inline;margin-left:8px;"
                  onsubmit="return confirm('Re-list this part as available?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
                <input type="hidden" name="id" value="<?= $id ?>" />
                <input type="hidden" name="undo" value="1" />
                <input type="submit" value="Re-list"
                       style="padding:6px 14px;background:#5588bb;color:#fff;border:none;cursor:pointer;border-radius:3px;font-size:13px;" />
            </form>
            <?php endif; ?>
            <a href="index.php?navigate=deletepart&id=<?= $id ?>"
               style="padding:6px 14px;background:#dc3545;color:#fff;text-decoration:none;border-radius:3px;margin-left:8px;font-size:13px;"
               onclick="return confirm('Delete this part listing permanently?');">Delete</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<p style="margin-top:16px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
    <a href="index.php?navigate=browse">&larr; Back to browse</a>
    <button id="share-btn" onclick="sharePartLink()"
            style="padding:4px 12px;font-size:12px;border-radius:3px;cursor:pointer;
                   border:1px solid var(--color-content-border);background:var(--color-surface);">
        &#128279; Share
    </button>
    <span id="share-copied" style="font-size:12px;color:#2a7a2a;display:none;">&#10003; Link copied!</span>
    <?php if (!empty($_SESSION['authenticated']) && !$is_seller): ?>
    <a href="index.php?navigate=flagpart&id=<?= $id ?>"
       style="font-size:11px;color:#aaa;margin-left:4px;" title="Report this listing">&#9873; Report</a>
    <?php endif; ?>
</p>
</div>

<!-- Q&A / Messages section -->
<div class="content-box" id="qa-section">
<h3>Questions &amp; messages</h3>

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

<?php if (!empty($threads)): ?>
<?php foreach ($threads as $thread):
    $display_name = htmlspecialchars($thread['sender_name'] ?: $thread['name'] ?: 'Anonymous');
?>
<div style="border:1px solid var(--color-content-border);border-radius:5px;margin-bottom:12px;overflow:hidden;">
    <!-- Top-level message -->
    <div style="padding:10px 14px;background:var(--color-surface);">
        <div style="font-size:12px;color:#888;margin-bottom:5px;">
            <strong><?= $display_name ?></strong>
            &mdash; <?= htmlspecialchars(substr($thread['created_at'], 0, 16)) ?>
        </div>
        <div style="font-size:13px;line-height:1.6;"><?= nl2br(htmlspecialchars($thread['message'])) ?></div>
        <?php if ($can_edit): ?>
        <div style="margin-top:6px;display:flex;gap:12px;align-items:center;">
            <button type="button" onclick="toggleReplyForm(<?= (int)$thread['id'] ?>)"
                    style="font-size:11px;color:var(--color-link);background:none;border:none;cursor:pointer;padding:0;">
                &#9998; Reply
            </button>
            <form method="post" action="index.php?navigate=processpartmessage" style="display:inline;"
                  onsubmit="return confirm('Delete this message and all its replies?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
                <input type="hidden" name="action"     value="delete" />
                <input type="hidden" name="id"         value="<?= (int)$thread['id'] ?>" />
                <input type="hidden" name="part_id"    value="<?= $id ?>" />
                <input type="submit" value="Delete"
                       style="font-size:11px;color:#c04040;background:none;border:none;cursor:pointer;padding:0;" />
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Replies -->
    <?php foreach ($thread['replies'] as $reply):
        $reply_name = htmlspecialchars($reply['sender_name'] ?: $reply['name'] ?: 'Seller');
    ?>
    <div style="padding:9px 14px 9px 28px;background:var(--color-nav-hover-bg);
                border-top:1px solid var(--color-content-border);">
        <div style="font-size:12px;color:#888;margin-bottom:4px;">
            <strong><?= $reply_name ?></strong>
            &mdash; <?= htmlspecialchars(substr($reply['created_at'], 0, 16)) ?>
            <span style="color:#5588bb;font-size:10px;margin-left:4px;">&#8618; reply</span>
        </div>
        <div style="font-size:13px;line-height:1.6;"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
        <?php if ($can_edit): ?>
        <form method="post" action="index.php?navigate=processpartmessage" style="margin-top:4px;"
              onsubmit="return confirm('Delete this reply?');">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
            <input type="hidden" name="action"     value="delete" />
            <input type="hidden" name="id"         value="<?= (int)$reply['id'] ?>" />
            <input type="hidden" name="part_id"    value="<?= $id ?>" />
            <input type="submit" value="Delete reply"
                   style="font-size:11px;color:#c04040;background:none;border:none;cursor:pointer;padding:0;" />
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Inline reply form (seller/admin only) -->
    <?php if ($can_edit): ?>
    <div id="reply-form-<?= (int)$thread['id'] ?>" style="display:none;padding:10px 14px;
         border-top:1px solid var(--color-content-border);background:var(--color-content-bg,var(--color-surface));">
        <form method="post" action="index.php?navigate=processmessagereply">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
            <input type="hidden" name="parent_id"  value="<?= (int)$thread['id'] ?>" />
            <input type="hidden" name="part_id"    value="<?= $id ?>" />
            <textarea name="message" rows="3" required maxlength="2000"
                      style="width:100%;padding:5px;font-size:13px;"
                      placeholder="Type your reply…"></textarea><br>
            <div style="margin-top:6px;display:flex;gap:8px;">
                <input type="submit" value="Send reply" class="btn" style="padding:5px 14px;font-size:12px;" />
                <button type="button" onclick="toggleReplyForm(<?= (int)$thread['id'] ?>)"
                        style="padding:5px 10px;font-size:12px;background:none;border:1px solid var(--color-content-border);border-radius:3px;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<p style="color:#888;font-size:13px;">No messages yet.</p>
<?php endif; ?>

<?php if (!$is_seller): ?>
<h4 style="margin-top:16px;">Send a message to the seller</h4>
<?php if ($seller_inbox_full): ?>
<p style="color:#c87020;font-size:13px;background:var(--color-nav-hover-bg);
          padding:10px 14px;border-radius:4px;border:1px solid var(--color-content-border);">
    &#9993; This seller's inbox is currently full. You cannot send a message at this time.
    Please try again later or find their contact details on their profile.
</p>
<?php else: ?>
<form method="post" action="index.php?navigate=processpartmessage">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>" />
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
<?php endif; // seller_inbox_full ?>
<?php endif; // !is_seller ?>
</div>

<script>
function toggleReplyForm(id) {
    var el = document.getElementById('reply-form-' + id);
    if (!el) return;
    var shown = el.style.display !== 'none';
    el.style.display = shown ? 'none' : '';
    if (!shown) el.querySelector('textarea').focus();
}
</script>

<?php if (!empty($related)): ?>
<!-- Related parts -->
<div class="content-box">
<h3>You might be interested in</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;">
<?php foreach ($related as $rp):
    $rthumb = parts_first_photo((int)$rp['id']);
?>
<a href="index.php?navigate=viewpart&id=<?= (int)$rp['id'] ?>"
   style="display:block;border:1px solid var(--color-content-border);border-radius:5px;
          overflow:hidden;text-decoration:none;color:inherit;background:var(--color-surface);
          transition:box-shadow .15s;"
   onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.15)'"
   onmouseout="this.style.boxShadow='none'">
    <?php if ($rthumb): ?>
    <img src="<?= htmlspecialchars($rthumb) ?>" alt=""
         style="width:100%;height:100px;object-fit:cover;display:block;" />
    <?php else: ?>
    <div style="width:100%;height:100px;background:var(--color-input-bg);
                display:flex;align-items:center;justify-content:center;font-size:24px;">&#128295;</div>
    <?php endif; ?>
    <div style="padding:6px 8px;">
        <div style="font-size:12px;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= htmlspecialchars($rp['title']) ?>
        </div>
        <div style="font-size:12px;color:var(--color-accent);font-weight:bold;margin-top:2px;">
            <?php $rpt = $rp['price_type'] ?? 'fixed';
                  echo $rpt === 'bid' ? '<span style="font-size:11px;color:var(--color-accent);">Make a bid</span>'
                     : ($rp['price'] !== null ? '&euro;' . number_format((float)$rp['price'], 2, ',', '.') : '<span style="font-size:11px;color:#888;font-weight:normal;">On request</span>'); ?>
        </div>
    </div>
</a>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);
     z-index:9999;align-items:center;justify-content:center;">
    <!-- Backdrop — tap to close -->
    <div onclick="lbClose()" style="position:absolute;inset:0;cursor:zoom-out;"></div>
    <!-- Image -->
    <img id="lightbox-img" src="" alt=""
         style="position:relative;max-width:90vw;max-height:86vh;object-fit:contain;
                border-radius:3px;z-index:1;user-select:none;-webkit-user-drag:none;" />
    <!-- Prev -->
    <button id="lb-prev" onclick="lbPrev()"
            style="position:absolute;left:10px;top:50%;transform:translateY(-50%);
                   background:rgba(255,255,255,0.18);border:none;color:#fff;font-size:26px;
                   padding:10px 16px;cursor:pointer;border-radius:4px;z-index:2;line-height:1;">&#10094;</button>
    <!-- Next -->
    <button id="lb-next" onclick="lbNext()"
            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                   background:rgba(255,255,255,0.18);border:none;color:#fff;font-size:26px;
                   padding:10px 16px;cursor:pointer;border-radius:4px;z-index:2;line-height:1;">&#10095;</button>
    <!-- Counter -->
    <div id="lb-counter"
         style="position:absolute;bottom:14px;left:50%;transform:translateX(-50%);
                color:#fff;font-size:13px;background:rgba(0,0,0,0.5);
                padding:3px 12px;border-radius:10px;z-index:2;white-space:nowrap;"></div>
    <!-- Close -->
    <button onclick="lbClose()"
            style="position:absolute;top:12px;right:12px;background:rgba(255,255,255,0.18);
                   border:none;color:#fff;font-size:20px;padding:5px 12px;cursor:pointer;
                   border-radius:4px;z-index:2;">&times;</button>
</div>

<script>
var _partId  = <?= $id ?>;
var _csrf    = '<?= $csrf ?>';
var _photos  = <?= json_encode(array_values($photos)) ?>;
var _lbIdx   = 0;
var _lbTouchX = 0;

// ── Lightbox ──────────────────────────────────────────────────────────────────
function openLightbox(src) {
    // Match by stripping query strings for comparison
    var bare = src.split('?')[0];
    _lbIdx = 0;
    for (var i = 0; i < _photos.length; i++) {
        if (_photos[i].split('?')[0] === bare) { _lbIdx = i; break; }
    }
    lbShow();
}
function lbShow() {
    document.getElementById('lightbox-img').src = _photos[_lbIdx] + '?t=' + Date.now();
    document.getElementById('lb-counter').textContent = (_photos.length > 1)
        ? (_lbIdx + 1) + ' / ' + _photos.length : '';
    document.getElementById('lb-prev').style.display = (_lbIdx > 0) ? '' : 'none';
    document.getElementById('lb-next').style.display = (_lbIdx < _photos.length - 1) ? '' : 'none';
    document.getElementById('lightbox').style.display = 'flex';
}
function lbPrev() { if (_lbIdx > 0) { _lbIdx--; lbShow(); } }
function lbNext() { if (_lbIdx < _photos.length - 1) { _lbIdx++; lbShow(); } }
function lbClose() { document.getElementById('lightbox').style.display = 'none'; }

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightbox').style.display !== 'flex') return;
    if (e.key === 'ArrowLeft')  lbPrev();
    if (e.key === 'ArrowRight') lbNext();
    if (e.key === 'Escape')     lbClose();
});

// Touch swipe — track on image only to avoid conflicting with backdrop tap-to-close
(function() {
    var img = document.getElementById('lightbox-img');
    img.addEventListener('touchstart', function(e) {
        _lbTouchX = e.changedTouches[0].screenX;
    }, {passive: true});
    img.addEventListener('touchend', function(e) {
        var dx = e.changedTouches[0].screenX - _lbTouchX;
        if (dx < -40) lbNext();
        else if (dx > 40) lbPrev();
    }, {passive: true});
})();

// ── Share ─────────────────────────────────────────────────────────────────────
function sharePartLink() {
    var url = window.location.origin + window.location.pathname + '?navigate=viewpart&id=<?= $id ?>';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(showCopied);
    } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showCopied();
    }
}
function showCopied() {
    var el = document.getElementById('share-copied');
    el.style.display = 'inline';
    setTimeout(function() { el.style.display = 'none'; }, 2500);
}

<?php if ($can_edit): ?>
// ── Inline field edit ─────────────────────────────────────────────────────────
function inlineEdit(field, cellId, inputType, currentRaw) {
    var cell = document.getElementById(cellId);
    if (cell.dataset.editing) return;
    cell.dataset.editing = '1';

    var pencil = cell.querySelector('.edit-pencil');
    var origHTML = cell.innerHTML;

    var inp = document.createElement('input');
    inp.type = inputType || 'text';
    inp.value = currentRaw;
    inp.placeholder = field === 'price' ? 'blank = on request' : '';
    inp.style.cssText = 'width:110px;padding:3px 5px;font-size:14px;vertical-align:middle;';

    var saveBtn = document.createElement('button');
    saveBtn.textContent = '✓';
    saveBtn.style.cssText = 'padding:2px 8px;margin-left:5px;cursor:pointer;';

    var cancelBtn = document.createElement('button');
    cancelBtn.textContent = '✕';
    cancelBtn.style.cssText = 'padding:2px 6px;margin-left:4px;cursor:pointer;';

    cell.innerHTML = '';
    cell.appendChild(inp);
    cell.appendChild(saveBtn);
    cell.appendChild(cancelBtn);
    inp.focus(); inp.select();

    function cancel() {
        cell.innerHTML = origHTML;
        delete cell.dataset.editing;
    }

    function save() {
        saveBtn.disabled = true;
        fetch('index.php?navigate=inlineeditpart&ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(_csrf)
                + '&part_id=' + _partId
                + '&field='   + encodeURIComponent(field)
                + '&value='   + encodeURIComponent(inp.value)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                cell.innerHTML = d.display
                    + ' <span class="edit-pencil" onclick="inlineEdit(\'' + field + '\',\'' + cellId + '\',\'' + inputType + '\',\'' + d.raw.replace(/'/g,"\\'") + '\')"'
                    + ' title="Edit" style="cursor:pointer;font-size:14px;color:#bbb;margin-left:6px;vertical-align:middle;">&#9998;</span>';
            } else {
                alert('Save failed: ' + (d.error || 'Unknown error'));
                cancel();
            }
            delete cell.dataset.editing;
        })
        .catch(function() { alert('Network error'); cancel(); });
    }

    saveBtn.addEventListener('click', save);
    cancelBtn.addEventListener('click', cancel);
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter')  save();
        if (e.key === 'Escape') cancel();
    });
}

// ── Inline photo upload ───────────────────────────────────────────────────────
(function() {
    var zone    = document.getElementById('vp-drop-zone');
    var gallery = document.getElementById('vp-file-gallery');
    var camera  = document.getElementById('vp-file-camera');
    var prog    = document.getElementById('vp-upload-progress');

    if (!zone) return;

    zone.addEventListener('dragover',  function(e) { e.preventDefault(); zone.style.background = 'var(--color-input-bg)'; });
    zone.addEventListener('dragleave', function()  { zone.style.background = 'var(--color-surface)'; });
    zone.addEventListener('drop',      function(e) { e.preventDefault(); zone.style.background = 'var(--color-surface)'; uploadFiles(e.dataTransfer.files); });
    gallery.addEventListener('change', function() { uploadFiles(this.files); this.value = ''; });
    camera.addEventListener('change',  function() { uploadFiles(this.files); this.value = ''; });

    function uploadFiles(files) { Array.from(files).forEach(uploadOne); }

    function uploadOne(file) {
        var row = document.createElement('div');
        row.style.cssText = 'font-size:11px;color:#888;margin:2px 0;';
        row.textContent = '↑ ' + file.name + '…';
        prog.appendChild(row);

        var fd = new FormData();
        fd.append('csrf_token', _csrf);
        fd.append('id', _partId);
        fd.append('photo', file);

        fetch('index.php?navigate=uploadpartimage&id=' + _partId + '&ajax=1', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                row.textContent = '✓ ' + file.name;
                row.style.color = '#2a7a2a';

                // Append to thumb strip or main photo slot
                var strip = document.getElementById('thumb-strip');
                var mainImg = document.getElementById('main-photo');
                var newSrc = d.path + '?t=' + Date.now();

                if (!mainImg) {
                    // No photos existed yet — reload to show full widget
                    location.reload();
                    return;
                }
                var img = document.createElement('img');
                img.src = newSrc;
                img.style.cssText = 'width:60px;height:45px;object-fit:cover;cursor:pointer;border-radius:3px;border:1px solid var(--color-content-border);';
                img.addEventListener('click', function() { mainImg.src = this.src; });
                if (strip) strip.appendChild(img);
            } else {
                row.textContent = '✗ ' + file.name + ': ' + (d.error || 'Upload failed');
                row.style.color = '#c04040';
            }
        })
        .catch(function() {
            row.textContent = '✗ ' + file.name + ': Network error';
            row.style.color = '#c04040';
        });
    }
})();
<?php endif; ?>
</script>
