                         <li><a href="index.php" title="Home">Car Parts DB</a></li>
                         <li><a href="index.php?navigate=browse" title="Browse all parts">Browse Parts</a></li>
                         <li><a href="index.php?navigate=addpart" title="Add a part to your collection">Add a Part</a></li>
                         <?php if (!empty($_SESSION['authenticated'])): ?>
                         <li><a href="index.php?navigate=myparts" title="Manage your parts collection">My Parts</a></li>
                         <?php endif; ?>
<li><a href="index.php?navigate=address" title="Contact">Contact</a></li>
                         <?php if (!empty($_SESSION['authenticated'])): ?>
                         <li><a href="index.php?navigate=userprofile&id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" title="My profile">My Profile</a></li>
                         <li><a href="index.php?navigate=logout" title="Log out">Logout</a></li>
                         <?php endif; ?>
