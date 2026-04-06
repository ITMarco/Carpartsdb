<div class="content-box">
       <h3>Voeg een nieuwe supra toe.</h3>
     <!--  <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" /> -->

       <br><br>
<?php
// Use session manager for secure authentication
require_once(__DIR__ . '/../session_manager.php');

// Require authentication for adding new cars
require_authentication();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// SECURITY: Validate CSRF token
	require_csrf_token();

	include 'connection.php';

	// SECURITY: Sanitize and validate all inputs
	$date = date("Y-m-d H:i:s");
	$notavail = "No data";

	// Get and sanitize POST data
	$License = isset($_POST['License']) ? trim($_POST['License']) : '';
	$owner = isset($_POST['owner']) ? trim($_POST['owner']) : '';
	$mark = isset($_POST['mark']) ? $_POST['mark'] : '';
	$engine = isset($_POST['engine']) ? $_POST['engine'] : '';
	$transmission = isset($_POST['transmission']) ? $_POST['transmission'] : '';
	$bouwjaar = isset($_POST['bouwjaar']) ? trim($_POST['bouwjaar']) : '';
	$regdate = isset($_POST['regdate']) ? trim($_POST['regdate']) : '';
	$milage = isset($_POST['milage']) ? trim($_POST['milage']) : '';
	$status = isset($_POST['status']) ? $_POST['status'] : '';
	$color = isset($_POST['color']) ? trim($_POST['color']) : '';
	$trans = isset($_POST['trans']) ? $_POST['trans'] : '';
	$mods = isset($_POST['mods']) ? htmlspecialchars($_POST['mods'], ENT_QUOTES) : '';
	$History = isset($_POST['history']) ? htmlspecialchars($_POST['history'], ENT_QUOTES) : '';

	// SECURITY: Use prepared statement to prevent SQL injection
	$stmt = $CarpartsConnection->prepare("INSERT INTO SNLDB (License, Owner_display, Choise_Model, Choise_Engine, Choise_Transmission, Build_date, Registration_date, Milage, Choise_Status, VIN_Number, VIN_Modelcode, VIN_Colorcode, MA, Mods, History, RECNO, moddate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)");

	if (!$stmt) {
		error_log("Prepare failed: " . $CarpartsConnection->error);
		die("Database error occurred.");
	}

	$stmt->bind_param("ssssssssssssssss", $License, $owner, $mark, $engine, $transmission, $bouwjaar, $regdate, $milage, $status, $notavail, $notavail, $color, $trans, $mods, $History, $date);

	if ($stmt->execute()) {
		$stmt->close();
		include 'stats_helper.php';
		include_once 'car_stats_helper.php';
		stats_day($CarpartsConnection, 'supras_added');
		car_changelog_log($CarpartsConnection, $License, 'new');
		mysqli_close($CarpartsConnection);

		// Create folder structure
		$myLicense = $License;
		$sPattern = '/\s*/m';
		$sReplace = '';
		$stripLicense = preg_replace($sPattern, $sReplace, $myLicense);
		$stripLicense = strtoupper($stripLicense);

		// SECURITY: Validate license plate format to prevent directory traversal
		$stripLicense = preg_replace('/[^A-Z0-9-]/', '', $stripLicense);

		$MyStruct = './cars/' . $stripLicense . '/slides/';

		if (mkdir($MyStruct, 0755, true))
		{
			echo "Created car and image folder";

	  ?>
	    <form name="editcarpicture" id="newcarpicture" action="index.php?navigate=uploadimage" method="post">
	    <input type="hidden" name="License" value="<?php echo htmlspecialchars($stripLicense); ?>" />
	    <?php csrf_token_field(); ?>
	    <input type="submit" value="Klik hier om foto's toe te voegen!"/>
	  </form>

	  <?php

		}
		else
		{
			die("Failed to create image folder: " . htmlspecialchars($MyStruct));
		}
	} else {
		$stmt->close();
		mysqli_close($CarpartsConnection);
		die("Error inserting record into database.");
	}

} else
{
	if (isset($_SESSION['isadmin']) && $_SESSION['isadmin'] === 1)
	{
?>


<form name="newcar" id="newcar" action="index.php?navigate=insertnew" method="post">
<br>Kenteken:<br>
<input type="text" name="License"/><br>
Eigenaar:<BR>
<input type="text" name="owner"/><br>
Bouwjaar:<br>
<input type="text" name="bouwjaar"/><br>
Registratiedatum:<br>
<input type="text" name="regdate"/><br>
Kilometrage:<br>
<input type="text" name="milage"/><br>
Kleur:<br>
<input type="text" name="color"/><br>

<br><br>
Selecteer type:<BR>
	<select name="mark">
		<option value="MA-46 (MKI)">Celica Supra MKI</option>
		<option value="MA-60 (MKII)">Celica Supra MKII</option>
		<option value="MA-70 (MKIII)" selected="selected">Supra MKIII MA</option>
		<option value="JZA70">Supra MKIII JZA</option>
		<option value="JA-80 (MKIV)">Supra MKIV</option>
		<option value="A-90 (MKV)">Supra MKV</option>		
	</select>
	<br><br>Handbak of automaat?<BR>
		<input type="radio" name="trans" value="M" /> Handbak
		<br>
		<input type="radio" name="trans" value="A"> Automaat
	<br><br>
	Selecteer motortype:<BR>
	<select name="engine">
	 <option value="4M-E">4M-E</option>
	 <option value="5M-GE">5M-GE</option>
	 <option value="7M-GE">7M-GE</option>
	 <option value="7M-GTE" selected="selected">7M-GTE</option>
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
		<option value="Running" selected="selected">Rijdend</option>
		<option value="No Road License">Geen kenteken</option>
		<option value="Wrecked">Wrecked</option>
		<option value="Garage">Garage</option>
		<option value="Forsale">For sale</option>
		<option value="Not Available">Not Available</option>
	</select>
Selecteer versnellingsbak:<BR>
<select name="transmission">
	<option value="W50 (5 Speed manual 4M)">W50 (5 Speed manual 4M)</option>
	<option value="W58 (5 speed manual 5M)">W58 (5 speed manual 5M)</option>
	<option value="W58 (5 speed manual 7M-GE)">W58 (5 speed manual 7M-GE)</option>
	<option value="W58 (5 speed manual 2JZ)">W58 (5 speed manual 2JZ)</option>
	<option value="V160 (6 speed manual 2JZ)">V160 (6 speed manual 2JZ)</option>
	<option value="V161 (6 speed manual 2JZ)">V161 (6 speed manual 2JZ)</option>
	<option value="R154 (5 Speed manual 7M-GTE)" selected="selected">R154 (5 Speed manual 7M-GTE)</option>
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
<textarea name="mods" rows="12" cols="40">No known modifications.</textarea><BR>
History:<BR>
<textarea name="history" rows="12" cols="40">No known car history.</textarea><BR>

<?php csrf_token_field(); ?>

   <input type="submit" value="Supra!"/>
</form>


<?php
	}
	else
	{
		echo "Access denied. If you're not an admin, you're not supposed to be here. Please use proper login methods.";
	}
}

?>
</div>








<!--


mysql_select_db("my_db", $con);

mysql_query("INSERT INTO Persons (FirstName, LastName, Age) VALUES ('Peter', 'Griffin', '35')");

mysql_query("INSERT INTO Persons (FirstName, LastName, Age) VALUES ('Glenn', 'Quagmire', '33')");

mysql_close($con);
?>

--?


<!-- alle velden:

License
Owner_display
Choise_Model
Choise_Engine
Choise_Transmission
Build_date
Registration_date
Milage
Choise_Status
VIN_Number
VIN_Modelcode
VIN_Colorcode
MA
Mods
History
RECNO

INSERT INTO `16915snldb`.`SNLDB` (
`License` ,
`Owner_display` ,
`Choise_Model` ,
`Choise_Engine` ,
`Choise_Transmission` ,
`Build_date` ,
`Registration_date` ,
`Milage` ,
`Choise_Status` ,
`VIN_Number` ,
`VIN_Modelcode` ,
`VIN_Colorcode` ,
`MA` ,
`Mods` ,
`History` ,
`RECNO`
)
VALUES (
'tt-tt-11', 'Jos @ Shoarmateam.nl', 'MA-70 (MKIII)', '7M-GTE', 'A340E (4 Speed Auto 7M)', '89-06', '16-4', '94.000', 'Running', '', '', 'Grijs', 'a', 'test123 en zo.', 'test456 en zo.', NULL
);



update query voorbeeldje:

UPDATE `16915snldb`.`SNLDB` SET `License` = 'circuit' WHERE `SNLDB`.`RECNO` =280 LIMIT 1 ;



-->

