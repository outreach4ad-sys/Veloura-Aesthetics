<?php
/**
 * Veloura Tec — Secure media uploads.
 *
 * Threat model, and how each part is closed:
 *
 *   Executable upload   Extension whitelist AND finfo MIME check AND, for
 *                       images, getimagesize(). Images are re-encoded
 *                       through GD, so a polyglot file that survives the
 *                       checks loses its payload anyway. uploads/.htaccess
 *                       disables interpreters as the final backstop.
 *   Path traversal      The client filename is DISCARDED. Names are
 *                       generated from random_bytes(). Deletion resolves
 *                       realpath() and refuses anything outside uploads/.
 *   Directory escape    Destination folders come from a fixed whitelist.
 *   Resource exhaustion Per-type byte limits, plus a pixel-count ceiling
 *                       before GD ever allocates a canvas.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Folders an upload may target. Nothing else is accepted. */
const UPLOAD_FOLDERS = ['products', 'categories', 'hero'];

/** Refuse absurd dimensions before GD allocates memory for them. */
const UPLOAD_MAX_PIXELS = 50_000_000;   // 50 MP

/** Images wider than this are downscaled on the way in. */
const UPLOAD_MAX_WIDTH = 1800;

/**
 * Thrown for every rejected upload. The message is safe to show a user.
 */
class UploadException extends RuntimeException
{
}

/**
 * Was a file actually chosen for this field?
 */
function upload_present(string $field): bool
{
    return isset($_FILES[$field])
        && is_array($_FILES[$field])
        && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        && ($_FILES[$field]['name'] ?? '') !== '';
}

/**
 * Translate a PHP upload error code into something a person can act on.
 */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'The file is larger than the server allows. Reduce its size and try again.',
        UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE   => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
            'The server could not write the file. Check folder permissions.',
        UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server.',
        default              => 'The file could not be uploaded.',
    };
}

/**
 * Generate an unpredictable filename. The client's filename never reaches
 * the filesystem — it is used for nothing but reading the extension.
 */
function upload_safe_name(string $extension): string
{
    return date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
}

/**
 * The lowercase extension of a client filename, with no path component.
 */
function upload_extension(string $clientName): string
{
    $clientName = basename(str_replace('\\', '/', $clientName));
    $extension  = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

    // jpeg and jpg are the same format; normalise so one canonical
    // extension lands on disk.
    return $extension === 'jpeg' ? 'jpg' : $extension;
}

/**
 * Absolute path of an upload folder, created on demand.
 *
 * @throws UploadException on a folder outside the whitelist
 */
function upload_dir(string $folder): string
{
    if (!in_array($folder, UPLOAD_FOLDERS, true)) {
        throw new UploadException('Invalid upload destination.');
    }

    $path = VELOURA_ROOT . '/' . trim((string) config('upload_dir', 'uploads'), '/') . '/' . $folder;

    if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
        throw new UploadException('The upload folder could not be created.');
    }

    return $path;
}

/**
 * Validate and store an uploaded IMAGE.
 *
 * @param string $field  the name="" of the file input
 * @param string $folder one of UPLOAD_FOLDERS
 * @return string        the stored path relative to the project root,
 *                       e.g. "uploads/products/20260915-a1b2….jpg"
 * @throws UploadException with a message safe to display
 */
function upload_image(string $field, string $folder): string
{
    $file = $_FILES[$field] ?? null;

    if (!is_array($file)) {
        throw new UploadException('No file was received.');
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new UploadException(upload_error_message($error));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');

    // Rejects anything that did not arrive through an HTTP upload — the
    // single most important check in this file.
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new UploadException('The file could not be verified.');
    }

    $maxBytes = (int) config('max_image_bytes', 4 * 1024 * 1024);
    $size     = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        throw new UploadException('The file is empty.');
    }
    if ($size > $maxBytes) {
        throw new UploadException(sprintf(
            'Images must be %d MB or smaller.',
            (int) round($maxBytes / 1048576)
        ));
    }

    // 1. Extension whitelist.
    $extension = upload_extension((string) ($file['name'] ?? ''));
    $allowedExt = (array) config('allowed_image_ext', ['jpg', 'jpeg', 'png', 'webp']);

    if (!in_array($extension, array_map('strtolower', $allowedExt), true)) {
        throw new UploadException('Allowed image types: ' . implode(', ', $allowedExt) . '.');
    }

    // 2. Real MIME type from file content, not from the client's header.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    $allowedMime = (array) config('allowed_image_mime', ['image/jpeg', 'image/png', 'image/webp']);

    if (!in_array($mime, $allowedMime, true)) {
        throw new UploadException('That file is not a valid image.');
    }

    // 3. It must genuinely decode as an image, and not be a pixel bomb.
    $info = @getimagesize($tmp);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        throw new UploadException('That file is not a valid image.');
    }
    if (($info[0] * $info[1]) > UPLOAD_MAX_PIXELS) {
        throw new UploadException('The image dimensions are too large.');
    }

    // 4. The declared extension must agree with the real type, so a .png
    //    that is really a JPEG cannot be used to confuse anything later.
    $expected = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if ($expected !== null && $expected !== $extension) {
        $extension = $expected;
    }

    $directory = upload_dir($folder);
    $filename  = upload_safe_name($extension);
    $target    = $directory . '/' . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new UploadException('The file could not be saved.');
    }

    @chmod($target, 0644);

    // 5. Re-encode: downscales oversized images, strips EXIF, and destroys
    //    any non-image payload smuggled inside a valid image container.
    upload_optimize_image($target, $mime);

    return 'uploads/' . $folder . '/' . $filename;
}

/**
 * Downscale and re-encode an image in place. Failure is non-fatal: the
 * validated original stays, it simply is not optimised.
 */
function upload_optimize_image(string $path, string $mime): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;   // GD unavailable on this host
    }

    $info = @getimagesize($path);
    if ($info === false) {
        return;
    }

    [$width, $height] = $info;

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default      => false,
    };

    if (!$source) {
        return;
    }

    try {
        $scale     = $width > UPLOAD_MAX_WIDTH ? UPLOAD_MAX_WIDTH / $width : 1.0;
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and WebP.
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $path, 82),
            'image/png'  => imagepng($canvas, $path, 7),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $path, 82) : false,
            default      => false,
        };

        imagedestroy($canvas);
    } catch (Throwable $e) {
        error_log('[Veloura] Image optimisation failed: ' . $e->getMessage());
    } finally {
        imagedestroy($source);
    }
}

/**
 * Validate and store an uploaded VIDEO (mp4 / webm).
 *
 * Videos cannot be re-encoded without ffmpeg, so they lean on the
 * extension whitelist, the content-sniffed MIME type and the execution
 * ban in uploads/.htaccess.
 *
 * @return string path relative to the project root
 * @throws UploadException
 */
function upload_video(string $field, string $folder = 'products'): string
{
    $file = $_FILES[$field] ?? null;

    if (!is_array($file)) {
        throw new UploadException('No file was received.');
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new UploadException(upload_error_message($error));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new UploadException('The file could not be verified.');
    }

    $maxBytes = (int) config('max_video_bytes', 32 * 1024 * 1024);
    $size     = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        throw new UploadException('The file is empty.');
    }
    if ($size > $maxBytes) {
        throw new UploadException(sprintf(
            'Videos must be %d MB or smaller. For longer videos, host on YouTube and paste the link.',
            (int) round($maxBytes / 1048576)
        ));
    }

    $extension  = upload_extension((string) ($file['name'] ?? ''));
    $allowedExt = (array) config('allowed_video_ext', ['mp4', 'webm']);

    if (!in_array($extension, array_map('strtolower', $allowedExt), true)) {
        throw new UploadException('Allowed video types: ' . implode(', ', $allowedExt) . '.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    $allowedMime = (array) config('allowed_video_mime', ['video/mp4', 'video/webm']);

    if (!in_array($mime, $allowedMime, true)) {
        throw new UploadException('That file is not a valid MP4 or WebM video.');
    }

    $directory = upload_dir($folder);
    $filename  = upload_safe_name($extension);
    $target    = $directory . '/' . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new UploadException('The file could not be saved.');
    }

    @chmod($target, 0644);

    return 'uploads/' . $folder . '/' . $filename;
}

/**
 * Delete a stored upload.
 *
 * The path is resolved with realpath() and compared against the uploads
 * root, so "../../app/config.php" — or a symlink pointing there — is
 * refused. Returns true only when a file was actually removed.
 */
function upload_delete(?string $relativePath): bool
{
    $relativePath = trim((string) $relativePath);

    if ($relativePath === '') {
        return false;
    }

    $uploadRoot = realpath(VELOURA_ROOT . '/' . trim((string) config('upload_dir', 'uploads'), '/'));
    if ($uploadRoot === false) {
        return false;
    }

    $candidate = realpath(VELOURA_ROOT . '/' . ltrim($relativePath, '/'));
    if ($candidate === false || !is_file($candidate)) {
        return false;
    }

    // Containment check — the resolved file must sit inside uploads/.
    if (!str_starts_with($candidate, $uploadRoot . DIRECTORY_SEPARATOR)) {
        error_log('[Veloura] Refused delete outside uploads: ' . $relativePath);

        return false;
    }

    return @unlink($candidate);
}

/**
 * Handle an optional image field on a form submit.
 *
 * Returns the new path when a file was uploaded, or $current when the
 * field was left empty. When a replacement is uploaded the previous file
 * is deleted so orphans do not accumulate.
 *
 * @throws UploadException
 */
function upload_replace_image(string $field, string $folder, ?string $current): ?string
{
    if (!upload_present($field)) {
        return $current;
    }

    $path = upload_image($field, $folder);

    if ($current !== null && $current !== '' && $current !== $path) {
        upload_delete($current);
    }

    return $path;
}

/**
 * Normalise PHP's awkward multi-file $_FILES shape into one array per file.
 *
 * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function upload_files_list(string $field): array
{
    $input = $_FILES[$field] ?? null;

    if (!is_array($input) || !isset($input['name'])) {
        return [];
    }

    if (!is_array($input['name'])) {
        return [$input];
    }

    $files = [];
    foreach (array_keys($input['name']) as $index) {
        if ((int) $input['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name'     => (string) $input['name'][$index],
            'type'     => (string) $input['type'][$index],
            'tmp_name' => (string) $input['tmp_name'][$index],
            'error'    => (int) $input['error'][$index],
            'size'     => (int) $input['size'][$index],
        ];
    }

    return $files;
}

/**
 * Store every file from a multiple="multiple" image input.
 *
 * One bad file does not discard the good ones: successes are returned and
 * failures are collected as messages for the caller to surface.
 *
 * @return array{paths:array<int,string>, errors:array<int,string>}
 */
function upload_image_multiple(string $field, string $folder, int $limit = 12): array
{
    $files  = upload_files_list($field);
    $paths  = [];
    $errors = [];

    foreach (array_slice($files, 0, $limit) as $index => $file) {
        // upload_image() reads from $_FILES, so present one file at a time
        // under a scratch key.
        $scratch = '__veloura_single';
        $_FILES[$scratch] = $file;

        try {
            $paths[] = upload_image($scratch, $folder);
        } catch (UploadException $e) {
            $errors[] = sprintf('%s: %s', basename($file['name']), $e->getMessage());
        } finally {
            unset($_FILES[$scratch]);
        }
    }

    if (count($files) > $limit) {
        $errors[] = sprintf('Only the first %d images were uploaded.', $limit);
    }

    return ['paths' => $paths, 'errors' => $errors];
}

/**
 * Human-readable file size for admin listings.
 */
function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}
