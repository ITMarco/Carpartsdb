<div class="content-box">
       <h3>Voeg een nieuwe supra toe.</h3>
     <!--  <img src="images/tumb1.jpg" style="float:left; margin-left:0px;" alt="img" /> -->

       <br><br>

<?php


if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
$passcorrect=false;
	include 'connection.php';
	

    $myLicense=strtoupper($_POST[userLicense]);

	 $query="SELECT * FROM PASSWRDS WHERE carLicense ='$myLicense'";
	 $result=$CarpartsConnection->query($query)or die( "Error in query: \n". mysqli_error());
	 $myrow=$result->fetch_row();
if ($myrow)
{
 echo "Welkom $myrow[4] , uw password is:\r\n";
 if ($myrow[2] == "$_POST[userpassword]") $passcorrect=true;
 if ($myrow[3] == "$_POST[userpassword]") $passcorrect=true;

 if ($passcorrect==true)
   {
     echo "correct";
   } else
   {
     echo "incorrect";
   }
}
 else
{
echo "User not found.";
}

 //$num=mysqli_num_rows($result);
	mysqli_close($CarpartsConnection);

} else
{
//echo "insert new code here";

?>

<form name="secure" id="secure" action="<?php echo $PHP_SELF;?>" method="post">
<br>Kenteken:<br>
<input type="text" name="userLicense"/><br>
Password:<BR>
<input type="password" name="userpassword"/><br>
  <input type="submit" value="Supra!"/>
</form>


<?php
}

?>
</div>