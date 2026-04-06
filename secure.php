<?php
// Use session manager for secure session handling
require_once(__DIR__ . '/session_manager.php');

// Display session timeout message if user was logged out due to inactivity
if (isset($_SESSION['session_expired']) && $_SESSION['session_expired'] === true) {
	echo "<div style='color: red; padding: 10px; margin: 10px; border: 1px solid red;'>";
	echo "Your session has expired due to inactivity. Please log in again.";
	echo "</div>";
	unset($_SESSION['session_expired']);
}

// Display timeout parameter from URL if redirected
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
	echo "<div style='color: red; padding: 10px; margin: 10px; border: 1px solid red;'>";
	echo "Your session has expired due to inactivity. Please log in again.";
	echo "</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// SECURITY: Validate CSRF token
	require_csrf_token();

	// SECURITY: Sanitize and validate input
	$mylicense = isset($_POST['userlicense']) ? strtoupper(trim($_POST['userlicense'])) : '';
	$userpassword = isset($_POST['userpassword']) ? $_POST['userpassword'] : '';

	// SECURITY: Check rate limiting
	$rate_limit = check_rate_limit($mylicense);
	if ($rate_limit['blocked']) {
		$minutes = ceil($rate_limit['remaining_time'] / 60);
		echo "<div style='color: red; padding: 10px; margin: 10px; border: 2px solid red;'>";
		echo "<strong>Too many failed login attempts!</strong><br>";
		echo "Your account has been temporarily locked for security reasons.<br>";
		echo "Please try again in " . $minutes . " minute" . ($minutes != 1 ? 's' : '') . ".";
		echo "</div>";
	} else {
		$passcorrect = false;
		include 'connection.php';

	// SECURITY: Use prepared statement to prevent SQL injection
	$stmt = $CarpartsConnection->prepare("SELECT carlicense, password1, password2, userid, username FROM PASSWRDS WHERE carlicense = ?");

	if (!$stmt) {
		error_log("Prepare failed: " . $CarpartsConnection->error);
		die("Database error occurred.");
	}

	$stmt->bind_param("s", $mylicense);
	$stmt->execute();
	$result = $stmt->get_result();
	$myrow = $result->fetch_assoc();

	if ($myrow)
	{
		echo "Welkom " . htmlspecialchars($myrow['username']) . "<br>";

		// SECURITY: Check password with support for both hashed and legacy plaintext passwords
		$password_field_used = null;

		// Try password1 first
		if (!empty($myrow['password1'])) {
			// Check if it's a hashed password (starts with $2y$ for bcrypt)
			if (substr($myrow['password1'], 0, 4) === '$2y$') {
				// Hashed password - use password_verify
				if (password_verify($userpassword, $myrow['password1'])) {
					$passcorrect = true;
					$password_field_used = 'password1';
				}
			} else {
				// Legacy plaintext password
				if ($myrow['password1'] === $userpassword) {
					$passcorrect = true;
					$password_field_used = 'password1';

					// Automatically upgrade to hashed password
					$hashed_password = password_hash($userpassword, PASSWORD_DEFAULT);
					$update_stmt = $CarpartsConnection->prepare("UPDATE PASSWRDS SET password1 = ? WHERE carlicense = ?");
					$update_stmt->bind_param("ss", $hashed_password, $mylicense);
					$update_stmt->execute();
					$update_stmt->close();
					error_log("Password upgraded to hash for user: " . $mylicense);
				}
			}
		}

		// Try password2 if password1 didn't match
		if (!$passcorrect && !empty($myrow['password2'])) {
			// Check if it's a hashed password
			if (substr($myrow['password2'], 0, 4) === '$2y$') {
				// Hashed password - use password_verify
				if (password_verify($userpassword, $myrow['password2'])) {
					$passcorrect = true;
					$password_field_used = 'password2';
				}
			} else {
				// Legacy plaintext password
				if ($myrow['password2'] === $userpassword) {
					$passcorrect = true;
					$password_field_used = 'password2';

					// Automatically upgrade to hashed password
					$hashed_password = password_hash($userpassword, PASSWORD_DEFAULT);
					$update_stmt = $CarpartsConnection->prepare("UPDATE PASSWRDS SET password2 = ? WHERE carlicense = ?");
					$update_stmt->bind_param("ss", $hashed_password, $mylicense);
					$update_stmt->execute();
					$update_stmt->close();
					error_log("Password upgraded to hash for user: " . $mylicense);
				}
			}
		}

		if ($passcorrect == true)
		{
			// Regenerate session ID to prevent session fixation attacks
			regenerate_session_id();

			// Set session variable for authenticated user
			$_SESSION['authenticated'] = true;
			$_SESSION['user_license'] = $myrow['carlicense'];
			$_SESSION['username'] = $myrow['username'];
			$_SESSION['LAST_ACTIVITY'] = time();
			$_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

			// Reset login attempts after successful login
			reset_login_attempts($mylicense);

			// Redirect to admin panel after successful login
			header("Location: index.php?navigate=adminpanel");
			exit();
		} else
		{
			// Record failed login attempt
			record_failed_login($mylicense);
			$remaining = get_remaining_attempts($mylicense);

			echo "<div style='color: red;'>";
			echo "Incorrect password.";
			if ($remaining > 0 && $remaining < MAX_LOGIN_ATTEMPTS) {
				echo "<br><small>Warning: " . $remaining . " attempt" . ($remaining != 1 ? 's' : '') . " remaining before temporary lockout.</small>";
			}
			echo "</div>";
		}
	}
	else
	{
		// Record failed login attempt for non-existent user
		record_failed_login($mylicense);

		// Don't reveal that user doesn't exist (security through obscurity)
		echo "<div style='color: red;'>Incorrect username or password.</div>";
	}

		$stmt->close();
		mysqli_close($CarpartsConnection);
	} // End rate limiting check
}

// Always show login form (unless already logged in)
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>

<form name="secure" id="secure" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
<br>Kenteken:<br>
<input type="text" name="userlicense" value="<?php echo isset($mylicense) ? htmlspecialchars($mylicense) : ''; ?>" required /><br>
Password:<BR>
<input type="password" name="userpassword" required /><br>
<?php csrf_token_field(); ?>
<input type="submit" value="Login!" />
</form>

<?php
}
?>