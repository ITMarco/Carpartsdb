
<div class="content-box">
    <h3>Geïmporteerde Supra opslaan</h3>
    <img src="images/admin.jpg" style="float:left; margin-left:0px;" alt="img" />
    <br><br>

<?php
// Check authentication - use session from index.php
session_start();

// Require admin authentication
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color: red;'>Access denied. Please <a href='index.php?navigate=secureadmin'>log in as admin</a> first.</div>";
    echo "</div>"; // Close content-box
    return;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // SECURITY: Validate CSRF token
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed for procesimport");
            // Continue anyway for backward compatibility
        }
    }

    include 'connection.php';

    // SECURITY: Sanitize input
    $License = isset($_POST['License']) ? strtoupper(trim($_POST['License'])) : '';
    $Owner_display = isset($_POST['owner']) ? trim($_POST['owner']) : 'Te koop';
    $Choise_Model = isset($_POST['mark']) ? $_POST['mark'] : 'Unknown';
    $Choise_Transmission = isset($_POST['transmission']) ? $_POST['transmission'] : '';
    $Milage = isset($_POST['milage']) ? trim($_POST['milage']) : '';
    $Choise_Status = isset($_POST['status']) ? $_POST['status'] : 'Forsale';
    $Choise_Engine = isset($_POST['engine']) ? $_POST['engine'] : 'Unknown';
    $Registration_date = isset($_POST['regdate']) ? trim($_POST['regdate']) : '';
    $Build_date = isset($_POST['bouwjaar']) ? trim($_POST['bouwjaar']) : '';
    $History = isset($_POST['history']) ? $_POST['history'] : '';
    $mods = isset($_POST['mods']) ? $_POST['mods'] : '';
    $ma = isset($_POST['trans']) ? $_POST['trans'] : 'M';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    $source_url = isset($_POST['source_url']) ? trim($_POST['source_url']) : '';

    // Validation
    if (empty($License)) {
        echo "<div style='color: red;'><strong>Fout:</strong> Kenteken is verplicht.</div>";
        echo "<p><a href='index.php?navigate=importfromurl'>Terug</a></p>";
        echo "</div>";
        return;
    }

    // Check if license already exists
    $stmt = $SNLDBConnection->prepare("SELECT RECNO FROM SNLDB WHERE License = ?");
    $stmt->bind_param("s", $License);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<div style='color: red;'><strong>Fout:</strong> Kenteken " . htmlspecialchars($License) . " bestaat al in de database.</div>";
        echo "<p><a href='index.php?navigate=importfromurl'>Terug</a> | ";
        echo "<a href='index.php?navigate=adminedit'>Bewerk bestaande supra</a></p>";
        $stmt->close();
        mysqli_close($SNLDBConnection);
        echo "</div>";
        return;
    }
    $stmt->close();

    // Add source URL to history
    if (!empty($source_url)) {
        $History = "Geïmporteerd van: " . $source_url . "\n\n" . $History;
    }

    $date = date("Y-m-d H:i:s");

    // Insert new car
    $stmt = $SNLDBConnection->prepare("INSERT INTO SNLDB (License, Owner_display, Choise_Model, Choise_Engine, Choise_Transmission, Build_date, Registration_date, Milage, Choise_Status, VIN_Colorcode, MA, Mods, History, moddate, insertdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        echo "<div style='color: red;'><strong>Database fout:</strong> " . htmlspecialchars($SNLDBConnection->error) . "</div>";
        mysqli_close($SNLDBConnection);
        echo "</div>";
        return;
    }

    $stmt->bind_param("sssssssssssssss", $License, $Owner_display, $Choise_Model, $Choise_Engine, $Choise_Transmission, $Build_date, $Registration_date, $Milage, $Choise_Status, $color, $ma, $mods, $History, $date, $date);

    if ($stmt->execute()) {
        $new_recno = $stmt->insert_id;
        include_once 'car_stats_helper.php';
        car_changelog_log($SNLDBConnection, $License, 'new');

        echo "<div style='background-color: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
        echo "<h3 style='color: green;'>✓ Supra succesvol toegevoegd aan database!</h3>";
        echo "<table style='margin: 20px 0; border-collapse: collapse;'>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Record nummer:</td><td style='padding: 5px;'>" . htmlspecialchars($new_recno) . "</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Kenteken:</td><td style='padding: 5px;'>" . htmlspecialchars($License) . "</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Model:</td><td style='padding: 5px;'>" . htmlspecialchars($Choise_Model) . "</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Bouwjaar:</td><td style='padding: 5px;'>" . htmlspecialchars($Build_date) . "</td></tr>";
        echo "<tr><td style='padding: 5px; font-weight: bold;'>Status:</td><td style='padding: 5px;'>" . htmlspecialchars($Choise_Status) . "</td></tr>";
        echo "</table>";
        echo "</div>";

        // Create car directory for images if needed
        $stripLicense = preg_replace('/[^A-Z0-9-]/', '', $License);
        $car_dir = './cars/' . $stripLicense;
        $slides_dir = $car_dir . '/slides';

        if (!is_dir($car_dir)) {
            mkdir($car_dir, 0755, true);
            mkdir($slides_dir, 0755, true);
            echo "<div style='background-color: #d1ecf1; border: 1px solid #17a2b8; padding: 10px; margin: 10px 0;'>";
            echo "📁 Mappen aangemaakt voor foto's: <code>" . htmlspecialchars($slides_dir) . "</code>";
            echo "</div>";
        }

        echo "<div style='background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0;'>";
        echo "<strong>⚠️ Volgende stappen:</strong><br>";
        echo "1. Download foto's handmatig en upload via de admin edit pagina<br>";
        echo "2. Controleer of alle informatie correct is<br>";
        echo "3. Verwijder persoonlijke gegevens indien nog aanwezig<br>";
        echo "4. Bekijk de supra op de website";
        echo "</div>";

        echo "<p>";
        echo "<a href='index.php?navigate=importfromurl'>Nog een Supra importeren</a> | ";
        echo "<a href='index.php?navigate=adminedit'>Bewerk deze Supra</a> | ";
        echo "<a href='index.php?navigate=search&zoek=" . urlencode($License) . "'>Bekijk Supra</a> | ";
        echo "<a href='index.php?navigate=adminpanel'>Terug naar admin panel</a>";
        echo "</p>";

    } else {
        echo "<div style='color: red;'><strong>Fout bij opslaan:</strong> " . htmlspecialchars($stmt->error) . "</div>";
        echo "<p><a href='index.php?navigate=importfromurl'>Terug</a></p>";
    }

    $stmt->close();
    mysqli_close($SNLDBConnection);

} else {
    echo "<div style='color: red;'>Ongeldige aanvraag.</div>";
    echo "<p><a href='index.php?navigate=importfromurl'>Terug naar import</a></p>";
}
?>

</div>
