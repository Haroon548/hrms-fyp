<?php
/*
 * Photo uploads from the admin panel (FR-ROOM-07, FR-ROOM-08; later the menu and site photos).
 *
 * Every upload is opened as an image and saved again as a new JPEG:
 *   - anything that isn't a real JPG, PNG or WebP photo is refused;
 *   - big photos are shrunk to at most 1920px, so pages stay fast on free hosting (NFR-12);
 *   - the file gets a random name, so nobody can guess or overwrite another photo.
 * Uploaded files live in assets/images/uploads/<folder>/.
 */

const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;  // 10 MB
const UPLOAD_MAX_SIDE = 1920;               // longest side after shrinking, in pixels
const UPLOAD_MAX_PIXELS = 40000000;         // e.g. 8000 × 5000; bigger needs too much memory

/*
 * Saves one uploaded photo ($_FILES['...'] entry, or one item of a multi-upload).
 * Returns ['file' => 'uploads/rooms/....jpg'] (path inside assets/images/) or an error message.
 */
function save_photo_upload(array $upload, $folder)
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Choose a photo to upload.';
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $upload['size'] > UPLOAD_MAX_BYTES) {
        return '"' . $upload['name'] . '" is too big. Photos can be up to 10 MB.';
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return '"' . $upload['name'] . '" could not be uploaded. Please try again.';
    }

    $info = @getimagesize($upload['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng'];
    if (function_exists('imagecreatefromwebp')) {
        $loaders[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    }
    if (!$info || !isset($loaders[$info[2]])) {
        return '"' . $upload['name'] . '" is not a JPG, PNG or WebP photo.';
    }
    [$width, $height] = $info;
    if ($width * $height > UPLOAD_MAX_PIXELS) {
        return '"' . $upload['name'] . '" is too large (' . $width . ' × ' . $height . ' pixels). Make it smaller than 8000 × 5000 and try again.';
    }

    $source = @$loaders[$info[2]]($upload['tmp_name']);
    if (!$source) {
        return '"' . $upload['name'] . '" seems to be damaged and could not be opened.';
    }

    // Phone photos are often stored sideways with a note saying which way is up
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($upload['tmp_name']);
        $turn = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($turn !== 0) {
            $source = imagerotate($source, $turn, 0);
            $width = imagesx($source);
            $height = imagesy($source);
        }
    }

    // Shrink to UPLOAD_MAX_SIDE; a white background replaces see-through parts of PNGs
    $scale = min(1, UPLOAD_MAX_SIDE / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $photo = imagecreatetruecolor($newWidth, $newHeight);
    imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
    imagecopyresampled($photo, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $relative = 'uploads/' . $folder . '/' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.jpg';
    $target = __DIR__ . '/../assets/images/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
        return 'The photo folder could not be created. Check that assets/images/uploads/ can be written to.';
    }
    $saved = imagejpeg($photo, $target, 82);
    imagedestroy($photo);
    imagedestroy($source);

    return $saved ? ['file' => $relative] : 'The photo could not be saved. Check that assets/images/uploads/ can be written to.';
}

// The files of a multi-upload (<input type="file" name="photos[]" multiple>) as separate uploads.
function uploaded_files($field)
{
    $files = [];
    foreach ((array) ($_FILES[$field]['name'] ?? []) as $i => $name) {
        $files[] = [
            'name'     => $name,
            'type'     => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error'    => $_FILES[$field]['error'][$i],
            'size'     => $_FILES[$field]['size'][$i],
        ];
    }
    return array_values(array_filter($files, function ($file) {
        return $file['error'] !== UPLOAD_ERR_NO_FILE;
    }));
}

// Deletes a photo file, but only one uploaded from the admin panel (never the starting photos).
function delete_photo_file($file)
{
    if (strpos($file, 'uploads/') === 0 && strpos($file, '..') === false) {
        $path = __DIR__ . '/../assets/images/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
