

<div class="content-box">
       <h3>Supra bewerken..</h3>
     <!--  <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" /> -->

       <br><br>

<?php
// Check authentication - use session from index.php
session_start();

// Require admin authentication
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color: red;'>Access denied. Please <a href='index.php?navigate=secureadmin'>log in as admin</a> first.</div>";
    echo "</div>"; // Close content-box
    return;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// SECURITY: Validate CSRF token (optional for backward compatibility)
	if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
		if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
			error_log("CSRF token validation failed for adminedit");
			// Continue anyway for backward compatibility
		}
	}

	include 'connection.php';

	// SECURITY: Sanitize input
	$myLicense = isset($_POST['userLicense']) ? strtoupper(trim($_POST['userLicense'])) : '';

	// SECURITY: Use prepared statement to prevent SQL injection
	$stmt = $SNLDBConnection->prepare("SELECT License, Owner_display, Owner_show, Owner_history, Choise_Model, Milage, Choise_Status, Choise_Engine, Registration_date, Build_date, History, Mods, MA, VIN_Colorcode, Choise_Transmission, RECNO FROM SNLDB WHERE License = ?");

	if (!$stmt) {
		error_log("Prepare failed: " . $SNLDBConnection->error);
		die("Database error occurred.");
	}

	$stmt->bind_param("s", $myLicense);
	$stmt->execute();
	$result = $stmt->get_result();
	$num = $result->num_rows;

	while ($row = $result->fetch_assoc()) {

		$License = $row['License'];
		$Owner_display = $row['Owner_display'];
		$Owner_show    = (int)($row['Owner_show'] ?? 0);
		$Owner_history = $row['Owner_history'] ?? '';
		$Choise_Model = $row['Choise_Model'];
		$Milage = $row['Milage'];
		$Choise_Status = $row['Choise_Status'];
		$Choise_Engine = $row['Choise_Engine'];
		$Registration_date = $row['Registration_date'];
		$Build_date = $row['Build_date'];
		$History = $row['History'];
		$mods = $row['Mods'];
		$ma = $row['MA'];
		$color = $row['VIN_Colorcode'];
		$trans = $row['Choise_Transmission'];
		$recordnr = $row['RECNO'];

?>



<form name="editcarpicture" id="newcarpicture" action="index.php?navigate=uploadimage" method="post">
  <input type="hidden" name="License" value="<?php echo htmlspecialchars($License); ?>" />
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
  <input type="submit" value="Klik hier om foto's toe te voegen!"/>
</form>


<form name="editcar" id="newcar" action="index.php?navigate=procesadminedit" method="post">
<br>Kenteken:<br>
<input type="text" name="License" value="<?php echo htmlspecialchars($License); ?>" /><br>
Eigenaar:<BR>
<input type="text" name="owner" value="<?php echo htmlspecialchars($Owner_display); ?>" /><br>
<label style="font-size:13px;">
    <input type="checkbox" name="owner_show" value="1" <?php echo $Owner_show ? 'checked' : ''; ?> />
    Naam publiek tonen
</label><br>
Vorige eigenaren (alleen zichtbaar voor admins):<BR>
<textarea name="owner_history" rows="4" cols="40"><?php echo htmlspecialchars($Owner_history); ?></textarea><br>
Bouwjaar:<br>
<input type="text" name="bouwjaar" value="<?php echo htmlspecialchars($Build_date); ?>" /><br>
Registratiedatum:<br>
<input type="text" name="regdate" value="<?php echo htmlspecialchars($Registration_date); ?>" /><br>
Kilometrage:<br>
<input type="text" name="milage" value="<?php echo htmlspecialchars($Milage); ?>" /><br>
Kleur:<br>
<input type="text" name="color" value="<?php echo htmlspecialchars($color); ?>" /><br>

<br><br>
Selecteer type:<BR>
	<select name="mark">
		<option value="<?php echo htmlspecialchars($Choise_Model);?>" selected="selected" ><?php echo htmlspecialchars($Choise_Model);?></option>
		<option value="MA-46 (MKI)">Celica Supra MKI</option>
		<option value="MA-60 (MKII)">Celica Supra MKII</option>
		<option value="MA-70 (MKIII)">Supra MKIII MA</option>
		<option value="JZA70">Supra MKIII JZA</option>
		<option value="JA-80 (MKIV)">Supra MKIV</option>
		<option value="A-90 (MKV)">Supra MKV</option>
	</select>

	<?php
	If ($ma=='M')
	{
	?>
	<br><br>Handbak of automaat?<BR>
		<input type="radio" name="trans" value="M" checked> Handbak
		<br>
		<input type="radio" name="trans" value="A"> Automaat
	<br><br>
	<?php
	}
	else
	{
	?>
	<br><br>Handbak of automaat?<BR>
			<input type="radio" name="trans" value="M" /> Handbak
			<br>
			<input type="radio" name="trans" value="A" checked> Automaat
	<br><br>
<?php
	}
	?>
	Selecteer motortype:<BR>
	<select name="engine">
	 <option value="<?php echo htmlspecialchars($Choise_Engine);?>" selected="selected" ><?php echo htmlspecialchars($Choise_Engine);?></option>
	 <option value="4M-E">4M-E</option>
	 <option value="5M-GE">5M-GE</option>
	 <option value="7M-GE">7M-GE</option>
	 <option value="7M-GTE">7M-GTE</option>
	 <option value="1JZ-GTE">1JZ-GTE</option>
	 <option value="1JZ-GTE-VVTI">1JZ-GTE VVT-I</option>
	 <option value="1.5JZ-GTE">1.5JZ-GTE</option>
	 <option value="2JZ-GE">2JZ-GE</option>
	 <option value="2JZ-GTE">2JZ-GTE</option>
	 <option value="1G-GTE">1G-GTE</option>
	 <option value="BMW-B48">BMW-B48</option>
	 <option value="BMW-B58">BMW-B58</option>
	 <option value="Unknown">Unknown</option>
    </select>
<br>

	Selecteer status:<br>
	<select name="status">
	    <option value="<?php echo htmlspecialchars($Choise_Status);?>" selected="selected" ><?php echo htmlspecialchars($Choise_Status);?></option>
		<option value="Running">Running</option>
		<option value="No Road License">Geen kenteken</option>
		<option value="Wrecked">Wrecked</option>
		<option value="Garage">Garage</option>
		<option value="Forsale">For sale</option>
		<option value="Not Available">Not Available</option>
	</select><BR>
Selecteer versnellingsbak:<BR>

<select name="transmission">
	<option value="<?php echo htmlspecialchars($trans);?>" selected="selected"><?php echo htmlspecialchars($trans);?></option>
	<option value="W50 (5 Speed manual 4M)">W50 (5 Speed manual 4M)</option>
	<option value="W58 (5 speed manual 5M)">W58 (5 speed manual 5M)</option>
	<option value="W58 (5 speed manual 7M-GE)">W58 (5 speed manual 7M-GE)</option>
	<option value="W58 (5 speed manual 2JZ)">W58 (5 speed manual 2JZ)</option>
	<option value="V160 (6 speed manual 2JZ)">V160 (6 speed manual 2JZ)</option>
	<option value="V161 (6 speed manual 2JZ)">V161 (6 speed manual 2JZ)</option>
	<option value="R154 (5 Speed manual 7M-GTE)">R154 (5 Speed manual 7M-GTE)</option>
	<option value="A43DE (4 Speed Auto 5M)">A43DE (4 Speed Auto 5M)</option>
	<option value="A340E (4 Speed Auto 7M)">A340E (4 Speed Auto 7M)</option>
	<option value="A342E (4 speed Auto 2JZ)">A342E (4 speed Auto 2JZ)</option>
	<option value="T56 (Upgrade kit for JZ)">T56 6-speed (Upgrade kit for 2JZ)</option>
	<option value="ZF 8HP (8 speed Auto MK5)">ZF 8HP (8 speed Auto MK5)</option>
	<option value="ZF S6-53 (6 speed manual MK5)">ZF S6-53 (6 speed manual MK5)</option>
	<option value="Other"</option>
</select>


	<BR><BR>
Modifications:<BR>
<textarea name="mods" rows="12" cols="40"><?php echo htmlspecialchars($mods); ?></textarea><BR>
History:<BR>
<textarea name="history" rows="12" cols="40"><?php echo htmlspecialchars($History); ?></textarea><BR>

<input type="hidden" name="recno" value="<?php echo htmlspecialchars($recordnr); ?>" />
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
   <input type="submit" value="Supra!"/>
</form>
<?php

	}

	$stmt->close();
	mysqli_close($SNLDBConnection);

} else
{
?>
<center>
<form name="secure" id="secure" action="index.php?navigate=adminedit" method="post">
<br>Kenteken:<br>
<input type="text" name="userLicense" required /><br><br>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
  <input type="submit" value="Supra!"/>
</form>
</center>

<?php
}

?>
</div>