<?php
if (!defined('CARPARTS_ACCESS')) die('Direct access not permitted.');

/**
 * Resize and compress an uploaded image to fit within $max_w x $max_h
 * and stay under $max_bytes. Always saves as WebP.
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

    if (!function_exists('imagewebp')) {
        imagedestroy($dst);
        return false;
    }

    // Always save as WebP — start at quality 82, reduce until under max_bytes
    $quality = 82;
    do {
        imagewebp($dst, $target_path, $quality);
        $quality -= 5;
    } while (file_exists($target_path) && filesize($target_path) > $max_bytes && $quality >= 40);

    imagedestroy($dst);
    return file_exists($target_path);
}
