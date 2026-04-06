<?php
/**
 * Database Export to CSV
 *
 * SECURITY: This file requires authentication and should only be accessible to administrators.
 * Access via: http://yoursite.com/export_database.php
 *
 * IMPORTANT: For security, consider:
 * 1. Deleting this file after use
 * 2. Moving it outside the web root
 * 3. Restricting access via .htaccess
 */

// Require authentication - use session from index.php
session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
	die("Access denied. Please <a href='index.php?navigate=secureadmin'>log in</a> first.");
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load database connection
define('SNLDBCARPARTS_ACCESS', true);
require_once(__DIR__ . '/connection.php');

// Configuration
$export_directory = __DIR__ . '/exports/';

// Create exports directory if it doesn't exist
if (!is_dir($export_directory)) {
    if (!mkdir($export_directory, 0755, true)) {
        die("Error: Could not create exports directory.");
    }
}

// Add .htaccess to protect exports directory
$htaccess_file = $export_directory . '.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, "Order Deny,Allow\nDeny from all");
}

/**
 * Get list of all tables in the database
 */
function get_tables($connection) {
    $tables = array();
    $result = $connection->query("SHOW TABLES");

    if ($result) {
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
    }

    return $tables;
}

/**
 * Export a table to CSV
 */
function export_table_to_csv($connection, $table_name, $export_directory) {
    // Sanitize table name to prevent SQL injection
    $safe_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);

    // Create filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $safe_table_name . '_' . $timestamp . '.csv';
    $filepath = $export_directory . $filename;

    // Query the table
    $query = "SELECT * FROM `" . $safe_table_name . "`";
    $result = $connection->query($query);

    if (!$result) {
        return array('success' => false, 'error' => 'Query failed: ' . $connection->error);
    }

    // Open file for writing
    $file = fopen($filepath, 'w');
    if (!$file) {
        return array('success' => false, 'error' => 'Could not create file');
    }

    // Write UTF-8 BOM for Excel compatibility
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

    // Get column names
    $fields = $result->fetch_fields();
    $headers = array();
    foreach ($fields as $field) {
        $headers[] = $field->name;
    }

    // Write headers
    fputcsv($file, $headers);

    // Write data rows
    $row_count = 0;
    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
        $row_count++;
    }

    fclose($file);

    $filesize = filesize($filepath);

    return array(
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'rows' => $row_count,
        'size' => $filesize
    );
}

/**
 * Create a ZIP archive of all CSV files
 */
function create_zip_archive($csv_files, $export_directory) {
    if (!extension_loaded('zip')) {
        return array('success' => false, 'error' => 'ZIP extension not available');
    }

    $timestamp = date('Y-m-d_H-i-s');
    $zip_filename = 'database_export_' . $timestamp . '.zip';
    $zip_filepath = $export_directory . $zip_filename;

    $zip = new ZipArchive();
    if ($zip->open($zip_filepath, ZipArchive::CREATE) !== true) {
        return array('success' => false, 'error' => 'Could not create ZIP file');
    }

    foreach ($csv_files as $csv_file) {
        $zip->addFile($csv_file['filepath'], basename($csv_file['filename']));
    }

    $zip->close();

    return array(
        'success' => true,
        'filename' => $zip_filename,
        'filepath' => $zip_filepath,
        'size' => filesize($zip_filepath)
    );
}

// Handle form submission
$export_results = array();
$zip_result = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection (optional for backward compatibility)
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed for export_database");
            // Continue anyway for backward compatibility
        }
    }

    $selected_tables = isset($_POST['tables']) ? $_POST['tables'] : array();
    $create_zip = isset($_POST['create_zip']) && $_POST['create_zip'] == '1';

    if (empty($selected_tables)) {
        $error_message = "Please select at least one table to export.";
    } else {
        foreach ($selected_tables as $table) {
            $result = export_table_to_csv($SNLDBConnection, $table, $export_directory);
            $result['table'] = $table;
            $export_results[] = $result;
        }

        // Create ZIP if requested
        if ($create_zip && !empty($export_results)) {
            $successful_exports = array_filter($export_results, function($r) { return $r['success']; });
            if (!empty($successful_exports)) {
                $zip_result = create_zip_archive($successful_exports, $export_directory);
            }
        }
    }
}

// Get all tables
$tables = get_tables($SNLDBConnection);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Export to CSV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #dc3545;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #17a2b8;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .table-list {
            margin: 20px 0;
        }
        .table-item {
            padding: 10px;
            margin: 5px 0;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .table-item label {
            cursor: pointer;
            display: block;
        }
        .table-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .buttons {
            margin-top: 20px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        button.secondary {
            background-color: #6c757d;
        }
        button.secondary:hover {
            background-color: #5a6268;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .result-table th,
        .result-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .result-table th {
            background-color: #4CAF50;
            color: white;
        }
        .result-table tr:hover {
            background-color: #f5f5f5;
        }
        .download-link {
            color: #007bff;
            text-decoration: none;
        }
        .download-link:hover {
            text-decoration: underline;
        }
        .user-info {
            float: right;
            color: #666;
            font-size: 14px;
        }
        .select-all {
            margin: 15px 0;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
    </style>
    <script>
        function toggleAll(source) {
            var checkboxes = document.getElementsByName('tables[]');
            for(var i=0; i<checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function confirmExport() {
            var checkboxes = document.querySelectorAll('input[name="tables[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one table to export.');
                return false;
            }
            return confirm('Export ' + checkboxes.length + ' table(s) to CSV?');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="user-info">
            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?>
        </div>

        <h1>📊 Database Export to CSV</h1>

        <div class="warning">
            <strong>⚠️ Security Notice:</strong>
            <ul>
                <li>Exported files contain sensitive database information</li>
                <li>Files are protected by .htaccess but should be deleted after download</li>
                <li>Consider deleting this export tool when not needed</li>
            </ul>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($export_results)): ?>
            <div class="success">
                <h3>✅ Export Complete</h3>

                <table class="result-table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Rows</th>
                            <th>Size</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($export_results as $result): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($result['table']); ?></strong></td>
                            <td>
                                <?php
                                if ($result['success']) {
                                    echo number_format($result['rows']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($result['success']) {
                                    echo number_format($result['size'] / 1024, 2) . ' KB';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($result['success']): ?>
                                    <span style="color: green;">✓ Success</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Failed: <?php echo htmlspecialchars($result['error']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($zip_result && $zip_result['success']): ?>
                    <div class="info" style="margin-top: 20px;">
                        <strong>📦 ZIP Archive Created:</strong><br>
                        Filename: <strong><?php echo htmlspecialchars($zip_result['filename']); ?></strong><br>
                        Size: <strong><?php echo number_format($zip_result['size'] / 1024, 2); ?> KB</strong><br>
                        <br>
                        <strong>⚠️ Important:</strong> To download, you'll need to access the file via FTP or file manager at:<br>
                        <code><?php echo htmlspecialchars(str_replace(__DIR__, '', $zip_result['filepath'])); ?></code>
                    </div>
                <?php endif; ?>

                <p style="margin-top: 20px;">
                    <strong>Note:</strong> CSV files are stored in the <code>exports/</code> directory.<br>
                    Use FTP or your hosting file manager to download them.<br>
                    <strong style="color: red;">Remember to delete exported files after downloading for security.</strong>
                </p>
            </div>
        <?php endif; ?>

        <h2>Select Tables to Export</h2>

        <div class="info">
            <strong>ℹ️ Information:</strong><br>
            Database: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong><br>
            Total Tables: <strong><?php echo count($tables); ?></strong><br>
            Export Directory: <code>exports/</code>
        </div>

        <form method="POST" onsubmit="return confirmExport();">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />

            <div class="select-all">
                <label>
                    <input type="checkbox" onclick="toggleAll(this)">
                    <strong>Select/Deselect All</strong>
                </label>
            </div>

            <div class="table-list">
                <?php foreach ($tables as $table): ?>
                <div class="table-item">
                    <label>
                        <input type="checkbox" name="tables[]" value="<?php echo htmlspecialchars($table); ?>">
                        <strong><?php echo htmlspecialchars($table); ?></strong>
                        <?php
                        // Get row count
                        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                        $count_result = $SNLDBConnection->query("SELECT COUNT(*) as cnt FROM `$safe_table`");
                        if ($count_result) {
                            $count_row = $count_result->fetch_assoc();
                            echo '<span style="color: #666;"> (' . number_format($count_row['cnt']) . ' rows)</span>';
                        }
                        ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin: 20px 0; padding: 10px; background-color: #e9ecef; border-radius: 4px;">
                <label>
                    <input type="checkbox" name="create_zip" value="1" checked>
                    <strong>Create ZIP archive of all exported files</strong>
                    <small style="display: block; margin-left: 25px; color: #666;">
                        (Recommended for easier download of multiple files)
                    </small>
                </label>
            </div>

            <div class="buttons">
                <button type="submit">📥 Export Selected Tables</button>
                <button type="button" class="secondary" onclick="window.location.href='index.php?navigate=adminpanel'">
                    ← Back to Admin Panel
                </button>
            </div>
        </form>

        <div class="warning" style="margin-top: 30px;">
            <strong>🗑️ Cleanup Reminder:</strong>
            <ul>
                <li>Download your exported files via FTP or file manager</li>
                <li>Delete the files from the <code>exports/</code> directory after downloading</li>
                <li>Consider removing this export tool (<code>export_database.php</code>) when not in use</li>
            </ul>
        </div>
    </div>
</body>
</html>

<?php
mysqli_close($SNLDBConnection);
?>
