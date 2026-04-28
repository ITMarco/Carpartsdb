                         <li><a href="index.php" title="Home">Car Parts DB</a></li>
                         <li><a href="index.php?navigate=browse" title="Browse all parts">Browse Parts</a></li>
                         <li><a href="index.php?navigate=addpart" title="Add a part to your collection">Add a Part</a></li>
                         <?php if (!empty($_SESSION['authenticated'])): ?>
                         <li><a href="index.php?navigate=myparts" title="Manage your parts collection">My Parts</a></li>
                         <?php
                         // Unread message count for nav badge
                         $_nav_unread = 0;
                         if (!empty($_SESSION['user_id'])) {
                             if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1);
                             include_once 'config.php';
                             $_nav_own_conn = false;
                             if (!isset($CarpartsConnection) || !($CarpartsConnection instanceof mysqli)) {
                                 $_nav_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                                 $_nav_own_conn = true;
                             } else {
                                 $_nav_db = $CarpartsConnection;
                             }
                             if (!$_nav_db->connect_error) {
                                 $_nav_uid = (int)$_SESSION['user_id'];
                                 try {
                                     $_nav_stmt = $_nav_db->prepare(
                                         "SELECT COUNT(*) FROM `PART_MESSAGES` WHERE `recipient_id` = ? AND `is_read` = 0"
                                     );
                                     if ($_nav_stmt) {
                                         $_nav_stmt->bind_param('i', $_nav_uid);
                                         $_nav_stmt->execute();
                                         $_nav_stmt->bind_result($_nav_unread);
                                         $_nav_stmt->fetch();
                                         $_nav_stmt->close();
                                     }
                                 } catch (\Throwable $_nav_e) {
                                     // columns not yet migrated — badge stays 0
                                 }
                             }
                             if ($_nav_own_conn) { $_nav_db->close(); }
                             unset($_nav_db, $_nav_own_conn, $_nav_stmt, $_nav_uid, $_nav_e);
                         }
                         ?>
                         <li>
                             <a href="index.php?navigate=mymessages" title="Your messages">Messages<?php if ($_nav_unread > 0): ?> <span style="display:inline-block;background:#c87020;color:#fff;border-radius:8px;padding:0 6px;font-size:10px;font-weight:bold;vertical-align:middle;"><?= $_nav_unread ?></span><?php endif; ?></a>
                         </li>
                         <?php endif; ?>
<li><a href="index.php?navigate=address" title="Contact">Contact</a></li>
<li><a href="index.php?navigate=about" title="About &amp; Help">About / Help</a></li>
                         <?php if (!empty($_SESSION['authenticated'])): ?>
                         <li><a href="index.php?navigate=userprofile&id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" title="My profile">My Profile</a></li>
                         <?php if (!empty($_SESSION['isadmin'])): ?>
                         <li><a href="index.php?navigate=adminpanel" title="Admin panel">Admin Panel</a></li>
                         <?php endif; ?>
                         <li><a href="index.php?navigate=logout" title="Log out">Logout</a></li>
                         <?php endif; ?>
