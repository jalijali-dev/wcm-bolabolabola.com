<?php
declare(strict_types=1);

/**
 * Shared Media Library helpers — used by pages/media-library.php (upload
 * form) and api/media-upload.php + api/media-list.php (the "Select from
 * Media Library" picker modal), so there is exactly one copy of the schema
 * definition and of the upload validation/saving rules.
 *
 * Lives in includes/ (tracked) rather than config/app.php, which is
 * git-ignored and therefore not deployed with the code.
 */

/**
 * Idempotent: create media_library on a fresh database, then self-heal
 * older tables (missing columns; file_path still VARCHAR(255) — 500 so long
 * https URLs fit). Safe to call on every request. file_type/caption on
 * older tables are wider than the VARCHAR(20)/VARCHAR(500) used for new
 * tables; they're left alone (a superset) rather than narrowed over data
 * that may already exist, and validated to the narrower limits in code.
 */
function cms_media_ensure_schema(PDO $pdo): void
{
    cms_ensure_table($pdo, 'media_library', '
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_type` VARCHAR(20) DEFAULT NULL,
        `mime_type` VARCHAR(100) DEFAULT NULL,
        `file_size_kb` INT UNSIGNED DEFAULT NULL,
        `alt_text` VARCHAR(255) DEFAULT NULL,
        `caption` VARCHAR(500) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ');
    cms_ensure_column($pdo, 'media_library', 'mime_type', 'VARCHAR(100) DEFAULT NULL AFTER `file_type`');
    cms_ensure_column($pdo, 'media_library', 'file_size_kb', 'INT(10) UNSIGNED DEFAULT NULL AFTER `mime_type`');
    cms_ensure_column($pdo, 'media_library', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `file_size_kb`');
    cms_ensure_column($pdo, 'media_library', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
    cms_widen_column($pdo, 'media_library', 'file_path', 'VARCHAR(500) NOT NULL');
}

/**
 * Validate + store one uploaded file under uploads/media/YYYY/MM/.
 *
 * Rules: extension whitelist (jpg/jpeg/png/webp/gif/pdf); real MIME sniffed
 * with finfo from the file bytes (the client-sent type/extension are never
 * trusted); 5 MB limit for images, 10 MB for PDF; random on-disk filename
 * (the client's name is only kept as a display label); index.php 403 guard
 * written into every folder level so directory listing stays closed.
 *
 * $imagesOnly = true rejects PDFs (the image picker modal).
 *
 * @param array $file one $_FILES entry
 * @return array{ok:bool, error?:string, file_name?:string, file_path?:string,
 *               file_type?:string, mime_type?:string, file_size_kb?:int}
 */
function cms_handle_media_upload(array $file, bool $imagesOnly = false): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'error' => $message];

    $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        // The app's own limits (5 MB image / 10 MB PDF) can be lower than what
        // the server allows, but never higher: if PHP's upload_max_filesize is
        // smaller, the file is cut off before our checks run.
        return $fail(match ($uploadErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is larger than the server upload limit ('
                . ini_get('upload_max_filesize') . '). Use a smaller file, or ask the host to raise upload_max_filesize / post_max_size.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Server could not store the upload (error code ' . $uploadErr . ').',
            default => 'File upload failed (error code ' . $uploadErr . ').',
        });
    }

    $tmpName   = (string) ($file['tmp_name'] ?? '');
    $origName  = (string) ($file['name'] ?? '');
    $fileBytes = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return $fail('Invalid upload.');
    }
    if ($fileBytes <= 0) {
        return $fail('Uploaded file is empty.');
    }

    // Step 1: fast extension check (before reading the file).
    $allowedExts = $imagesOnly ? ['jpg', 'jpeg', 'png', 'webp', 'gif'] : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
    $clientExt   = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($clientExt === '' || !in_array($clientExt, $allowedExts, true)) {
        return $fail($imagesOnly
            ? 'Unsupported format. Use JPG, PNG, WebP or GIF.'
            : 'Disallowed file extension.');
    }

    // Step 2: real MIME from the bytes. The map also gives the canonical
    // saved extension — never taken from the client filename.
    $mimeExtMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
    ];
    $finfo        = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string) ($finfo->file($tmpName) ?: '');
    if ($detectedMime === '' || !array_key_exists($detectedMime, $mimeExtMap)
        || ($imagesOnly && !str_starts_with($detectedMime, 'image/'))) {
        return $fail('Disallowed file type (' . ($detectedMime !== '' ? $detectedMime : 'unknown') . ').');
    }

    // Step 3: per-type size limit.
    $isPdf    = $detectedMime === 'application/pdf';
    $maxBytes = $isPdf ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
    if ($fileBytes > $maxBytes) {
        return $fail('File exceeds the ' . ($isPdf ? '10 MB' : '5 MB') . ' limit for this file type.');
    }

    // Step 4: folders + 403 guards at every level.
    $projectRoot = CMS_PROJECT_ROOT;
    $relBase     = 'uploads/media';
    $relYear     = $relBase . '/' . date('Y');
    $relDir      = $relYear . '/' . date('m');
    $diskDir     = $projectRoot . '/' . $relDir;

    if (!is_dir($diskDir) && !mkdir($diskDir, 0755, true) && !is_dir($diskDir)) {
        return $fail('Upload directory could not be created.');
    }
    $guardContent = "<?php\ndeclare(strict_types=1);\n\nhttp_response_code(403);\nexit('Forbidden');\n";
    foreach ([$relBase, $relYear, $relDir] as $guardLevel) {
        $guardFile = $projectRoot . '/' . $guardLevel . '/index.php';
        if (!file_exists($guardFile)) {
            file_put_contents($guardFile, $guardContent);
            @chmod($guardFile, 0644);
        }
    }

    // Step 5: random filename, then move.
    do {
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $mimeExtMap[$detectedMime];
        $targetPath   = $diskDir . '/' . $safeFilename;
    } while (file_exists($targetPath));

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return $fail('Could not save the uploaded file.');
    }
    @chmod($targetPath, 0644);

    // Display label only (never used on disk): client's filename minus any
    // path and control characters.
    $displayName = mb_substr(
        (string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $origName))),
        0,
        255,
        'UTF-8'
    );

    return [
        'ok' => true,
        'file_name' => $displayName !== '' ? $displayName : $safeFilename,
        'file_path' => '/' . $relDir . '/' . $safeFilename,
        'file_type' => $isPdf ? 'document' : 'image',
        'mime_type' => $detectedMime,
        'file_size_kb' => (int) ceil($fileBytes / 1024),
    ];
}

/**
 * Real pixel dimensions of a stored local image (0,0 when unknown: external
 * URL, missing file, or a path that doesn't safely resolve inside uploads/).
 *
 * @return array{0:int,1:int}
 */
function cms_media_image_size(string $webPath): array
{
    $diskPath = app_safe_media_disk_path($webPath, CMS_PROJECT_ROOT);
    if ($diskPath !== null && is_file($diskPath)) {
        $dim = @getimagesize($diskPath);
        if (is_array($dim) && $dim[0] > 0 && $dim[1] > 0) {
            return [(int) $dim[0], (int) $dim[1]];
        }
    }

    return [0, 0];
}

/**
 * Browser URL for a stored media path, as seen from the admin PAGES that
 * embed the picker (pages/pages.php, pages/ads.php).
 *
 * app_asset_preview_url() builds relative URLs from the folder of the script
 * that calls it — "../../uploads/…" from pages/, but only "../uploads/…" from
 * api/ — so the same path resolved inside api/media-list.php would be one
 * level short and every thumbnail would 404. Absolute https URLs (external
 * images, or the public domain on a split deployment) pass through unchanged.
 */
function cms_media_thumb_url(string $webPath): string
{
    $url = app_asset_preview_url($webPath);
    if (!cms_is_pages_subdirectory() && str_starts_with($url, '../') && !str_starts_with($url, '../../')) {
        $url = '../' . $url;
    }

    return $url;
}
