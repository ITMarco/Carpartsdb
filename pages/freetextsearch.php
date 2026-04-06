<?php
$search_term = ($_SERVER['REQUEST_METHOD'] === 'POST') ? trim($_POST['naam'] ?? '') : '';
$rows = [];

if ($search_term !== '' && strlen($search_term) > 1) {
    if (!defined('SNLDBCARPARTS_ACCESS')) define('SNLDBCARPARTS_ACCESS', 1);
    include_once 'connection.php';
    include_once 'stats_helper.php';
    stats_session_check($SNLDBConnection);

    $is_admin = !empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1;

    if ($is_admin) {
        $q    = $search_term;
        $stmt = $SNLDBConnection->prepare(
            "SELECT *,
                    MATCH(License, Owner_display, VIN_Colorcode, Mods, History)
                    AGAINST (? IN BOOLEAN MODE) AS relevance
             FROM SNLDB
             WHERE MATCH(License, Owner_display, VIN_Colorcode, Mods, History)
                   AGAINST (? IN BOOLEAN MODE)
             ORDER BY relevance DESC"
        );
        if ($stmt) { $stmt->bind_param('ss', $q, $q); }
    } else {
        $p    = '%' . $search_term . '%';
        $stmt = $SNLDBConnection->prepare(
            "SELECT *, 0 AS relevance FROM SNLDB
             WHERE License LIKE ? OR Choise_Model LIKE ? OR Choise_Engine LIKE ?
                OR VIN_Colorcode LIKE ? OR Mods LIKE ?
             ORDER BY License"
        );
        if ($stmt) { $stmt->bind_param('sssss', $p, $p, $p, $p, $p); }
    }

    if ($stmt) {
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $SNLDBConnection->query("UPDATE `16915snldb`.`HITS` SET `searches` = searches + 1 WHERE CONVERT(`HITS`.`key` USING utf8) = '1'");
    stats_day($SNLDBConnection, 'searches');
}

$max_relevance = !empty($rows) ? (float)$rows[0]['relevance'] : 1.0;
if ($max_relevance <= 0) $max_relevance = 1.0;

function fts_status_badge(string $status): string {
    $map = [
        'Running'         => ['#2a8a2a', 'Rijdend'],
        'Garage'          => ['#5588bb', 'Garage'],
        'Forsale'         => ['#c8a020', 'Te koop'],
        'Wrecked'         => ['#c04040', 'Wrak'],
        'Not Available'   => ['#c04040', 'N/A'],
        'No Road License' => ['#888',    'Geen kenteken'],
    ];
    [$color, $label] = $map[$status] ?? ['#888', htmlspecialchars($status)];
    return "<span style='display:inline-block;padding:2px 7px;border-radius:3px;
                         background:{$color};color:#fff;font-size:10px;font-weight:bold;'>"
         . $label . "</span>";
}
?>
<div class="content-box">
<h3>Zoek op trefwoord</h3>
<form method="post" action="index.php?navigate=freetextsearch">
    <div style="display:flex;gap:8px;align-items:center;max-width:480px;margin-top:10px;">
        <input type="text" name="naam" autofocus
               value="<?= htmlspecialchars($search_term) ?>"
               placeholder="bijv. 2JZ, Turbo, rood, Amsterdam…"
               style="flex:1;padding:7px 10px;font-size:13px;
                      border:1px solid var(--color-content-border);border-radius:4px;
                      background:var(--color-input-bg);color:var(--color-text);" />
        <input type="submit" value="Zoeken" class="btn" />
    </div>
    <div style="font-size:11px;color:var(--color-muted);margin-top:6px;">
        Zoekt in kenteken, eigenaar, kleur, modificaties en geschiedenis.
        Gebruik <code>+woord</code> om te vereisen, <code>-woord</code> om uit te sluiten.
    </div>
</form>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $search_term !== ''): ?>

<?php if (empty($rows)): ?>
<div class="content-box">
    <p>Geen supras gevonden voor <strong><?= htmlspecialchars($search_term) ?></strong>.</p>
    <p><a href="index.php?navigate=contribute" style="color:var(--color-link);">Voeg een supra toe aan de database</a></p>
</div>

<?php else: ?>
<div style="font-size:11px;color:var(--color-muted);padding:6px 0 10px;">
    <?= count($rows) ?> resultaat<?= count($rows) !== 1 ? 'en' : '' ?> voor
    <strong><?= htmlspecialchars($search_term) ?></strong> — gesorteerd op relevantie
</div>

<?php foreach ($rows as $row):
    $lic   = $row['License'];
    $strip = strtoupper(preg_replace('/\s*/m', '', $lic));
    $pct   = min(100, round(($row['relevance'] / $max_relevance) * 100));
?>
<div class="content-box">

    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
        <a href="index.php?navigate=<?= urlencode($lic) ?>"
           style="font-size:19px;font-weight:bold;color:var(--color-link);text-decoration:none;">
            <?= htmlspecialchars($lic) ?>
        </a>
        <?= fts_status_badge($row['Choise_Status']) ?>
        <span style="font-size:12px;color:var(--color-accent);font-weight:bold;">
            <?= htmlspecialchars($row['Choise_Model']) ?>
        </span>
        <span style="font-size:13px;color:var(--color-muted);flex:1;">
            <?php
              $fts_owner_visible = (!empty($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1)
                  || (!empty($_SESSION['user_license']) && strtoupper($_SESSION['user_license']) === strtoupper($lic))
                  || !empty($row['Owner_show']);
              echo $fts_owner_visible && $row['Owner_display'] !== ''
                  ? htmlspecialchars($row['Owner_display'])
                  : '<em style="color:#888;font-size:11px;">Verborgen of niet opgegeven.</em>';
            ?>
        </span>
        <!-- relevance bar -->
        <span title="Relevantiescore: <?= $pct ?>%"
              style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--color-muted);">
            <span style="display:inline-block;width:60px;height:5px;border-radius:3px;
                         background:var(--color-nav-border);overflow:hidden;">
                <span style="display:block;width:<?= $pct ?>%;height:100%;
                             background:var(--color-accent);border-radius:3px;"></span>
            </span>
            <?= $pct ?>%
        </span>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:5px 18px;font-size:12px;color:var(--color-muted);margin-bottom:10px;">
        <?php if ($row['Choise_Engine']): ?>
        <span><strong>Motor:</strong> <?= htmlspecialchars($row['Choise_Engine']) ?></span>
        <?php endif; ?>
        <?php if ($row['VIN_Colorcode']): ?>
        <span><strong>Kleur:</strong> <?= htmlspecialchars($row['VIN_Colorcode']) ?></span>
        <?php endif; ?>
        <?php if ($row['Milage']): ?>
        <span><strong>KM:</strong> <?= htmlspecialchars($row['Milage']) ?></span>
        <?php endif; ?>
        <?php if ($row['Build_date']): ?>
        <span><strong>Bouwjaar:</strong> <?= htmlspecialchars($row['Build_date']) ?></span>
        <?php endif; ?>
    </div>

    <a href="index.php?navigate=<?= urlencode($lic) ?>" class="btn"
       style="font-size:12px;padding:5px 14px;">Bekijk auto</a>

</div>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>
