<?php
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);

$search_term = ($_SERVER['REQUEST_METHOD'] === 'POST') ? trim($_POST['naam'] ?? '') : '';
$rows = [];

if ($search_term !== '' && strlen($search_term) > 1) {
    include_once 'connection.php';
    include_once 'stats_helper.php';
    stats_session_check($CarpartsConnection);

    $fl   = '%' . $search_term . '%';
    $stmt = $CarpartsConnection->prepare("SELECT * FROM SNLDB WHERE License LIKE ? ORDER BY License");
    if ($stmt) {
        $stmt->bind_param('s', $fl);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $CarpartsConnection->query("UPDATE `16915snldb`.`HITS` SET `searches` = searches + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");
    stats_day($CarpartsConnection, 'searches');
}
?>
<div class="content-box">
<h3>Zoeken op nummerbord</h3>
<form method="post" action="index.php?navigate=search">
    <div style="display:flex;gap:8px;align-items:center;max-width:400px;margin-top:10px;">
        <input type="text" name="naam" autofocus
               value="<?= htmlspecialchars($search_term) ?>"
               placeholder="bijv. 37-HXR of HXR"
               style="flex:1;padding:7px 10px;font-size:13px;
                      border:1px solid var(--color-content-border);border-radius:4px;
                      background:var(--color-input-bg);color:var(--color-text);" />
        <input type="submit" value="Zoeken" class="btn" />
    </div>
</form>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $search_term !== ''): ?>

<?php if (empty($rows)): ?>
<div class="content-box">
    <p>Geen supras gevonden voor <strong><?= htmlspecialchars($search_term) ?></strong>.</p>
</div>

<?php else: ?>

<?php foreach ($rows as $row):
    $lic   = $row['License'];
    $strip = strtoupper(preg_replace('/\s*/m', '', $lic));
    $nav   = urlencode($lic);
?>
<div class="content-box">
    <h3>
        <a href="index.php?navigate=<?= $nav ?>"
           style="color:var(--color-link);text-decoration:none;"><?= htmlspecialchars($lic) ?></a>
        &nbsp;
        <a href="https://ovi.rdw.nl/default.aspx" target="_blank" rel="noopener"
           class="btn" style="font-size:11px;padding:3px 10px;">Check RDW</a>
    </h3>

    <div style="display:flex;flex-wrap:wrap;gap:5px 18px;font-size:12px;
                color:var(--color-muted);margin-bottom:10px;">
        <span><strong>Model:</strong> <?= htmlspecialchars($row['Choise_Model']) ?></span>
        <?php if ($row['Owner_display']): ?>
        <span><strong>Eigenaar:</strong> <?= htmlspecialchars($row['Owner_display']) ?></span>
        <?php endif; ?>
        <?php if ($row['Choise_Engine']): ?>
        <span><strong>Motor:</strong> <?= htmlspecialchars($row['Choise_Engine']) ?></span>
        <?php endif; ?>
        <?php if ($row['VIN_Colorcode']): ?>
        <span><strong>Kleur:</strong> <?= htmlspecialchars($row['VIN_Colorcode']) ?></span>
        <?php endif; ?>
        <span><strong>Status:</strong> <?= htmlspecialchars($row['Choise_Status']) ?></span>
        <?php if ($row['Registration_date']): ?>
        <span><strong>Geregistreerd:</strong> <?= htmlspecialchars($row['Registration_date']) ?></span>
        <?php endif; ?>
        <?php if ($row['Build_date']): ?>
        <span><strong>Bouwjaar:</strong> <?= htmlspecialchars($row['Build_date']) ?></span>
        <?php endif; ?>
    </div>

    <?php if (count($rows) === 1 && !empty(trim($row['Mods']))): ?>
    <div style="margin-bottom:10px;">
        <div style="font-size:10px;font-weight:bold;color:var(--color-accent);
                    text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Mods</div>
        <textarea rows="6" readonly
                  style="width:100%;font-size:12px;border:1px solid var(--color-content-border);
                         border-radius:3px;padding:6px;resize:vertical;
                         background:var(--color-input-bg);color:var(--color-text);box-sizing:border-box;"
        ><?= htmlspecialchars($row['Mods']) ?></textarea>
    </div>
    <?php endif; ?>

    <a href="index.php?navigate=<?= $nav ?>" class="btn"
       style="font-size:12px;padding:5px 14px;">Bekijk auto</a>
</div>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>
