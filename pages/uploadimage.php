<div class="content-box">
       <h3>Plaatje toevoegen</h3>
       <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" />

<?php
// Check authentication - use session from index.php
session_start();

// Require authentication for image uploads
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo "<div style='color: red;'>Access denied. Please <a href='index.php?navigate=secureadmin'>log in</a> first.</div>";
    echo "</div>"; // Close content-box
    return;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECURITY: Validate License input
$myLicense = isset($_POST['License']) ? $_POST['License'] : '';

if (strlen($myLicense) > 4)
{
    echo "<br>Plaatjes toevoegen voor auto: " . htmlspecialchars($myLicense) . "<br>";

    // SECURITY: Sanitize license plate
    $stripLicense = preg_replace('/\s*/m', '', $myLicense);
    $stripLicense = strtoupper($stripLicense);
    $stripLicense = preg_replace('/[^A-Z0-9-]/', '', $stripLicense);

    $mylocation = './cars/' . $stripLicense . '/slides/';

    // Verify the directory exists
    if (!is_dir($mylocation)) {
        echo "<div style='color: red;'>Error: Directory does not exist for license: " . htmlspecialchars($stripLicense) . "</div>";
    } else {

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES["file"]))
        {
            // SECURITY: Validate CSRF token
            if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
                if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                    error_log("CSRF token validation failed for uploadimage");
                }
            }

            if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', true);
            include 'image_helper.php';
            $allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp');
            $allowed_mime_types = array('image/jpeg', 'image/jpg', 'image/gif', 'image/png', 'image/webp');
            $max_file_size = 20971520; // 20 MB raw input limit (server compresses to ≤1.5 MB)

            // Restructure $_FILES["file"] array for multi-upload loop
            $files = $_FILES["file"];
            $file_count = is_array($files["name"]) ? count($files["name"]) : 1;

            // Normalize to array format whether one or many files were uploaded
            if (!is_array($files["name"])) {
                $files["name"]     = [$files["name"]];
                $files["type"]     = [$files["type"]];
                $files["tmp_name"] = [$files["tmp_name"]];
                $files["error"]    = [$files["error"]];
                $files["size"]     = [$files["size"]];
            }

            $success_count = 0;
            $error_count   = 0;
            $saved_files   = [];

            echo "<hr><strong>Resultaten ({$file_count} bestand(en)):</strong><br><br>";

            for ($i = 0; $i < $file_count; $i++) {
                $filename = $files["name"][$i];
                $tmp      = $files["tmp_name"][$i];
                $error    = $files["error"][$i];
                $size     = $files["size"][$i];

                echo "<strong>" . htmlspecialchars($filename) . ":</strong> ";

                if ($error > 0) {
                    echo "<span style='color:red;'>Upload fout (code " . intval($error) . ")</span><br>";
                    $error_count++;
                    continue;
                }

                $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_mime      = mime_content_type($tmp);

                if (!in_array($file_extension, $allowed_extensions)) {
                    echo "<span style='color:red;'>Ongeldig bestandstype (alleen JPG, GIF, PNG, WebP)</span><br>";
                    $error_count++;
                } elseif (!in_array($file_mime, $allowed_mime_types)) {
                    echo "<span style='color:red;'>Ongeldig MIME type gedetecteerd</span><br>";
                    $error_count++;
                } elseif ($size > $max_file_size) {
                    echo "<span style='color:red;'>Bestand te groot (max " . ($max_file_size / 1024 / 1024) . " MB)</span><br>";
                    $error_count++;
                } else {
                    $base          = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
                    $safe_filename = $base . '.jpg'; // always save as JPEG
                    $target_file   = $mylocation . $safe_filename;

                    if (file_exists($target_file)) {
                        echo "<span style='color:orange;'>Bestaat al, overgeslagen. Hernoem en probeer opnieuw.</span><br>";
                        $error_count++;
                    } elseif (snldb_save_image($tmp, $target_file)) {
                        chmod($target_file, 0644);
                        $saved_kb = number_format(filesize($target_file) / 1024, 1);
                        echo "<span style='color:green;'>✓ Opgeslagen ({$saved_kb} KB)</span><br>";
                        $success_count++;
                        $saved_files[] = $safe_filename;
                    } else {
                        echo "<span style='color:red;'>Opslaan mislukt (controleer of GD beschikbaar is)</span><br>";
                        $error_count++;
                    }
                }
            }

            echo "<br><strong>Klaar: {$success_count} succesvol, {$error_count} mislukt.</strong><hr>";

            if ($success_count > 0) {
                include 'connection.php';
                include 'stats_helper.php';
                include 'photo_recent_helper.php';
                include_once 'car_stats_helper.php';
                $upd = $CarpartsConnection->prepare("UPDATE SNLDB SET moddate = NOW() WHERE License = ?");
                if ($upd) {
                    $upd->bind_param("s", $stripLicense);
                    $upd->execute();
                    $upd->close();
                }
                stats_day($CarpartsConnection, 'images_added', $success_count);
                foreach ($saved_files as $fn) {
                    photo_recent_add($CarpartsConnection, $stripLicense, $fn);
                }
                car_stats_log($CarpartsConnection, $stripLicense, 'edit');
                car_changelog_log($CarpartsConnection, $stripLicense, 'photo');
                mysqli_close($CarpartsConnection);
            }
        }

    } // End directory exists check

    // Show upload form
    ?>
    <center>
    <div id="drop-zone" style="border:2px dashed #576C85;border-radius:6px;padding:28px 20px;margin:10px 0;background:#f0f4ff;cursor:pointer;transition:background 0.15s;">
        <p style="margin:0;color:#576C85;font-size:14px;"><strong>Sleep foto's hierheen</strong> of klik om te selecteren</p>
        <p style="margin:6px 0 0;color:#888;font-size:11px;">JPG, GIF, PNG, WebP &mdash; max 20 MB per foto &mdash; maximaal 20 foto's per auto</p>
    </div>
    <div id="file-list" style="margin:6px 0;font-size:12px;color:#333;"></div>
    <form action="index.php?navigate=uploadimage" method="post" enctype="multipart/form-data" id="upload-form">
        <input type="file" name="file[]" id="file"
               accept="image/jpeg,image/jpg,image/gif,image/png,image/webp"
               multiple required
               style="display:none;" />
        <small style="color:#666;">Wordt automatisch verkleind naar max 1920&times;1280 en opgeslagen als JPEG.</small><br><br>
        <input type="hidden" name="License" value="<?php echo htmlspecialchars($myLicense); ?>" />
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="submit" name="submit" value="Uploaden!" style="padding:8px 20px;font-size:14px;" />
    </form>
    </center>
    <script>
    (function(){
        var zone  = document.getElementById('drop-zone');
        var input = document.getElementById('file');
        var list  = document.getElementById('file-list');
        function showFiles(files) {
            if (!files || files.length === 0) { list.innerHTML = ''; return; }
            var names = [];
            for (var i = 0; i < files.length; i++) names.push(files[i].name);
            list.innerHTML = '<strong>' + files.length + ' bestand(en) geselecteerd:</strong> ' + names.join(', ');
        }
        zone.addEventListener('click', function(){ input.click(); });
        zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.style.background = '#dce6ff'; });
        zone.addEventListener('dragleave', function(){ zone.style.background = '#f0f4ff'; });
        zone.addEventListener('drop', function(e){
            e.preventDefault();
            zone.style.background = '#f0f4ff';
            input.files = e.dataTransfer.files;
            showFiles(input.files);
        });
        input.addEventListener('change', function(){ showFiles(input.files); });
    })();
    </script>
    <br>
    <?php

} // End if (strlen($myLicense) > 4)
else
{
    echo "<div style='color: red;'>Please select a car first</div>";
}

?>
</div>
