<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Access denied. <a href='index.php?navigate=secureadmin'>Log in</a></div>";
    return;
}
if (!defined('SNLDBCARPARTS_ACCESS')) define('SNLDBCARPARTS_ACCESS', 1);
include_once 'connection.php';
include_once 'settings_helper.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = '<div style="color:red;padding:8px;">CSRF token mismatch.</div>';
    } else {
        $lic = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($_POST['featured_license'] ?? '')));
        $cap = trim($_POST['featured_caption'] ?? '');
        $img = trim($_POST['featured_image'] ?? '');
        settings_set($SNLDBConnection, 'featured_license', $lic);
        settings_set($SNLDBConnection, 'featured_caption', $cap);
        settings_set($SNLDBConnection, 'featured_image',   $img);
        $msg = '<div style="color:green;padding:8px 0;">✓ Supra van de maand bijgewerkt!</div>';
    }
}

$cur_lic = settings_get($SNLDBConnection, 'featured_license', '');
$cur_cap = settings_get($SNLDBConnection, 'featured_caption', '');
$cur_img = settings_get($SNLDBConnection, 'featured_image',   '');

// Look up the current featured car
$featured_car = null;
if ($cur_lic !== '') {
    $fl  = '%' . $cur_lic . '%';
    $fst = $SNLDBConnection->prepare(
        "SELECT * FROM SNLDB WHERE REPLACE(REPLACE(License,'-',''),' ','') LIKE ? LIMIT 1"
    );
    if ($fst) {
        $fst->bind_param('s', $fl);
        $fst->execute();
        $res = $fst->get_result();
        $featured_car = $res ? $res->fetch_assoc() : null;
        $fst->close();
    }
}

// Find all slides for this car (for the picker)
$all_slides = [];
if ($featured_car) {
    $strip = strtoupper(preg_replace('/\s*/m', '', $featured_car['License']));

    // Preferred: parse full image paths from the bolgallery static cache
    $gal_html = "./bolgallerycars/cars{$strip}slides_bolGalleryStaticPage.html";
    if (file_exists($gal_html)) {
        preg_match_all('/data-full="([^"]+)"/', file_get_contents($gal_html), $matches);
        $all_slides = $matches[1] ?? [];
    }

    // Fallback: direct glob with individual extensions (avoids GLOB_BRACE issues)
    if (empty($all_slides)) {
        $dir = "./cars/{$strip}/slides/";
        foreach (['jpg','jpeg','png','webp'] as $ext) {
            $all_slides = array_merge($all_slides, glob("{$dir}*.{$ext}") ?: []);
        }
        usort($all_slides, fn($a, $b) => filemtime($b) - filemtime($a));
    }
}

// The preview to show: explicit choice > first slide
$preview_img = $cur_img ?: ($all_slides[0] ?? '');

?>
<div class="content-box">
<h3>Supra van de maand beheer</h3>

<?= $msg ?>

<?php if ($cur_lic !== '' && !$featured_car): ?>
<div style="color:orange;padding:6px 10px;background:#fff3cd;border:1px solid #ffe08a;border-radius:4px;margin-bottom:12px;font-size:12px;">
    ⚠ Opgeslagen kenteken: <strong><?= htmlspecialchars($cur_lic) ?></strong> — niet gevonden in SNLDB.<br>
    Controleer of het kenteken exact overeenkomt (zonder streepjes, bijv. <code>37HXR9</code>).
</div>
<?php endif; ?>

<form method="post" id="fc-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="featured_image" id="featured_image_input" value="<?= htmlspecialchars($cur_img) ?>" />

    <table style="border-collapse:collapse;width:100%;max-width:540px;">
        <tr>
            <td style="padding:6px 8px 6px 0;font-size:12px;font-weight:bold;white-space:nowrap;">Kenteken</td>
            <td style="padding:6px 0;">
                <input type="text" name="featured_license"
                       value="<?= htmlspecialchars($cur_lic) ?>"
                       placeholder="bijv. 37HXR9 of 37-HXR-9" maxlength="12"
                       style="padding:6px 10px;font-size:13px;width:180px;
                              border:1px solid var(--color-content-border);border-radius:4px;" />
                <span style="font-size:11px;color:#5a7a90;margin-left:6px;">Streepjes worden automatisch genegeerd. Leeglaten = geen widget.</span>
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px 6px 0;font-size:12px;font-weight:bold;">Bijschrift</td>
            <td style="padding:6px 0;">
                <input type="text" name="featured_caption"
                       value="<?= htmlspecialchars($cur_cap) ?>"
                       placeholder="Optioneel tekstje bij de supra" maxlength="200"
                       style="padding:6px 10px;font-size:13px;width:320px;
                              border:1px solid var(--color-content-border);border-radius:4px;" />
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding:10px 0 0 0;">
                <input type="submit" value="Opslaan" class="btn" />
                <?php if ($cur_lic !== ''): ?>
                &nbsp;
                <button type="submit" name="featured_license" value="" class="btn btn-ghost"
                        onclick="document.getElementById('featured_image_input').value='';
                                 document.querySelector('[name=featured_caption]').value='';">
                    Widget verwijderen
                </button>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</form>

<?php if ($featured_car): ?>

<!-- Current car info -->
<div style="background:var(--color-input-bg);border:1px solid var(--color-content-border);
            border-radius:6px;padding:14px 18px;margin-top:18px;display:flex;gap:16px;align-items:flex-start;">
    <?php if ($preview_img): ?>
    <img id="fc-preview" src="<?= htmlspecialchars($preview_img) ?>" alt="featured"
         style="width:160px;height:110px;object-fit:cover;border-radius:4px;flex-shrink:0;
                border:2px solid var(--color-accent);" />
    <?php endif; ?>
    <div>
        <div style="font-size:11px;font-weight:bold;text-transform:uppercase;
                    letter-spacing:1px;color:var(--color-accent);margin-bottom:6px;">Geselecteerde supra</div>
        <div style="font-size:16px;font-weight:bold;">
            <a href="index.php?navigate=<?= htmlspecialchars($featured_car['License']) ?>"
               style="color:var(--color-link);"><?= htmlspecialchars($featured_car['License']) ?></a>
        </div>
        <div style="font-size:12px;color:#5a7a90;margin-top:3px;">
            <?= htmlspecialchars($featured_car['Choise_Model']) ?>
            <?php if ($featured_car['Owner_display']): ?>
             — <?= htmlspecialchars($featured_car['Owner_display']) ?>
            <?php endif; ?>
        </div>
        <?php if ($cur_img !== ''): ?>
        <div style="font-size:11px;color:#5a7a90;margin-top:6px;">
            📷 Handmatig gekozen foto
            <a href="#" onclick="document.getElementById('featured_image_input').value='';
                                  document.getElementById('fc-form').submit();return false;"
               style="color:var(--color-link);margin-left:6px;font-size:11px;">Wis keuze (gebruik nieuwste)</a>
        </div>
        <?php else: ?>
        <div style="font-size:11px;color:#8aabbf;margin-top:6px;">📷 Automatisch: nieuwste foto</div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($all_slides)): ?>
<div style="color:#8aabbf;font-size:12px;font-style:italic;margin-top:14px;">
    Geen foto's gevonden voor <?= htmlspecialchars($strip) ?> in <code>cars/<?= htmlspecialchars($strip) ?>/slides/</code>
</div>
<?php elseif (!empty($all_slides)): ?>
<!-- Photo picker -->
<div style="margin-top:16px;">
    <div style="font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;
                color:var(--color-accent);margin-bottom:10px;">
        Kies een foto — <?= count($all_slides) ?> beschikbaar, klik om te selecteren
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($all_slides as $slide):
            $is_selected = ($slide === $cur_img) || ($cur_img === '' && $slide === $all_slides[0]);
        ?>
        <div onclick="fcPickPhoto('<?= addslashes(htmlspecialchars($slide)) ?>')"
             style="cursor:pointer;border-radius:4px;overflow:hidden;flex-shrink:0;
                    border:3px solid <?= $is_selected ? 'var(--color-accent)' : 'transparent' ?>;
                    transition:border-color .15s;"
             class="fc-thumb" data-path="<?= htmlspecialchars($slide) ?>">
            <img src="<?= htmlspecialchars($slide) ?>" alt=""
                 style="width:100px;height:68px;object-fit:cover;display:block;" loading="lazy" />
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
function fcPickPhoto(path) {
    document.getElementById('featured_image_input').value = path;
    // Update preview
    var prev = document.getElementById('fc-preview');
    if (prev) prev.src = path;
    // Highlight selected thumb
    document.querySelectorAll('.fc-thumb').forEach(function(el) {
        el.style.borderColor = el.getAttribute('data-path') === path
            ? 'var(--color-accent)' : 'transparent';
    });
}
</script>
<?php endif; ?>

<?php elseif ($cur_lic !== ''): ?>
<div style="color:orange;padding:8px 0;">
    Kenteken <strong><?= htmlspecialchars($cur_lic) ?></strong> niet gevonden in de database.
</div>
<?php endif; ?>

</div>
