<body>

<div id="container">
<div id="core_header">
    <!-- Hamburger button (test — original sidebar menu is kept in place) -->
    <button id="hamburger-btn"
            onclick="var n=document.getElementById('hamburger-nav');n.style.display=(n.style.display==='block'?'none':'block');"
            aria-label="Menu" title="Menu">
        <span></span><span></span><span></span>
    </button>
    <nav id="hamburger-nav">
        <ul>
            <?php include("data/menu.data.php"); ?>
        </ul>
    </nav>
    <div id="header_text"><font color="black">
<?php
echo 'Online users: ' . getOnlineUsers();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $uname = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_email'] ?? '');
    echo ' &mdash; <strong>' . $uname . '</strong>';
    if (!empty($_SESSION['isadmin'])) {
        echo ' &nbsp;<a href="index.php?navigate=adminpanel" style="color:#000;font-size:12px;">[Admin]</a>';
    }
}
?></font></div>
</div>

	<div id="core_container">
	<div id="core_container2">

		<div id="core_right">


