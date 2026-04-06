<div class="content-box">
       <h3>SNLDB Admin panel</h3>
       <img src="images/admin.jpg" style="float:left; margin-left:0px;" alt="img" />

	<A href="index.php?navigate=insertnew">Voeg nieuwe supra toe</a><BR>
<BR>
	<A href="index.php?navigate=adminedit">bewerk een supra</a><BR>

</div>



<div class="content-box">
       <h3>Supra informatie wijzigen.</h3>
     <!--  <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" /> -->

       <br><br>

<?php
// Use session manager for secure authentication
require_once(__DIR__ . '/../session_manager.php');

// Require authentication
require_authentication();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
		// SECURITY: Validate CSRF token
		require_csrf_token();

		include 'connection.php';
		include_once 'car_stats_helper.php';

		// SECURITY: Sanitize all inputs
		$License = isset($_POST['License']) ? trim($_POST['License']) : '';
		$Owner_display = isset($_POST['owner']) ? trim($_POST['owner']) : '';
		$Owner_show    = isset($_POST['owner_show']) ? 1 : 0;
		$Owner_history = isset($_POST['owner_history']) ? trim($_POST['owner_history']) : '';
		$Choise_Model = isset($_POST['mark']) ? $_POST['mark'] : '';
		$Choise_Transmission = isset($_POST['transmission']) ? $_POST['transmission'] : '';
		$Milage = isset($_POST['milage']) ? trim($_POST['milage']) : '';
		$Choise_Status = isset($_POST['status']) ? $_POST['status'] : '';
		$Choise_Engine = isset($_POST['engine']) ? $_POST['engine'] : '';
		$Registration_date = isset($_POST['regdate']) ? trim($_POST['regdate']) : '';
		$Build_date = isset($_POST['bouwjaar']) ? trim($_POST['bouwjaar']) : '';
		$History = isset($_POST['history']) ? htmlspecialchars($_POST['history'], ENT_QUOTES) : '';
		$mods = isset($_POST['mods']) ? htmlspecialchars($_POST['mods'], ENT_QUOTES) : '';
		$ma = isset($_POST['trans']) ? $_POST['trans'] : '';
		$color = isset($_POST['color']) ? trim($_POST['color']) : '';
		$recordnr = isset($_POST['recno']) ? intval($_POST['recno']) : 0;
		$date = date("Y-m-d H:i:s");

		// Auto-preserve old owner name in history if it changed
		$cur = $SNLDBConnection->prepare("SELECT Owner_display, Owner_history FROM SNLDB WHERE RECNO = ?");
		if ($cur) {
		    $cur->bind_param('i', $recordnr);
		    $cur->execute();
		    $cur_row = $cur->get_result()->fetch_assoc();
		    $cur->close();
		    if ($cur_row && $cur_row['Owner_display'] !== '' && $cur_row['Owner_display'] !== $Owner_display) {
		        $entry = date('Y-m-d') . ': ' . $cur_row['Owner_display'];
		        // Only overwrite Owner_history from form if it wasn't manually edited; otherwise merge
		        $base = $Owner_history !== '' ? $Owner_history : ($cur_row['Owner_history'] ?? '');
		        $Owner_history = $base !== '' ? $entry . "\n" . $base : $entry;
		    }
		}

		// SECURITY: Use prepared statement to prevent SQL injection
		$stmt = $SNLDBConnection->prepare("UPDATE SNLDB SET License = ?, Owner_display = ?, Owner_show = ?, Owner_history = ?, Choise_Model = ?, Choise_Engine = ?, Choise_Transmission = ?, Build_date = ?, Registration_date = ?, Milage = ?, Choise_Status = ?, VIN_Colorcode = ?, MA = ?, Mods = ?, History = ?, moddate = ? WHERE RECNO = ?");

		if (!$stmt) {
			error_log("Prepare failed: " . $SNLDBConnection->error);
			die("Database error occurred.");
		}

		// Types: s=License, s=Owner_display, i=Owner_show, s=Owner_history,
		//        s×13 (Model,Engine,Trans,Build,Reg,Milage,Status,Color,MA,Mods,History,date), i=RECNO
$stmt->bind_param("ssisssssssssssssi", $License, $Owner_display, $Owner_show, $Owner_history, $Choise_Model, $Choise_Engine, $Choise_Transmission, $Build_date, $Registration_date, $Milage, $Choise_Status, $color, $ma, $mods, $History, $date, $recordnr);

		if ($stmt->execute()) {
			car_stats_log($SNLDBConnection, $License, 'edit');
			car_changelog_log($SNLDBConnection, $License, 'info');
			echo "Wijzigingen opgeslagen...";
		} else {
			error_log("Execute failed: " . $stmt->error);
			echo "Error updating record.";
		}

		$stmt->close();

	?>
<form name="editcarpicture" id="newcarpicture" action="index.php?navigate=uploadimage" method="post">
  <input type="hidden" name="License" value="<?php echo htmlspecialchars($License); ?>" />
  <input type="submit" value="Plaatje toevoegen!"/>
</form>

	<?php
		mysqli_close($SNLDBConnection);
	}
?>
</div>


<!--
update query voorbeeldje:

UPDATE `16915snldb`.`SNLDB` SET `License` = 'circuit' WHERE `SNLDB`.`RECNO` =280 LIMIT 1 ;

License` ,
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

-->