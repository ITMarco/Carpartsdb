<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a>.</p></div>";
    return;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<div class='content-box'><p>Invalid part ID.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'parts_helper.php';
include_once 'makes_helper.php';

parts_ensure_table($CarpartsConnection);

$part = parts_get($CarpartsConnection, $id, true);

if (!$part) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p>Part not found.</p></div>";
    return;
}

$is_seller = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$part['seller_id'];
if (!$is_seller && empty($_SESSION['isadmin'])) {
    mysqli_close($CarpartsConnection);
    echo "<div class='content-box'><p style='color:red;'>Access denied. You can only edit your own listings.</p></div>";
    return;
}

$makes       = makes_list($CarpartsConnection);
$models_json = makes_all_models_json($CarpartsConnection);
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Edit part: <?= htmlspecialchars(parts_ref($id)) ?></h3>
<br>

<form method="post" action="index.php?navigate=processeditpart">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="id" value="<?= $id ?>" />

    <label><strong>Title / part name: *</strong></label><br>
    <input type="text" name="title" maxlength="255" required style="width:380px;padding:5px;"
           value="<?= htmlspecialchars($part['title']) ?>" /><br><br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Car make: *</strong></label><br>
            <select name="make_id" id="make_id" required onchange="updateModels()" style="padding:5px;min-width:160px;">
                <option value="">-- Select make --</option>
                <?php foreach ($makes as $mid => $mname): ?>
                <option value="<?= $mid ?>" <?= ($part['make_id'] == $mid) ? 'selected' : '' ?>><?= htmlspecialchars($mname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><strong>Model:</strong></label><br>
            <select name="model_id" id="model_id" style="padding:5px;min-width:160px;">
                <option value="">-- Select model (optional) --</option>
            </select>
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Year from: *</strong></label><br>
            <input type="number" name="year_from" required min="1940" max="<?= date('Y') ?>"
                   style="width:100px;padding:5px;" value="<?= (int)$part['year_from'] ?>" />
        </div>
        <div>
            <label><strong>Year to:</strong></label><br>
            <input type="number" name="year_to" min="1940" max="<?= date('Y') + 1 ?>"
                   style="width:100px;padding:5px;"
                   value="<?= $part['year_to'] ? (int)$part['year_to'] : '' ?>" />
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Price (€): *</strong></label><br>
            <input type="number" name="price" required min="0" step="0.01"
                   style="width:120px;padding:5px;"
                   value="<?= number_format((float)$part['price'], 2, '.', '') ?>" />
        </div>
        <div>
            <label><strong>Condition:</strong></label><br>
            <select name="condition" required style="padding:5px;">
                <?php foreach (['Rubbish','Poor','Fair','Good','Very Good','Mint'] as $ci => $cl): ?>
                <option value="<?= $ci ?>" <?= ($part['condition'] == $ci) ? 'selected' : '' ?>><?= $ci ?> — <?= htmlspecialchars($cl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><strong>Quantity:</strong></label><br>
            <input type="number" name="stock" min="1" style="width:80px;padding:5px;"
                   value="<?= (int)$part['stock'] ?>" />
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>OEM part number:</strong></label><br>
            <input type="text" name="oem_number" maxlength="100" style="width:180px;padding:5px;"
                   value="<?= htmlspecialchars($part['oem_number'] ?? '') ?>" />
        </div>
        <div>
            <label><strong>OEM replacement number:</strong></label><br>
            <input type="text" name="replacement_number" maxlength="100" style="width:180px;padding:5px;"
                   value="<?= htmlspecialchars($part['replacement_number'] ?? '') ?>" />
        </div>
    </div>
    <br>

    <label><strong>Description:</strong></label><br>
    <textarea name="description" rows="5" style="width:100%;max-width:480px;padding:5px;"><?= htmlspecialchars($part['description'] ?? '') ?></textarea><br><br>

    <label>
        <input type="checkbox" name="for_sale" value="1" <?= ($part['for_sale']) ? 'checked' : '' ?> />
        <strong>List this part for sale</strong> <small style="color:#666;">(uncheck for display-only items)</small>
    </label><br><br>

    <label>
        <input type="checkbox" name="visible" value="1" <?= ($part['visible']) ? 'checked' : '' ?> />
        <strong>Visible to others</strong> <small style="color:#666;">(uncheck to keep it in your own collection)</small>
    </label><br><br>

    <label>
        <input type="checkbox" name="visible_private" value="1" <?= ($part['visible_private']) ? 'checked' : '' ?> />
        <strong>Private listing</strong> <small style="color:#666;">(incrowd only)</small>
    </label><br><br>

    <input type="submit" value="Save changes" class="btn" style="padding:9px 24px;" />
    <a href="index.php?navigate=viewpart&id=<?= $id ?>" style="padding:9px 18px;margin-left:10px;">Cancel</a>
</form>
</div>

<script>
var _models = <?= $models_json ?>;
var _prevModel = <?= (int)($part['model_id'] ?? 0) ?>;
function updateModels() {
    var makeId = parseInt(document.getElementById('make_id').value);
    var sel = document.getElementById('model_id');
    sel.innerHTML = '<option value="">-- Select model (optional) --</option>';
    if (makeId && _models[makeId]) {
        _models[makeId].forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.selected = (m.id === _prevModel);
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            sel.appendChild(o);
        });
    }
}
updateModels();
</script>
