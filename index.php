<?php


//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

session_start();

// Default OpenGraph values
$og_title       = "Car Parts DB — Used Car Parts Marketplace";
$og_description = "Browse and sell used car parts. Search by make, model, year or OEM number.";
$og_image       = "https://www.supraclub.nl/carparts/images/header1.jpg";
$og_url         = "https://www.supraclub.nl/carparts/index.php";

define("MAX_IDLE_TIME", 600); // seconds

function getOnlineUsers() {
    session_save_path("/tmp");
    $dh    = opendir(session_save_path()) or die("Could not open session path<BR>\n");
    $count = 0;
    while ($file = readdir($dh)) {
        if (!((time() - fileatime(session_save_path() . '/' . $file) > MAX_IDLE_TIME)
               || $file == '.' || $file == '..')) {
            $count++;
        }
    }
    return $count;
}

// SECURITY: Whitelist approach — prevents Local File Inclusion (LFI) attacks.
// Only pages listed here are accessible via ?navigate=.
// Helper/shared files (rdwu_functions.php, connection.php, etc.) must NOT be added.
$allowed_pages = [
    // Public
    'home', 'browse', 'viewpart', 'address', 'privacyverklaring',
    // Self-signup & email confirmation
    'signup', 'processsignup', 'confirmemail', 'resendemail',
    // Authenticated users (sellers)
    'addpart', 'processaddpart', 'editpart', 'processeditpart',
    'deletepart', 'uploadpartimage', 'deletepartimage', 'myparts',
    'markpartsold', 'processpartmessage', 'mymessages',
    // Auth
    'secureadmin', 'logout',
    // Admin
    'adminpanel', 'adminmakes',
    'insertuser', 'processinsertuser', 'edituser', 'processedituser',
    'themeadmin', 'ipwhitelist', 'commentadmin', 'homenews', 'carstats',
    // User preferences (AJAX)
    'savetheme', 'savebrowseview',
    // User profiles
    'userprofile', 'edituserprofile',
    // Bulk part actions
    'processbulkparts',
    // Reporting
    'flagpart', 'flagadmin',
    // Admin export
    'exportparts',
];

// AJAX requests bypass the page layout so they can return pure JSON
// (body_top outputs HTML before the page file runs, which would corrupt JSON responses)
if (isset($_GET['ajax'], $_GET['navigate'])) {
    if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
    include_once 'config.php';
    $pagina = basename($_GET['navigate']);
    $pagina = str_replace(['.', '/', '\\'], '', $pagina);
    if (in_array($pagina, $allowed_pages) && file_exists('pages/' . $pagina . '.php')) {
        include('pages/' . $pagina . '.php');
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not found']);
    }
    exit();
}

include('engine/header.engine.php');
include('engine/body_top.engine.php');

if (isset($_GET['navigate'])) {
    $pagina = basename($_GET['navigate']);
    $pagina = str_replace(['.', '/', '\\'], '', $pagina);

    if (in_array($pagina, $allowed_pages) && file_exists('pages/' . $pagina . '.php')) {
        include('pages/' . $pagina . '.php');
    } else {
        include('data/error404.data.php');
    }
} else {
    include('pages/home.php');
}

include('engine/body_bottom.engine.php');
?>
