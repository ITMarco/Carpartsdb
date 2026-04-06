<?php
if (!defined('SNLDBCARPARTS_ACCESS')) die('Direct access not permitted.');

/**
 * Resize and compress an uploaded image to fit within $max_w x $max_h
 * and stay under $max_bytes. Always saves as JPEG.
 * Images smaller than the limits are kept at original dimensions.
 * Returns true on success, false on failure.
 */
function snldb_save_image($tmp_path, $target_path, $max_w = 1920, $max_h = 1280, $max_bytes = 1572864) {
    $info = @getimagesize($tmp_path);
    if (!$info) return false;

    [$orig_w, $orig_h, $type] = $info;

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp_path); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp_path);  break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($tmp_path);  break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmp_path); break;
        default: return false;
    }
    if (!$src) return false;

    // Scale down only — never upscale
    $ratio = min($max_w / $orig_w, $max_h / $orig_h, 1.0);
    $new_w = max(1, intval($orig_w * $ratio));
    $new_h = max(1, intval($orig_h * $ratio));

    $dst = imagecreatetruecolor($new_w, $new_h);
    // White background so PNG transparency becomes white instead of black
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    // Start at quality 85, reduce by 5 per step until under max_bytes or floor of 40
    $quality = 85;
    do {
        imagejpeg($dst, $target_path, $quality);
        $quality -= 5;
    } while (filesize($target_path) > $max_bytes && $quality >= 40);

    // Also save a WebP version alongside the JPEG (quality 82 is a good size/quality balance)
    if (function_exists('imagewebp')) {
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $target_path);
        if ($webp_path !== $target_path) {
            imagewebp($dst, $webp_path, 82);
        }
    }

    imagedestroy($dst);
    return file_exists($target_path);
}
