<?php
if (empty($_SESSION['authenticated'])) {
    echo "<div class='content-box'><p>Please <a href='index.php?navigate=secureadmin'>log in</a> to list a part.</p></div>";
    return;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'connection.php';
include_once 'makes_helper.php';
include_once 'parts_helper.php';

makes_ensure_tables($CarpartsConnection);
parts_ensure_table($CarpartsConnection);

$makes       = makes_list($CarpartsConnection);
$models_json = makes_all_models_json($CarpartsConnection);
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Add a part to your collection</h3>
<br>

<form method="post" action="index.php?navigate=processaddpart">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />

    <label><strong>Title / part name: *</strong></label><br>
    <input type="text" name="title" maxlength="255" required style="width:380px;padding:5px;"
           placeholder="e.g. Front bumper, alternator, door mirror…" /><br><br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Car make: *</strong></label><br>
            <select name="make_id" id="make_id" required onchange="updateModels()" style="padding:5px;min-width:160px;">
                <option value="">-- Select make --</option>
                <?php foreach ($makes as $mid => $mname): ?>
                <option value="<?= $mid ?>"><?= htmlspecialchars($mname) ?></option>
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
            <label><strong>Applicable year from: *</strong></label><br>
            <input type="number" name="year_from" required min="1940" max="<?= date('Y') ?>"
                   style="width:100px;padding:5px;" placeholder="e.g. 1986" />
        </div>
        <div>
            <label><strong>Applicable year to:</strong></label><br>
            <input type="number" name="year_to" min="1940" max="<?= date('Y') + 1 ?>"
                   style="width:100px;padding:5px;" placeholder="leave blank = same year" />
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Price (€): *</strong></label><br>
            <input type="number" name="price" required min="0" step="0.01"
                   style="width:120px;padding:5px;" placeholder="0.00" />
        </div>
        <div>
            <label><strong>Condition: *</strong> <small style="color:#666;">(0 = rubbish, 5 = mint)</small></label><br>
            <select name="condition" required style="padding:5px;">
                <option value="3" selected>3 — Good</option>
                <option value="0">0 — Rubbish</option>
                <option value="1">1 — Poor</option>
                <option value="2">2 — Fair</option>
                <option value="4">4 — Very Good</option>
                <option value="5">5 — Mint</option>
            </select>
        </div>
        <div>
            <label><strong>Quantity available:</strong></label><br>
            <input type="number" name="stock" min="1" value="1" style="width:80px;padding:5px;" />
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>OEM part number:</strong></label><br>
            <input type="text" name="oem_number" maxlength="100" style="width:180px;padding:5px;" />
        </div>
        <div>
            <label><strong>OEM replacement number:</strong></label><br>
            <input type="text" name="replacement_number" maxlength="100" style="width:180px;padding:5px;" />
        </div>
    </div>
    <br>

    <label><strong>Description:</strong></label><br>
    <textarea name="description" rows="5" style="width:100%;max-width:480px;padding:5px;"
              placeholder="Condition details, fitment notes, removal notes…"></textarea><br><br>

    <label>
        <input type="checkbox" name="for_sale" value="1" checked />
        <strong>List this part for sale</strong> <small style="color:#666;">(uncheck for display-only items)</small>
    </label><br><br>

    <label>
        <input type="checkbox" name="visible" value="1" checked />
        <strong>Visible to others</strong> <small style="color:#666;">(uncheck to keep it in your own collection)</small>
    </label><br><br>

    <?php if (!empty($_SESSION['isadmin']) || !empty($_SESSION['is_member'])): ?>
    <label>
        <input type="checkbox" name="visible_private" value="1" />
        <strong>Private listing</strong> <small style="color:#666;">(only visible to incrowd members)</small>
    </label><br><br>
    <?php endif; ?>

    <input type="submit" value="Save part" class="btn" style="padding:9px 24px;font-size:15px;" />
    <button type="button" onclick="location.href='index.php?navigate=browse'"
            style="padding:9px 18px;margin-left:10px;">Cancel</button>
</form>
</div>

<script>
var _models = <?= $models_json ?>;
function updateModels() {
    var makeId = parseInt(document.getElementById('make_id').value);
    var sel = document.getElementById('model_id');
    sel.innerHTML = '<option value="">-- Select model (optional) --</option>';
    if (makeId && _models[makeId]) {
        _models[makeId].forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            sel.appendChild(o);
        });
    }
}
</script>
