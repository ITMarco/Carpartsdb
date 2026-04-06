<?php
	include 'connection.php';

	$per_page = 50;
	$page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

	$count_result = $SNLDBConnection->query("SELECT COUNT(*) FROM SNLDB");
	$total        = $count_result ? (int)$count_result->fetch_row()[0] : 0;
	$total_pages  = max(1, (int)ceil($total / $per_page));
	$page         = min($page, $total_pages);
	$offset       = ($page - 1) * $per_page;

	$result = $SNLDBConnection->query("SELECT License, Owner_display, Choise_Model FROM SNLDB ORDER BY Choise_Model LIMIT $per_page OFFSET $offset");

	if (!$result) {
		error_log("full.php query failed: " . $SNLDBConnection->error);
		echo "<div class='content-box'><h3>Fout</h3><p>Kon de database niet ophalen.</p></div>";
	} else {
		$prev = $page > 1            ? $page - 1 : null;
		$next = $page < $total_pages ? $page + 1 : null;

		$nav = "<div class='content-box'><p>Pagina $page van $total_pages &nbsp;|&nbsp; $total supras totaal</p><p>";
		if ($prev) $nav .= "<a href='index.php?navigate=full&amp;page=$prev'>&#8592; Vorige</a>";
		if ($prev && $next) $nav .= " &nbsp;|&nbsp; ";
		if ($next) $nav .= "<a href='index.php?navigate=full&amp;page=$next'>Volgende &#8594;</a>";
		$nav .= "</p></div>";

		echo $nav;

		$MyModel = '';
		$open    = false;
		while ($row = $result->fetch_assoc()) {
			$License       = $row['License'];
			$Owner_display = $row['Owner_display'];
			$Choise_Model  = $row['Choise_Model'];

			if ($MyModel !== $Choise_Model) {
				if ($open) echo "</div>";
				echo "<div class='content-box'>";
				echo "<h3>" . htmlspecialchars($Choise_Model) . "</h3><br><br>";
				$MyModel = $Choise_Model;
				$open    = true;
			}
			echo "<a href='index.php?navigate=" . urlencode($License) . "'><b>" . htmlspecialchars($License) . "</b></a>: " . htmlspecialchars($Owner_display) . "<BR>";
		}
		if ($open) echo "</div>";

		echo $nav;
	}
	mysqli_close($SNLDBConnection);
?>
