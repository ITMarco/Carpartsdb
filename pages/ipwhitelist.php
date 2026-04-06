<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color:red;'>Geen toegang.</div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', true);
require_once __DIR__ . '/../ip_whitelist_helper.php';
require_once __DIR__ . '/../config.php';

$db  = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ip'])) {
        $ip    = trim($_POST['ip'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            ip_whitelist_add($db, $ip, $label);
            $msg = "IP <strong>" . htmlspecialchars($ip) . "</strong> toegevoegd.";
        } else {
            $msg = "<span style='color:red;'>Ongeldig IP-adres.</span>";
        }
    } elseif (isset($_POST['remove_ip'])) {
        $ip = trim($_POST['ip'] ?? '');
        ip_whitelist_remove($db, $ip);
        $msg = "IP <strong>" . htmlspecialchars($ip) . "</strong> verwijderd.";
    }
}

$my_ip    = $_SERVER['REMOTE_ADDR'];
$entries  = ip_whitelist_get_all($db);
$db->close();
?>

<div class="content-box">
<h3>IP Whitelist beheer</h3>
<p style="font-size:13px;color:#555;">
  Whitelisted IP-adressen kunnen altijd inloggen (geen limiet) en worden <strong>niet</strong> opgeslagen in de statistieken.
</p>

<?php if ($msg): ?>
<div style="background:#eef7ee;border:1px solid #9c9;padding:8px 12px;margin-bottom:12px;border-radius:4px;"><?= $msg ?></div>
<?php endif; ?>

<!-- Add form -->
<form method="post" style="margin-bottom:20px;">
  <table style="font-size:13px;border-collapse:collapse;">
    <tr>
      <td style="padding:4px 8px 4px 0;"><label>IP-adres:</label></td>
      <td style="padding:4px 8px 4px 0;">
        <input type="text" name="ip" value="<?= htmlspecialchars($my_ip) ?>"
               style="width:180px;font-family:monospace;" maxlength="45" />
        <span style="font-size:11px;color:#7a9ab0;">(jouw huidige IP is al ingevuld)</span>
      </td>
    </tr>
    <tr>
      <td style="padding:4px 8px 4px 0;"><label>Label:</label></td>
      <td style="padding:4px 8px 4px 0;">
        <input type="text" name="label" placeholder="bijv. Thuis, Kantoor"
               style="width:180px;" maxlength="100" />
      </td>
    </tr>
    <tr>
      <td></td>
      <td style="padding:6px 0 0;">
        <input type="submit" name="add_ip" value="IP toevoegen" />
      </td>
    </tr>
  </table>
</form>

<!-- Current whitelist -->
<?php if (empty($entries)): ?>
<p style="color:#888;font-size:13px;">Nog geen IP-adressen in de whitelist.</p>
<?php else: ?>
<table style="border-collapse:collapse;font-size:13px;width:100%;max-width:600px;">
<tr style="background:#dde8f0;">
  <th style="padding:5px 10px;text-align:left;">IP-adres</th>
  <th style="padding:5px 10px;text-align:left;">Label</th>
  <th style="padding:5px 10px;text-align:left;">Toegevoegd</th>
  <th style="padding:5px 10px;"></th>
</tr>
<?php foreach ($entries as $i => $e): ?>
<tr style="background:<?= $i%2?'#f0f5fa':'#fff' ?>;">
  <td style="padding:5px 10px;font-family:monospace;font-weight:bold;">
    <?= htmlspecialchars($e['ip']) ?>
    <?php if ($e['ip'] === $my_ip): ?>
      <span style="font-size:10px;color:#2a8;font-family:sans-serif;"> ← jij</span>
    <?php endif; ?>
  </td>
  <td style="padding:5px 10px;"><?= htmlspecialchars($e['label']) ?></td>
  <td style="padding:5px 10px;color:#7a9ab0;"><?= htmlspecialchars($e['added_at']) ?></td>
  <td style="padding:5px 10px;">
    <form method="post" style="margin:0;" onsubmit="return confirm('IP <?= htmlspecialchars($e['ip']) ?> verwijderen?');">
      <input type="hidden" name="ip" value="<?= htmlspecialchars($e['ip']) ?>" />
      <input type="submit" name="remove_ip" value="Verwijder" style="font-size:11px;" />
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</div>
