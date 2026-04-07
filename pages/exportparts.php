<?php
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div class='content-box'><p style='color:red;'>Access denied.</p></div>";
    return;
}

if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
include 'connection.php';
include_once 'parts_helper.php';
parts_ensure_table($CarpartsConnection);

// ── Trigger download ───────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $filter_sold = isset($_GET['include_sold']) ? '' : " AND COALESCE(p.`is_sold`,0)=0";

    $res = $CarpartsConnection->query(
        "SELECT p.`id`, p.`title`, m.`name` AS make, mo.`name` AS model,
                p.`year_from`, p.`year_to`, p.`price`, p.`condition`, p.`stock`,
                p.`oem_number`, p.`replacement_number`, p.`description`,
                p.`for_sale`, COALESCE(p.`is_sold`,0) AS is_sold,
                p.`visible`, p.`view_count`, p.`created_at`,
                u.`realname` AS seller_name, u.`email` AS seller_email
         FROM `PARTS` p
         JOIN `CAR_MAKES` m ON m.`id`=p.`make_id`
         LEFT JOIN `CAR_MODELS` mo ON mo.`id`=p.`model_id`
         JOIN `USERS` u ON u.`id`=p.`seller_id`
         WHERE p.`visible`=1 {$filter_sold}
         ORDER BY p.`created_at` DESC"
    );

    mysqli_close($CarpartsConnection);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="carparts_export_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens it correctly
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'ID','Title','Make','Model','Year from','Year to',
        'Price (EUR)','Condition (0-5)','Stock',
        'OEM number','Replacement OEM','For sale','Sold',
        'Views','Listed date','Seller name','Seller email','Description'
    ]);

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['title'],
                $row['make'],
                $row['model'] ?? '',
                $row['year_from'],
                $row['year_to'] ?? '',
                $row['price'] ?? '',
                $row['condition'],
                $row['stock'],
                $row['oem_number'] ?? '',
                $row['replacement_number'] ?? '',
                $row['for_sale'] ? 'Yes' : 'No',
                $row['is_sold']  ? 'Yes' : 'No',
                $row['view_count'] ?? 0,
                substr($row['created_at'], 0, 10),
                $row['seller_name'] ?: $row['seller_email'],
                $row['seller_email'],
                $row['description'] ?? '',
            ]);
        }
    }
    fclose($out);
    exit();
}

// ── UI ─────────────────────────────────────────────────────────────────────────
$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `PARTS` WHERE `visible`=1 AND COALESCE(`is_sold`,0)=0");
$active_count = $r ? $r->fetch_row()[0] : 0;
$r = $CarpartsConnection->query("SELECT COUNT(*) FROM `PARTS` WHERE `visible`=1 AND COALESCE(`is_sold`,0)=1");
$sold_count = $r ? $r->fetch_row()[0] : 0;
mysqli_close($CarpartsConnection);
?>
<div class="content-box">
<h3>Export parts to CSV</h3>

<p style="font-size:13px;">
    Active listings: <strong><?= number_format($active_count) ?></strong> &nbsp;&nbsp;
    Sold: <strong><?= number_format($sold_count) ?></strong>
</p>

<form method="get" action="index.php">
    <input type="hidden" name="navigate" value="exportparts" />
    <input type="hidden" name="download" value="1" />
    <label>
        <input type="checkbox" name="include_sold" value="1" />
        Include sold listings
    </label><br><br>
    <input type="submit" value="Download CSV" class="btn" style="padding:8px 20px;" />
</form>

<p style="margin-top:14px;font-size:12px;color:#888;">
    Exports all visible parts (with or without sold ones) including seller info, views, OEM numbers and description.<br>
    File is UTF-8 with BOM — opens correctly in Excel.
</p>
</div>
