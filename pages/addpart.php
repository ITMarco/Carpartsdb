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
<div id="drop-zone"
     style="border:2px dashed var(--color-content-border);border-radius:8px;padding:24px 20px;
            text-align:center;background:var(--color-surface);transition:background .15s;">
    <div style="font-size:28px;margin-bottom:8px;">&#128247;</div>
    <div style="font-size:13px;color:var(--color-accent);margin-bottom:10px;">
        Drop photos here, or choose:
    </div>
    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
        <button type="button" onclick="document.getElementById('photo-gallery').click()"
                style="padding:6px 14px;font-size:13px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
            &#128193; Gallery
        </button>
        <button type="button" onclick="document.getElementById('photo-camera').click()"
                style="padding:6px 14px;font-size:13px;border-radius:3px;cursor:pointer;border:1px solid var(--color-content-border);">
            &#128247; Camera
        </button>
    </div>
    <input type="file" id="photo-gallery" name="photo" accept="image/*" multiple style="display:none;" />
    <input type="file" id="photo-camera"  name="photo" accept="image/*" capture="environment" style="display:none;" />
    <div style="font-size:11px;color:#aaa;margin-top:10px;">JPG, PNG, GIF, WebP &mdash; max 1.5 MB each</div>
</div>

<div id="upload-progress" style="margin-top:10px;font-size:12px;"></div>
<div id="photo-grid-wrap" style="margin-top:12px;<?= empty($photos) ? 'display:none;' : '' ?>">
    <p style="font-weight:bold;color:#2a7a2a;margin:0 0 6px;">&#10003; Successfully uploaded pictures:</p>
    <div id="photo-grid" style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($photos as $ph): ?>
        <img src="<?= htmlspecialchars($ph) ?>" alt=""
             style="width:100px;height:75px;object-fit:cover;border-radius:4px;
                    border:1px solid var(--color-content-border);" />
        <?php endforeach; ?>
    </div>
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
    var gallery = document.getElementById('photo-gallery');
    var camera  = document.getElementById('photo-camera');
    var prog    = document.getElementById('upload-progress');
    var wrap    = document.getElementById('photo-grid-wrap');
    var grid    = document.getElementById('photo-grid');

    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.style.background='var(--color-input-bg)'; });
    zone.addEventListener('dragleave',function()  { zone.style.background='var(--color-surface)'; });
    zone.addEventListener('drop',    function(e)  { e.preventDefault(); zone.style.background='var(--color-surface)'; uploadFiles(e.dataTransfer.files); });
    gallery.addEventListener('change', function() { uploadFiles(this.files); this.value=''; });
    camera.addEventListener('change',  function() { uploadFiles(this.files); this.value=''; });

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
                wrap.style.display = '';
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

include_once 'users_helper.php';
users_ensure_table($CarpartsConnection);

$makes       = makes_list($CarpartsConnection);
$models_json = makes_all_models_json($CarpartsConnection);

// Top 5 make/model combos this user has used before
$top_models = users_get_top_models($CarpartsConnection, (int)$_SESSION['user_id'], 5);

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

    <?php if (!empty($top_models)): ?>
    <div style="margin-bottom:14px;">
        <label><strong>Quick select:</strong> <small style="color:#888;font-weight:normal;">your recently used make/model</small></label><br>
        <select onchange="if(this.value){var p=JSON.parse(this.value);quickSelect(p[0],p[1]);this.selectedIndex=0;}"
                style="padding:5px;min-width:260px;">
            <option value="">-- Select recent make/model --</option>
            <?php foreach ($top_models as $tm):
                $label = $tm['make_name'];
                if ($tm['model_name']) $label .= ' — ' . $tm['model_name'];
                $val = json_encode([(int)$tm['make_id'], (int)$tm['model_id']]);
            ?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

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
            <label><strong>Price:</strong></label><br>
            <select name="price_type" id="price_type_sel" onchange="togglePriceInput()" style="padding:5px;">
                <option value="fixed">Fixed price</option>
                <option value="request">On request</option>
                <option value="bid">Make a bid</option>
            </select>
            <span id="price_input_wrap" style="margin-left:8px;">
                <input type="number" name="price" id="price_inp" min="0" step="0.01"
                       style="width:110px;padding:5px;" placeholder="0.00" />
                <small style="color:#888;">€</small>
            </span>
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
        <input type="checkbox" name="for_sale" id="for_sale_cb" value="1" />
        <strong>List this part for sale</strong> <small style="color:#666;">(uncheck for display-only items)</small>
    </label><br><br>

    <?php $can_set_private = !empty($_SESSION['isadmin']) || !empty($_SESSION['is_member']); ?>
    <label><strong>Visibility:</strong></label><br>
    <select name="visibility" id="visibility_sel" style="padding:5px;min-width:220px;">
        <?php if ($can_set_private): ?>
        <option value="private" selected>Private (incrowd members only)</option>
        <option value="public">Visible to all</option>
        <option value="hidden">My collection only</option>
        <?php else: ?>
        <option value="hidden" selected>My collection only</option>
        <option value="public">Visible to all</option>
        <?php endif; ?>
    </select>
    <span id="visibility_warn" style="display:none;margin-left:10px;color:#c00;font-weight:bold;">
        &#9888; A part listed for sale cannot be &ldquo;My collection only&rdquo; &mdash; please change the visibility.
    </span><br><br>

    <input type="submit" value="Save part &amp; add photos" class="btn" style="padding:9px 24px;font-size:15px;" />
    <button type="button" onclick="location.href='index.php?navigate=browse'"
            style="padding:9px 18px;margin-left:10px;">Cancel</button>
</form>
</div>

<script>
function togglePriceInput() {
    var sel  = document.getElementById('price_type_sel');
    var wrap = document.getElementById('price_input_wrap');
    wrap.style.display = sel.value === 'fixed' ? 'inline' : 'none';
}
togglePriceInput();

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

// ── Quick-select from recent history ──────────────────────────────────────────
function quickSelect(makeId, modelId) {
    var makeEl = document.getElementById('make_id');
    makeEl.value = makeId;
    saveMakePref();
    updateModels('make_id', 'model_id', 'year_from', 'year_to');
    if (modelId > 0) {
        var modelEl = document.getElementById('model_id');
        for (var i = 0; i < modelEl.options.length; i++) {
            if (parseInt(modelEl.options[i].value) === modelId) {
                modelEl.selectedIndex = i;
                saveModelPref();
                fillYears('model_id', 'year_from', 'year_to');
                break;
            }
        }
    }
}

function checkVisibilitySale() {
    var forSale = document.getElementById('for_sale_cb').checked;
    var vis     = document.getElementById('visibility_sel');
    var warn    = document.getElementById('visibility_warn');
    var invalid = forSale && vis.value === 'hidden';
    vis.style.outline    = invalid ? '3px solid #c00' : '';
    vis.style.background = invalid ? '#fff0f0'        : '';
    warn.style.display   = invalid ? 'inline'         : 'none';
}

document.getElementById('for_sale_cb').addEventListener('change', checkVisibilitySale);
document.getElementById('visibility_sel').addEventListener('change', checkVisibilitySale);

document.getElementById('addpart-form').addEventListener('submit', function(e) {
    if (document.getElementById('for_sale_cb').checked &&
        document.getElementById('visibility_sel').value === 'hidden') {
        checkVisibilitySale();
        e.preventDefault();
    }
});

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
