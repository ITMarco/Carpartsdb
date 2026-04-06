<html>
   <head>
      <meta charset="utf-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <meta property="og:site_name" content="Car Parts DB" />
      <meta property="og:type" content="website" />
      <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>" />
      <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>" />
      <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>" />
      <meta property="og:url" content="<?php echo htmlspecialchars($og_url); ?>" />

      <!--  Title displayed in top of the webbrowser -->
      <title>
             <?php include('data/title.data.php'); ?>
      </title>

      <!-- The file with the style sheet -->
      <link href="engine/style.css?v=<?php echo filemtime('engine/style.css'); ?>" rel="stylesheet" type="text/css" />
      <!-- Active theme variables (overrides defaults in style.css) -->
      <?php
      if (!defined('SNLDB_ACCESS')) define('SNLDB_ACCESS', 1);
      include_once 'config.php';
      include_once 'theme_helper.php';
      $_tdb = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
      if (!$_tdb->connect_error) {
          // Load public themes for the picker widget (used in body_bottom)
          $GLOBALS['_public_themes'] = theme_list_public($_tdb);
          $GLOBALS['_user_theme_id'] = 0;
          // Resolve theme: user cookie → site active theme
          $_tcss     = '';
          $cookie_id = isset($_COOKIE['snldb_theme']) ? (int)$_COOKIE['snldb_theme'] : 0;
          if ($cookie_id > 0) {
              $_tcss = theme_get_css_for_user($_tdb, $cookie_id);
              if ($_tcss) $GLOBALS['_user_theme_id'] = $cookie_id;
          }
          if (!$_tcss) $_tcss = theme_get_css($_tdb);
          $_tdb->close();
          if ($_tcss) echo "<style>$_tcss</style>\n";
      }
      ?>
	<!-- Random header background image -->
	<script type="text/javascript">
		var bgimages = [
			"images/header1.jpg",
			"images/header2.jpg",
			"images/header3.jpg",
			"images/header4.jpg",
			"images/header5.jpg",
			"images/header6.jpg"
		];

		document.addEventListener('DOMContentLoaded', function() {
			var h = document.getElementById('core_header');
			if (h) {
				h.style.backgroundImage    = 'url(' + bgimages[Math.floor(Math.random()*bgimages.length)] + ')';
				h.style.backgroundRepeat   = 'no-repeat';
				h.style.backgroundSize     = 'cover';
				h.style.backgroundPosition = 'center center';
			}
		});
	</script>
   </head>




