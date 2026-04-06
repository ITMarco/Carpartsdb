<?php
/**
 * Session Management with Security Features
 * Include this file at the top of any page that requires session handling
 */

// Define access constant before loading config
if (!defined('CARPARTS_ACCESS')) {
    define('CARPARTS_ACCESS', true);
}

// Load configuration
require_once(__DIR__ . '/config.php');

// Enforce HTTPS if required
if (HTTPS_REQUIRED && !isset($_SERVER['HTTPS'])) {
    // Redirect to HTTPS version
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: " . $redirect_url, true, 301);
    exit();
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Configure session parameters for security
    ini_set('session.cookie_httponly', '1'); // Prevent JavaScript access to session cookie
    ini_set('session.use_only_cookies', '1'); // Only use cookies, not URL parameters
    ini_set('session.cookie_samesite', 'Strict'); // CSRF protection

    // Set session name from config
    session_name(SESSION_NAME);

    // Start the session
    session_start();
}

/**
 * Check and enforce session timeout
 */
function check_session_timeout() {
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];

        if ($elapsed_time > SESSION_TIMEOUT) {
            // Session has expired
            session_unset();
            session_destroy();
            session_start(); // Start a new session
            $_SESSION['session_expired'] = true;
            return false;
        }
    }

    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Require authentication - redirect or die if not authenticated
 */
function require_authentication($redirect_to_login = false) {
    if (!check_session_timeout()) {
        if ($redirect_to_login) {
            header('Location: index.php?navigate=secureadmin&timeout=1');
            exit();
        } else {
            die("Session expired. Please log in again.");
        }
    }

    if (!is_authenticated()) {
        if ($redirect_to_login) {
            header('Location: index.php?navigate=secureadmin');
            exit();
        } else {
            die("Access denied. Please log in first.");
        }
    }
}

/**
 * Regenerate session ID to prevent session fixation attacks
 * Call this after successful login
 */
function regenerate_session_id() {
    session_regenerate_id(true);
}

/**
 * Check for session hijacking by validating user agent
 */
function validate_session() {
    if (!isset($_SESSION['HTTP_USER_AGENT'])) {
        $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
    }

    if ($_SESSION['HTTP_USER_AGENT'] !== $_SERVER['HTTP_USER_AGENT']) {
        // Possible session hijacking attempt
        session_unset();
        session_destroy();
        die("Security violation detected. Please log in again.");
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token (generate if doesn't exist)
 */
function get_csrf_token() {
    return generate_csrf_token();
}

/**
 * Output CSRF token as hidden input field
 */
function csrf_token_field() {
    $token = get_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '" />';
}

/**
 * Validate CSRF token from POST request
 * Returns true if valid, false otherwise
 */
function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Require valid CSRF token - die if invalid
 */
function require_csrf_token() {
    if (!validate_csrf_token()) {
        error_log("CSRF token validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
        die("Security error: Invalid CSRF token. Please refresh the page and try again.");
    }
}

// Automatically check session timeout on every page load
check_session_timeout();

// Validate session to prevent hijacking
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    validate_session();
}

// Generate CSRF token for this session
generate_csrf_token();

// Rate limiting functions
require_once __DIR__ . '/login_helper.php';
