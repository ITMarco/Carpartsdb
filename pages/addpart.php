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

// ── Step 2: Photo upload after save ───────────────────────────────────────────
$new_id = isset($_GET['new']) ? intval($_GET['new']) : 0;
if ($new_id > 0) {
    $new_part = parts_get($CarpartsConnection, $new_id, true);
    $is_owner = $new_part && isset($_SESSION['user_id'])
                && (int)$_SESSION['user_id'] === (int)$new_part['seller_id'];
    if (!$is_owner && empty($_SESSION['isadmin'])) {
        $new_id = 0; $new_part = null;
    }
}

if ($new_id > 0 && $new_part) {
    $photos = parts_photos($new_id);
    mysqli_close($CarpartsConnection);
    $ref = htmlspecialchars(parts_ref($new_id));
    $csrf = htmlspecialchars($_SESSION['csrf_token']);
?>
<div class="content-box">
<h3>Part saved &mdash; <?= $ref ?></h3>
<p style="color:#2a7a2a;font-weight:bold;">&#10003; <strong><?= htmlspecialchars($new_part['title']) ?></strong> has been listed.</p>

<h4 style="margin-top:18px;">Add photos</h4>
<p style="font-size:12px;color:#666;">Drag &amp; drop images here, or tap/click to select. JPG, PNG, GIF, WebP &mdash; max 1.5 MB each.</p>

<div id="drop-zone"
     style="border:2px dashed var(--color-content-border);border-radius:8px;padding:30px 20px;
            text-align:center;cursor:pointer;background:var(--color-surface);transition:background .15s;
            min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
    <div style="font-size:32px;">📷</div>
    <div style="font-size:14px;color:var(--color-accent);">Drop images here or <span style="text-decoration:underline;">click to choose</span></div>
    <input type="file" id="photo-input" name="photo" accept="image/*" multiple
           style="display:none;" />
</div>

<div id="upload-progress" style="margin-top:10px;font-size:12px;"></div>
<div id="photo-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
    <?php foreach ($photos as $ph): ?>
    <img src="<?= htmlspecialchars($ph) ?>" alt=""
         style="width:100px;height:75px;object-fit:cover;border-radius:4px;
                border:1px solid var(--color-content-border);" />
    <?php endforeach; ?>
</div>

<p style="margin-top:16px;">
    <a href="index.php?navigate=viewpart&id=<?= $new_id ?>" class="btn" style="padding:8px 18px;">View listing</a>
    <a href="index.php?navigate=addpart" style="padding:8px 18px;margin-left:8px;">+ Add another part</a>
</p>
</div>

<script>
(function() {
    var partId  = <?= $new_id ?>;
    var csrf    = '<?= $csrf ?>';
    var zone    = document.getElementById('drop-zone');
    var input   = document.getElementById('photo-input');
    var prog    = document.getElementById('upload-progress');
    var grid    = document.getElementById('photo-grid');

    zone.addEventListener('click',   function() { input.click(); });
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.style.background='var(--color-input-bg)'; });
    zone.addEventListener('dragleave',function()  { zone.style.background='var(--color-surface)'; });
    zone.addEventListener('drop',    function(e)  { e.preventDefault(); zone.style.background='var(--color-surface)'; uploadFiles(e.dataTransfer.files); });
    input.addEventListener('change', function()   { uploadFiles(this.files); this.value=''; });

    function uploadFiles(files) {
        Array.from(files).forEach(uploadOne);
    }
    function uploadOne(file) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('id', partId);
        fd.append('photo', file);

        var row = document.createElement('div');
        row.style.cssText = 'font-size:12px;color:#666;margin:2px 0;';
        row.textContent = '↑ ' + file.name + '…';
        prog.appendChild(row);

        fetch('index.php?navigate=uploadpartimage&id=' + partId + '&ajax=1', {
            method: 'POST',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                row.textContent = '✓ ' + file.name;
                row.style.color = '#2a7a2a';
                var img = document.createElement('img');
                img.src = d.path + '?t=' + Date.now();
                img.style.cssText = 'width:100px;height:75px;object-fit:cover;border-radius:4px;border:1px solid var(--color-content-border);';
                grid.appendChild(img);
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
</script>
<?php
    return; // Don't show the add-part form below
}

$makes       = makes_list($CarpartsConnection);
$models_json = makes_all_models_json($CarpartsConnection);
mysqli_close($CarpartsConnection);

// Last-used make/model from cookie
$pref_make  = isset($_COOKIE['cpdb_last_make'])  ? intval($_COOKIE['cpdb_last_make'])  : 0;
$pref_model = isset($_COOKIE['cpdb_last_model']) ? intval($_COOKIE['cpdb_last_model']) : 0;
?>
<div class="content-box">
<h3>Add a part to your collection</h3>
<br>

<form method="post" action="index.php?navigate=processaddpart" id="addpart-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
    <input type="hidden" name="compat_data" id="compat_data" value="[]" />

    <label><strong>Title / part name: *</strong></label><br>
    <input type="text" name="title" maxlength="255" required style="width:380px;padding:5px;"
           placeholder="e.g. Front bumper, alternator, door mirror…" /><br><br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div>
            <label><strong>Car make: *</strong></label><br>
            <select name="make_id" id="make_id" required onchange="updateModels('make_id','model_id','year_from','year_to');saveMakePref()" style="padding:5px;min-width:160px;">
                <option value="">-- Select make --</option>
                <?php foreach ($makes as $mid => $mname): ?>
                <option value="<?= $mid ?>" <?= ($pref_make === $mid) ? 'selected' : '' ?>><?= htmlspecialchars($mname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><strong>Model:</strong></label><br>
            <select name="model_id" id="model_id" style="padding:5px;min-width:160px;"
                    onchange="fillYears('model_id','year_from','year_to');saveModelPref()">
                <option value="">-- Select model (optional) --</option>
            </select>
        </div>
    </div>
    <br>

    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
        <div>
            <label><strong>Year from:</strong> <small style="color:#888;font-weight:normal;">auto-filled from model</small></label><br>
            <input type="number" name="year_from" id="year_from" min="1900" max="2099"
                   style="width:100px;padding:5px;" placeholder="e.g. 1986" />
        </div>
        <div>
            <label><strong>Year to:</strong></label><br>
            <input type="number" name="year_to" id="year_to" min="1900" max="2099"
                   style="width:100px;padding:5px;" placeholder="leave blank = ongoing" />
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

    <!-- Also fits -->
    <label><strong>Also applicable to:</strong> <small style="color:#666;">other makes/models this part fits</small></label><br>
    <div id="compat-rows" style="margin:8px 0;"></div>
    <button type="button" onclick="addCompatRow()"
            style="padding:5px 14px;font-size:12px;margin-bottom:12px;">+ Add vehicle</button>
    <br>

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

    <input type="submit" value="Save part &amp; add photos" class="btn" style="padding:9px 24px;font-size:15px;" />
    <button type="button" onclick="location.href='index.php?navigate=browse'"
            style="padding:9px 18px;margin-left:10px;">Cancel</button>
</form>
</div>

<script>
var _models    = <?= $models_json ?>;
var _prefMake  = <?= $pref_make ?>;
var _prefModel = <?= $pref_model ?>;

// ── Cookie helpers ─────────────────────────────────────────────────────────────
function saveMakePref() {
    var v = parseInt(document.getElementById('make_id').value) || 0;
    document.cookie = 'cpdb_last_make=' + v + '; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
    // Reset model pref when make changes
    document.cookie = 'cpdb_last_model=0; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
}
function saveModelPref() {
    var v = parseInt(document.getElementById('model_id').value) || 0;
    document.cookie = 'cpdb_last_model=' + v + '; path=/; max-age=' + (365*24*3600) + '; SameSite=Lax';
}

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
            o.textContent = m.name + (m.yf ? ' (' + m.yf + (m.yt ? '\u2013' + m.yt : '\u2013') + ')' : '');
            sel.appendChild(o);
        });
    }
    // Clear year fields when make changes
    if (yfId) document.getElementById(yfId).value = '';
    if (ytId) document.getElementById(ytId).value = '';
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
var _compatData = [];

function addCompatRow(makeVal, modelVal) {
    var idx = _compatIdx++;
    var div = document.createElement('div');
    div.id = 'compat-row-' + idx;
    div.style.cssText = 'display:flex;gap:10px;align-items:center;margin:4px 0;flex-wrap:wrap;';

    // Make select
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

    // Model select
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
    removeBtn.addEventListener('click', function() {
        div.remove();
        refreshCompatData();
    });

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

document.getElementById('addpart-form').addEventListener('submit', refreshCompatData);

// Pre-populate model dropdown from cookie preference on page load
if (_prefMake > 0) {
    updateModels('make_id', 'model_id', 'year_from', 'year_to');
    if (_prefModel > 0) {
        var ms = document.getElementById('model_id');
        for (var i = 0; i < ms.options.length; i++) {
            if (parseInt(ms.options[i].value) === _prefModel) {
                ms.selectedIndex = i;
                fillYears('model_id', 'year_from', 'year_to');
                break;
            }
        }
    }
}
</script>
