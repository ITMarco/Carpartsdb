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

include('engine/header.engine.php');
include('engine/body_top.engine.php');

// SECURITY: Whitelist approach — prevents Local File Inclusion (LFI) attacks.
// Only pages listed here are accessible via ?navigate=.
// Helper/shared files (rdwu_functions.php, connection.php, etc.) must NOT be added.
$allowed_pages = [
    // Public
    'home', 'browse', 'viewpart', 'about', 'address', 'privacyverklaring',
    // Self-signup & email confirmation
    'signup', 'processsignup', 'confirmemail', 'resendemail',
    // Authenticated users (sellers)
    'addpart', 'processaddpart', 'editpart', 'processeditpart',
    'deletepart', 'uploadpartimage', 'deletepartimage', 'myparts',
    // Auth
    'secureadmin', 'logout',
    // Admin
    'adminpanel', 'adminmakes',
    'insertuser', 'processinsertuser', 'edituser', 'processedituser',
    'themeadmin', 'ipwhitelist', 'commentadmin', 'homenews', 'carstats',
];

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
