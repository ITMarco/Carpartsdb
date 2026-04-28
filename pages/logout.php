<div class="content-box">
    <h3>Logging out...</h3>
    <br><br>

<?php
// Session is already started by index.php, so we don't need session_start() here

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

echo "<p>You have been successfully logged out.</p>";
echo "<p>Redirecting to home page...</p>";
echo "<script>setTimeout(function(){ window.location.replace('index.php'); }, 2000);</script>";
?>

</div>
