<?php
/**
 * Database Configuration
 * IMPORTANT: In production, move this file outside the web root directory
 * for additional security. Update the path in connection.php accordingly.
 */

// Prevent direct access to this file
if (!defined('SNLDBCARPARTS_ACCESS')) {
    die('Direct access to this file is not permitted.');
}

// Database credentials
define('DB_HOST', 'h29.mijn.host');
define('DB_USER', 'supraclub_carpartsdb01');
define('DB_PASS', 'YmT2K7mGp8BPRC567ubh');
define('DB_NAME', 'supraclub_carpartsdb01');

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('SESSION_NAME', 'SNLDBCARPARTS_SESSION');

// Security settings
define('HTTPS_REQUIRED', false); // Set to true when HTTPS is available
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
