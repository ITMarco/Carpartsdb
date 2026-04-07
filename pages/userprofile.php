<?php
// Anonymous users can never see profiles
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to view user profiles.</p></div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'users_helper.php';
include_once 'parts_helper.php';

users_ensure_table($CarpartsConnection);

$profile_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$viewer_id   = (int)$_SESSION['user_id'];
$is_admin    = !empty($_SESSION['isadmin']);
$is_member   = !empty($_SESSION['is_member']) || $is_admin;
$is_own      = ($profile_id === $viewer_id);

if ($profile_id <= 0) {
    // Default to own profile
    $profile_id = $viewer_id;
    $is_own = true;
}

$stmt = $CarpartsConnection->prepare(
    "SELECT `id`,`email`,`realname`,`isadmin`,`is_member`,`created_at`,
            `phone`,`address`,`bio`,`show_contact_to_members`
     FROM `USERS` WHERE `id` = ? LIMIT 1"
);
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>User not found.</p></div>";
    return;
}

// Visibility: contact info shown to incrowd always, to regular members if owner allows it
$show_contact = $is_admin || $is_own
    || $is_member                                          // incrowd always sees it
    || (!empty($profile['show_contact_to_members']));     // owner allows all logged-in

// Count active listings
$cr = $CarpartsConnection->prepare(
    "SELECT COUNT(*) FROM `PARTS` WHERE `seller_id`=? AND `visible`=1 AND COALESCE(`is_sold`,0)=0"
);
$cr->bind_param('i', $profile_id);
$cr->execute();
$cr->bind_result($listing_count);
$cr->fetch();
$cr->close();

// Recent 5 active listings (public ones only, unless own/admin)
$vis_clause = ($is_own || $is_admin) ? '' : "AND p.`visible`=1 AND COALESCE(p.`is_sold`,0)=0" . ($is_member ? '' : " AND p.`visible_private`=0");
$recent = $CarpartsConnection->query(
    "SELECT p.`id`, p.`title`, p.`price`, p.`is_sold`, m.`name` AS make_name
     FROM `PARTS` p JOIN `CAR_MAKES` m ON m.`id`=p.`make_id`
     WHERE p.`seller_id`={$profile_id} {$vis_clause}
     ORDER BY p.`created_at` DESC LIMIT 5"
);

mysqli_close($CarpartsConnection);

$flash = '';
if (!empty($_GET['saved'])) $flash = 'Profile saved.';
?>
<div class="content-box">

<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #28a745;color:#155724;padding:10px 14px;
            border-radius:4px;margin-bottom:14px;font-size:13px;"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
        <h3 style="margin:0;"><?= htmlspecialchars($profile['realname'] ?: $profile['email']) ?></h3>
        <small style="color:#888;">
            Member since <?= htmlspecialchars(substr($profile['created_at'], 0, 7)) ?>
            <?= $profile['isadmin']   ? ' &mdash; <strong style="color:#c04040;">Admin</strong>' : '' ?>
            <?= $profile['is_member'] ? ' &mdash; <span style="color:#448;font-weight:bold;">Incrowd</span>' : '' ?>
        </small>
    </div>
    <?php if ($is_own): ?>
    <a href="index.php?navigate=edituserprofile" class="btn" style="padding:6px 14px;font-size:13px;">Edit my profile</a>
    <?php endif; ?>
</div>

<?php if (!empty($profile['bio'])): ?>
<div style="margin-top:14px;font-size:13px;line-height:1.6;color:var(--color-text);">
    <?= nl2br(htmlspecialchars($profile['bio'])) ?>
</div>
<?php endif; ?>

<?php if ($show_contact && (!empty($profile['phone']) || !empty($profile['address']))): ?>
<div style="margin-top:14px;border-top:1px solid var(--color-content-border);padding-top:12px;">
    <h4 style="margin:0 0 8px;">Contact info</h4>
    <table style="font-size:13px;border-collapse:collapse;">
        <?php if (!empty($profile['phone'])): ?>
        <tr>
            <td style="padding:3px 16px 3px 0;font-weight:bold;white-space:nowrap;">Phone:</td>
            <td style="padding:3px 0;"><?= htmlspecialchars($profile['phone']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($profile['address'])): ?>
        <tr>
            <td style="padding:3px 16px 3px 0;font-weight:bold;white-space:nowrap;vertical-align:top;">Address:</td>
            <td style="padding:3px 0;"><?= nl2br(htmlspecialchars($profile['address'])) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="padding:3px 16px 3px 0;font-weight:bold;">Email:</td>
            <td style="padding:3px 0;"><a href="mailto:<?= htmlspecialchars($profile['email']) ?>"><?= htmlspecialchars($profile['email']) ?></a></td>
        </tr>
    </table>
</div>
<?php elseif (!$show_contact && ($is_own || !empty($profile['phone']) || !empty($profile['address']))): ?>
<p style="margin-top:14px;font-size:12px;color:#888;"><em>Contact info is not shown to regular members. <a href="index.php?navigate=edituserprofile">Edit your profile</a> to change this.</em></p>
<?php endif; ?>

<div style="margin-top:18px;border-top:1px solid var(--color-content-border);padding-top:12px;">
    <h4 style="margin:0 0 8px;"><?= $is_own ? 'My listings' : 'Active listings' ?>
        <small style="font-weight:normal;color:#888;font-size:13px;"><?= number_format($listing_count) ?> active</small>
    </h4>
    <?php if ($recent && $recent->num_rows > 0): ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <?php while ($r = $recent->fetch_assoc()): ?>
    <tr style="border-bottom:1px solid var(--color-nav-border);">
        <td style="padding:5px 10px 5px 0;">
            <a href="index.php?navigate=viewpart&id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a>
            <?php if ($r['is_sold']): ?><span style="font-size:11px;color:#c04040;"> [sold]</span><?php endif; ?>
        </td>
        <td style="padding:5px 10px;color:#888;"><?= htmlspecialchars($r['make_name']) ?></td>
        <td style="padding:5px 0;text-align:right;">
            <?= $r['price'] !== null ? '&euro;' . number_format((float)$r['price'], 2, ',', '.') : '<span style="color:#888;font-size:11px;">On request</span>' ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </table>
    <?php if ($listing_count > 5): ?>
    <p style="font-size:12px;margin-top:6px;">
        <a href="index.php?navigate=browse&seller=<?= $profile_id ?>">View all <?= $listing_count ?> listings &rarr;</a>
    </p>
    <?php endif; ?>
    <?php else: ?>
    <p style="color:#888;font-size:13px;">No active listings.</p>
    <?php endif; ?>
</div>

</div>
