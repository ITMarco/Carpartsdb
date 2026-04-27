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

$makes        = makes_list($CarpartsConnection);
$models_json  = makes_all_models_json($CarpartsConnection);
$compat_rows  = parts_compat_get($CarpartsConnection, $id);
$compat_json  = json_encode(array_map(fn($r) => ['make_id' => (int)$r['make_id'], 'model_id' => $r['model_id'] ? (int)$r['model_id'] : null], $compat_rows), JSON_UNESCAPED_UNICODE);
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Edit part: <?= htmlspecialchars(parts_ref($id)) ?></h3>
<br>

<form method="post" action="index.php?navigate=processeditpart" id="editpart-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="id" value="<?= $id ?>" />
    <input type="hidden" name="compat_data" id="compat_data" value="<?= htmlspecialchars($compat_json) ?>" />

    <label><strong>Title / part name: *</strong></label><br>
    <input type="text" name="title" maxlength="255" required style="width:380px;padding:5px;"
           value="<?= htmlspecialchars($part['title']) ?>" /><br><br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Car make: *</strong></label><br>
            <select name="make_id" id="make_id" required onchange="updateModels('make_id','model_id',null,null)" style="padding:5px;min-width:160px;">
                <option value="">-- Select make --</option>
                <?php foreach ($makes as $mid => $mname): ?>
                <option value="<?= $mid ?>" <?= ($part['make_id'] == $mid) ? 'selected' : '' ?>><?= htmlspecialchars($mname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><strong>Model:</strong></label><br>
            <select name="model_id" id="model_id" style="padding:5px;min-width:160px;"
                    onchange="fillYears('model_id','year_from','year_to')">
                <option value="">-- Select model (optional) --</option>
            </select>
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
        <div>
            <label><strong>Year from:</strong> <small style="color:#888;font-weight:normal;">auto-filled from model</small></label><br>
            <input type="number" name="year_from" id="year_from" min="1900" max="2099"
                   style="width:100px;padding:5px;" value="<?= (int)$part['year_from'] ?>" />
        </div>
        <div>
            <label><strong>Year to:</strong></label><br>
            <input type="number" name="year_to" id="year_to" min="1900" max="2099"
                   style="width:100px;padding:5px;"
                   value="<?= $part['year_to'] ? (int)$part['year_to'] : '' ?>" />
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Price (€):</strong> <small style="color:#888;font-weight:normal;">leave blank = price on request</small></label><br>
            <input type="number" name="price" min="0" step="0.01"
                   style="width:120px;padding:5px;" placeholder="on request"
                   value="<?= $part['price'] !== null ? number_format((float)$part['price'], 2, '.', '') : '' ?>" />
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

    <!-- Also fits -->
    <label><strong>Also applicable to:</strong> <small style="color:#666;">other makes/models this part fits</small></label><br>
    <div id="compat-rows" style="margin:8px 0;"></div>
    <button type="button" onclick="addCompatRow()"
            style="padding:5px 14px;font-size:12px;margin-bottom:12px;">+ Add vehicle</button>
    <br>

    <label>
        <input type="checkbox" name="for_sale" value="1" <?= ($part['for_sale']) ? 'checked' : '' ?> />
        <strong>List this part for sale</strong> <small style="color:#666;">(uncheck for display-only items)</small>
    </label><br><br>

    <label>
        <input type="checkbox" name="visible" value="1" <?= ($part['visible']) ? 'checked' : '' ?> />
        <strong>Visible to others</strong> <small style="color:#666;">(uncheck to keep it in your own collection)</small>
    </label><br><br>

    <?php if (!empty($_SESSION['isadmin']) || !empty($_SESSION['is_member'])): ?>
    <label>
        <input type="checkbox" name="visible_private" value="1" <?= ($part['visible_private']) ? 'checked' : '' ?> />
        <strong>Private listing</strong> <small style="color:#666;">(only visible to incrowd members)</small>
    </label><br><br>
    <?php endif; ?>

    <input type="submit" value="Save changes" class="btn" style="padding:9px 24px;" />
    <a href="index.php?navigate=viewpart&id=<?= $id ?>" style="padding:9px 18px;margin-left:10px;">Cancel</a>
</form>

<div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--color-content-border);display:flex;gap:10px;flex-wrap:wrap;">
    <?php $is_sold = !empty($part['is_sold']); ?>
    <?php if (!$is_sold): ?>
    <form method="post" action="index.php?navigate=markpartsold" style="display:inline;"
          onsubmit="return confirm('Mark this part as sold? It will be hidden from public listings.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <input type="hidden" name="id" value="<?= $id ?>" />
        <input type="submit" value="Mark as sold"
               style="padding:7px 16px;background:#c87020;color:#fff;border:none;cursor:pointer;border-radius:3px;font-size:13px;" />
    </form>
    <?php else: ?>
    <form method="post" action="index.php?navigate=markpartsold" style="display:inline;"
          onsubmit="return confirm('Re-list this part as available?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
        <input type="hidden" name="id" value="<?= $id ?>" />
        <input type="hidden" name="undo" value="1" />
        <input type="submit" value="Re-list (undo sold)"
               style="padding:7px 16px;background:#5588bb;color:#fff;border:none;cursor:pointer;border-radius:3px;font-size:13px;" />
    </form>
    <?php endif; ?>
    <a href="index.php?navigate=deletepart&id=<?= $id ?>"
       style="padding:7px 16px;background:#dc3545;color:#fff;text-decoration:none;border-radius:3px;font-size:13px;"
       onclick="return confirm('Permanently delete this part listing?');">Delete</a>
</div>
</div>

<script>
var _models    = <?= $models_json ?>;
var _prevModel = <?= (int)($part['model_id'] ?? 0) ?>;
var _existingCompat = <?= $compat_json ?>;

function updateModels(makeSelId, modelSelId, yfId, ytId) {
    var makeId = parseInt(document.getElementById(makeSelId).value);
    var sel = document.getElementById(modelSelId);
    sel.innerHTML = '<option value="">-- Select model (optional) --</option>';
    if (makeId && _models[makeId]) {
        _models[makeId].forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.dataset.yf = m.yf || '';
            o.dataset.yt = m.yt || '';
            o.selected = (m.id === _prevModel);
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            sel.appendChild(o);
        });
    }
}

function fillYears(modelSelId, yfId, ytId) {
    var sel = document.getElementById(modelSelId);
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.dataset.yf) return;
    if (yfId) document.getElementById(yfId).value = opt.dataset.yf;
    if (ytId) document.getElementById(ytId).value = opt.dataset.yt || '';
}

// ── Compat rows ────────────────────────────────────────────────────────────────
var _compatIdx = 0;

function addCompatRow(makeVal, modelVal) {
    var idx = _compatIdx++;
    var div = document.createElement('div');
    div.id = 'compat-row-' + idx;
    div.style.cssText = 'display:flex;gap:10px;align-items:center;margin:4px 0;flex-wrap:wrap;';

    var makeEl = document.createElement('select');
    makeEl.style.cssText = 'padding:4px;min-width:150px;';
    makeEl.innerHTML = '<option value="">-- Make --</option>';
    <?php foreach ($makes as $mid => $mname): ?>
    (function(){
        var o = document.createElement('option');
        o.value = '<?= $mid ?>';
        o.textContent = <?= json_encode($mname) ?>;
        makeEl.appendChild(o);
    })();
    <?php endforeach; ?>
    if (makeVal) makeEl.value = makeVal;

    var modelEl = document.createElement('select');
    modelEl.style.cssText = 'padding:4px;min-width:150px;';
    modelEl.innerHTML = '<option value="">-- Model (optional) --</option>';

    makeEl.addEventListener('change', function() {
        modelEl.innerHTML = '<option value="">-- Model (optional) --</option>';
        var mid = parseInt(makeEl.value);
        if (mid && _models[mid]) {
            _models[mid].forEach(function(m) {
                var o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
                modelEl.appendChild(o);
            });
        }
        refreshCompatData();
    });
    modelEl.addEventListener('change', refreshCompatData);

    if (makeVal && _models[parseInt(makeVal)]) {
        _models[parseInt(makeVal)].forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            modelEl.appendChild(o);
        });
        if (modelVal) modelEl.value = modelVal;
    }

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '✕';
    removeBtn.style.cssText = 'padding:3px 8px;font-size:12px;';
    removeBtn.addEventListener('click', function() { div.remove(); refreshCompatData(); });

    div.appendChild(makeEl);
    div.appendChild(modelEl);
    div.appendChild(removeBtn);
    document.getElementById('compat-rows').appendChild(div);
    makeEl.addEventListener('change', refreshCompatData);
}

function refreshCompatData() {
    var rows = document.querySelectorAll('#compat-rows > div');
    var data = [];
    rows.forEach(function(row) {
        var sels = row.querySelectorAll('select');
        var makeId  = parseInt(sels[0].value) || 0;
        var modelId = parseInt(sels[1].value) || 0;
        if (makeId > 0) data.push({make_id: makeId, model_id: modelId || null});
    });
    document.getElementById('compat_data').value = JSON.stringify(data);
}

document.getElementById('editpart-form').addEventListener('submit', refreshCompatData);

// Pre-populate model dropdown and compat rows
updateModels('make_id', 'model_id', null, null);
_existingCompat.forEach(function(e) { addCompatRow(e.make_id, e.model_id); });
</script>
