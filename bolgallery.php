<?php
// BolGallery - modernised with lightbox viewer

// Returns a GD image resource and its dimensions
function getImageResource($imageFile) {
	$info = getimagesize($imageFile);
	$dataArray[1] = $info[0];
	$dataArray[2] = $info[1];
	if ($info[2] == IMAGETYPE_GIF)  { $dataArray[0] = imagecreatefromgif($imageFile); }
	if ($info[2] == IMAGETYPE_JPEG) { $dataArray[0] = imagecreatefromjpeg($imageFile); }
	if ($info[2] == IMAGETYPE_PNG)  { $dataArray[0] = imagecreatefrompng($imageFile); }
	if ($info[2] == IMAGETYPE_WEBP) { $dataArray[0] = imagecreatefromwebp($imageFile); }
	return $dataArray;
}

// Creates a cropped detail thumbnail
function imageDetailExtract($referenceImage, $thumbnail, $thumbnailWidth, $thumbnailHeight, $thumbnailJpegQuality=70) {
	$img = getImageResource($referenceImage);
	$Xposition = random_int(0, max(0, $img[1] - $thumbnailWidth));
	$Yposition = random_int(0, max(0, $img[2] - $thumbnailHeight));
	$thumbRes = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
	imagecopy($thumbRes, $img[0], 0, 0, $Xposition, $Yposition, $img[1], $img[2]);
	imagejpeg($thumbRes, $thumbnail, $thumbnailJpegQuality);
	if (function_exists('imagewebp')) {
		$webpThumb = preg_replace('/\.(?:jpe?g|png|gif)$/i', '.webp', $thumbnail);
		imagewebp($thumbRes, $webpThumb, 82);
	}
	imagedestroy($img[0]);
	imagedestroy($thumbRes);
}

// Creates a resized thumbnail
function resizeImage($referenceImage, $thumbnail, $maxWidth, $maxHeight, $thumbnailJpegQuality=70) {
	$img = getImageResource($referenceImage);
	if ($img[1] > $img[2]) { $maxHeight = round(($img[2]/$img[1])*$maxWidth); }
	else { $maxWidth = round(($img[1]/$img[2])*$maxHeight); }
	$thumbRes = imagecreatetruecolor($maxWidth, $maxHeight);
	imagecopyresized($thumbRes, $img[0], 0, 0, 0, 0, $maxWidth, $maxHeight, $img[1], $img[2]);
	imagejpeg($thumbRes, $thumbnail, $thumbnailJpegQuality);
	if (function_exists('imagewebp')) {
		$webpThumb = preg_replace('/\.(?:jpe?g|png|gif)$/i', '.webp', $thumbnail);
		imagewebp($thumbRes, $webpThumb, 82);
	}
	imagedestroy($img[0]);
	imagedestroy($thumbRes);
}

// Date sorting
function mtime_sort($b, $a) {
	if (filemtime($a) == filemtime($b)) { return 0; }
	return (filemtime($a) < filemtime($b)) ? -1 : 1;
}

// Build gallery HTML with modern lightbox
function bolGalleryCreate($imagesList, $referenceImagesDirectory, $tableColumnsNb, $thumbnailWidth, $thumbnailHeight, $switchClassic=false) {

	$HTML = '';

	// Lightbox overlay (CSS + JS, only output once per page)
	$HTML .= '
<style>
#snl-lightbox {
	display: none; position: fixed; top:0; left:0;
	width:100%; height:100%;
	background: rgba(0,0,0,0.92);
	z-index: 9999;
}
#snl-lightbox.active { display: flex; align-items: center; justify-content: center; }
#snl-lightbox img {
	max-width: 92vw; max-height: 92vh;
	object-fit: contain;
	border: 2px solid #666;
	cursor: default;
	user-select: none;
}
#snl-lb-close {
	position: fixed; top: 12px; right: 20px;
	color: #fff; font-size: 40px; font-weight: bold;
	cursor: pointer; z-index: 10000;
	text-shadow: 0 0 8px #000; line-height: 1;
}
#snl-lb-prev, #snl-lb-next {
	position: fixed; top: 50%; transform: translateY(-50%);
	color: #fff; font-size: 52px;
	cursor: pointer; z-index: 10000;
	padding: 8px 16px;
	text-shadow: 0 0 8px #000; user-select: none;
}
#snl-lb-prev { left: 8px; }
#snl-lb-next { right: 8px; }
#snl-lb-counter {
	position: fixed; bottom: 14px; left: 50%; transform: translateX(-50%);
	color: #ccc; font-size: 14px; z-index: 10000;
}
.snl-thumb-wrap { display: inline-block; margin: 4px; cursor: zoom-in; }
.snl-thumb-wrap img { border: 2px solid #ccc; display: block; transition: border-color 0.2s; }
.snl-thumb-wrap img:hover { border-color: #666; }
</style>

<div id="snl-lightbox">
	<span id="snl-lb-close" title="Sluiten (Esc)">&times;</span>
	<span id="snl-lb-prev" title="Vorige (&#8592;)">&lsaquo;</span>
	<img id="snl-lb-img" src="" alt="" />
	<span id="snl-lb-next" title="Volgende (&#8594;)">&rsaquo;</span>
	<div id="snl-lb-counter"></div>
</div>

<script>
(function(){
	var lb      = document.getElementById("snl-lightbox");
	var lbImg   = document.getElementById("snl-lb-img");
	var lbClose = document.getElementById("snl-lb-close");
	var lbPrev  = document.getElementById("snl-lb-prev");
	var lbNext  = document.getElementById("snl-lb-next");
	var lbCount = document.getElementById("snl-lb-counter");
	var images  = [];
	var current = 0;

	function openLb(idx) {
		current = (idx + images.length) % images.length;
		lbImg.src = images[current].src;
		lbImg.alt = images[current].alt;
		lbCount.textContent = (current + 1) + " / " + images.length;
		lb.classList.add("active");
		document.body.style.overflow = "hidden";
	}
	function closeLb() {
		lb.classList.remove("active");
		lbImg.src = "";
		document.body.style.overflow = "";
	}

	document.addEventListener("DOMContentLoaded", function(){
		var thumbs = document.querySelectorAll(".snl-gallery-thumb");
		thumbs.forEach(function(a, i){
			images.push({ src: a.getAttribute("data-full"), alt: a.getAttribute("data-alt") });
			a.addEventListener("click", function(e){ e.preventDefault(); openLb(i); });
		});
	});

	lb.addEventListener("click",    function(e){ if (e.target === lb) closeLb(); });
	lbClose.addEventListener("click", closeLb);
	lbPrev.addEventListener("click",  function(e){ e.stopPropagation(); openLb(current - 1); });
	lbNext.addEventListener("click",  function(e){ e.stopPropagation(); openLb(current + 1); });

	document.addEventListener("keydown", function(e){
		if (!lb.classList.contains("active")) return;
		if      (e.key === "Escape")      closeLb();
		else if (e.key === "ArrowLeft")   openLb(current - 1);
		else if (e.key === "ArrowRight")  openLb(current + 1);
	});
})();
</script>
';

	// Thumbnail grid
	$HTML .= "<div style='margin:10px 0;'>\n";

	foreach ($imagesList as $currentImage) {
		$referenceImageName = str_replace($referenceImagesDirectory, "", $currentImage);
		$thumbnail = $referenceImagesDirectory . "bolGallery/thumbnail_" . $referenceImageName;

		if (!file_exists($thumbnail)) {
			if ($switchClassic) { resizeImage($currentImage, $thumbnail, $thumbnailWidth, $thumbnailHeight); }
			else { imageDetailExtract($currentImage, $thumbnail, $thumbnailWidth, $thumbnailHeight); }
		}

		$alt      = htmlspecialchars(str_replace("_", " ", substr($referenceImageName, 0, -4)));
		$thumbSrc = htmlspecialchars($thumbnail);

		// Use WebP full image in lightbox if it exists, fall back to original
		$fullWebpPath = preg_replace('/\.(?:jpe?g|png|gif)$/i', '.webp', $currentImage);
		$fullSrc = htmlspecialchars(file_exists($fullWebpPath) ? $fullWebpPath : $currentImage);

		// Use WebP thumbnail if it exists
		$webpThumbPath = preg_replace('/\.(?:jpe?g|png|gif)$/i', '.webp', $thumbnail);
		$hasWebpThumb  = file_exists($webpThumbPath);

		$HTML .= "<a href=\"{$fullSrc}\" class=\"snl-gallery-thumb snl-thumb-wrap\" data-full=\"{$fullSrc}\" data-alt=\"{$alt}\">";
		if ($hasWebpThumb) {
			$webpThumbSrc = htmlspecialchars($webpThumbPath);
			$HTML .= "<picture><source srcset=\"{$webpThumbSrc}\" type=\"image/webp\"><img src=\"{$thumbSrc}\" title=\"{$alt}\" alt=\"{$alt}\" /></picture>";
		} else {
			$HTML .= "<img src=\"{$thumbSrc}\" title=\"{$alt}\" alt=\"{$alt}\" />";
		}
		$HTML .= "</a>\n";
	}

	$HTML .= "</div>\n";
	return $HTML;
}

// Main function — call this from your pages
function bolGallery($referenceImagesDirectory, $tableColumnsNb, $thumbnailWidth, $thumbnailHeight, $switchClassic=false) {

	if (!is_dir("./bolgallerycars")) mkdir("./bolgallerycars", 0755);
	$staticPage = "./bolgallerycars/" . str_replace(".", "", str_replace("/", "", $referenceImagesDirectory)) . "_bolGalleryStaticPage.html";

	// Rebuild if directory is newer than cached page
	if (!file_exists($staticPage) || filemtime($referenceImagesDirectory) > filemtime($staticPage)) {

		if (!is_dir($referenceImagesDirectory)) {
			die("<b>" . htmlspecialchars($referenceImagesDirectory) . "</b> does not exist or is not a valid directory.");
		}

		// Build list of supported image files
		$imagesList = array();
		$handle = opendir($referenceImagesDirectory);
		while ($file = readdir($handle)) {
			if (is_file($referenceImagesDirectory . $file)) {
				$extension = strtolower(substr(strrchr($file, "."), 1));
				if (in_array($extension, array("jpg", "jpeg", "gif", "png")) && $file[0] != "#") {
					$imagesList[] = $referenceImagesDirectory . $file;
				}
			}
		}
		closedir($handle);

		if (empty($imagesList)) {
			echo "<p style='color:#888;'>Geen afbeeldingen gevonden.</p>";
			return;
		}

		// Create thumbnails directory if needed
		if (!is_dir($referenceImagesDirectory . "bolGallery")) {
			mkdir($referenceImagesDirectory . "bolGallery", 0755);
		}

		@usort($imagesList, "mtime_sort");

		$HTML = bolGalleryCreate($imagesList, $referenceImagesDirectory, $tableColumnsNb, $thumbnailWidth, $thumbnailHeight, $switchClassic);

		// Cache to static file
		$session = fopen($staticPage, "w");
		fputs($session, $HTML);
		fclose($session);
	}

	echo file_get_contents($staticPage);
}
?>
