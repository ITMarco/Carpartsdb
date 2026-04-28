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

// Override OG tags for part detail pages so social previews show the part image & title
if (isset($_GET['navigate']) && $_GET['navigate'] === 'viewpart' && !empty($_GET['id'])) {
    $_og_id = intval($_GET['id']);
    if ($_og_id > 0) {
        if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
        include_once 'config.php';
        include_once 'parts_helper.php';
        $_ogdb = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$_ogdb->connect_error) {
            $_ogst = $_ogdb->prepare(
                "SELECT p.`title`, p.`description`, m.`name` AS make_name, mo.`name` AS model_name
                 FROM `PARTS` p
                 JOIN `CAR_MAKES` m ON m.`id` = p.`make_id`
                 LEFT JOIN `CAR_MODELS` mo ON mo.`id` = p.`model_id`
                 WHERE p.`id` = ? AND p.`visible` = 1 LIMIT 1"
            );
            if ($_ogst) {
                $_ogst->bind_param('i', $_og_id);
                $_ogst->execute();
                $_ogrow = $_ogst->get_result()->fetch_assoc();
                $_ogst->close();
                if ($_ogrow) {
                    $og_title = $_ogrow['title']
                        . ' — ' . $_ogrow['make_name']
                        . ($_ogrow['model_name'] ? ' ' . $_ogrow['model_name'] : '')
                        . ' — Car Parts DB';
                    $og_description = !empty($_ogrow['description'])
                        ? substr(strip_tags($_ogrow['description']), 0, 200)
                        : 'Used car part: ' . $_ogrow['make_name']
                          . ($_ogrow['model_name'] ? ' ' . $_ogrow['model_name'] : '');
                    $og_url = 'https://www.supraclub.nl/carparts/index.php?navigate=viewpart&id=' . $_og_id;
                    $_ogphoto = parts_first_photo($_og_id);
                    if ($_ogphoto) {
                        $og_image = 'https://www.supraclub.nl/carparts/' . $_ogphoto;
                    }
                }
            }
            $_ogdb->close();
        }
        unset($_ogdb, $_ogst, $_ogrow, $_ogphoto);
    }
    unset($_og_id);
}

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
// Helper/shared files (connection.php, etc.) must NOT be added.
$allowed_pages = [
    // Public
    'home', 'browse', 'viewpart', 'address', 'privacyverklaring',
    // Self-signup & email confirmation
    'signup', 'processsignup', 'confirmemail', 'resendemail',
    // Authenticated users (sellers)
    'addpart', 'processaddpart', 'editpart', 'processeditpart',
    'deletepart', 'uploadpartimage', 'deletepartimage', 'myparts',
    'markpartsold', 'processpartmessage', 'processmessagereply', 'mymessages',
    // Auth
    'secureadmin', 'logout',
    // Admin
    'adminpanel', 'adminmakes',
    'insertuser', 'processinsertuser', 'edituser', 'processedituser',
    'themeadmin', 'ipwhitelist', 'commentadmin', 'homenews', 'carstats',
    // User preferences (AJAX)
    'savetheme', 'savebrowseview', 'inlineeditpart',
    // User profiles
    'userprofile', 'edituserprofile',
    // Bulk part actions
    'processbulkparts',
    // Reporting
    'flagpart', 'flagadmin', 'adminmessages',
    // Admin export
    'exportparts',
    // Info
    'about',
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
