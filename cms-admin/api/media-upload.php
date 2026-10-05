<?php
declare(strict_types=1);

/**
 * AJAX endpoint behind the upload drop zone in the "Select from Media
 * Library" picker modal — lets an editor upload an image without leaving
 * the article form to go to the Media Library page first. Validation and
 * saving are the shared cms_handle_media_upload() (includes/media-helpers.php),
 * the same code the Media Library page's own upload form uses; this file
 * only adds the media_library INSERT and the JSON shape the modal needs.
 *
 * Request:  POST multipart/form-data — media_file, csrf_token (auth.php
 *           enforces the session gate + CSRF for every POST)
 * Response: {success:true, id, file_name, file_path, thumb_url, alt_text,
 *            width, height, size_kb}  |  {success:false, error}
 *
 * Images only (JPG/PNG/WebP/GIF, max 5 MB) — PDFs belong on the Media
 * Library page, not in an image picker.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/schema-guard.php';
require_once dirname(__DIR__) . '/includes/media-helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** @return never */
function cms_media_upload_respond(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cms_media_upload_respond(['success' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    cms_media_ensure_schema($pdo);
} catch (Throwable $e) {
    cms_media_upload_respond(['success' => false, 'error' => 'Media Library schema is not ready: ' . $e->getMessage()], 500);
}

if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
    cms_media_upload_respond(['success' => false, 'error' => 'No file was uploaded.'], 400);
}

$up = cms_handle_media_upload($_FILES['media_file'], true);
if (!$up['ok']) {
    cms_media_upload_respond(['success' => false, 'error' => $up['error']], 422);
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO media_library (
            file_name, file_path, file_type, mime_type, file_size_kb,
            alt_text, caption, is_active, created_at, updated_at
        ) VALUES (
            :file_name, :file_path, :file_type, :mime_type, :file_size_kb,
            NULL, NULL, 1, NOW(), NOW()
        )'
    );
    $insert->execute([
        'file_name' => $up['file_name'],
        'file_path' => $up['file_path'],
        'file_type' => $up['file_type'],
        'mime_type' => $up['mime_type'],
        'file_size_kb' => $up['file_size_kb'],
    ]);
    $newId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    // The bytes are already on disk, just not catalogued — report failure
    // (there is no row/id for the picker to select) but don't delete the file.
    cms_media_upload_respond(['success' => false, 'error' => 'File saved but could not be catalogued: ' . $e->getMessage()], 500);
}

[$w, $h] = cms_media_image_size($up['file_path']);

cms_media_upload_respond([
    'success' => true,
    'id' => $newId,
    'file_name' => $up['file_name'],
    'file_path' => $up['file_path'],
    'thumb_url' => cms_media_thumb_url($up['file_path']),
    'alt_text' => '',
    'width' => $w,
    'height' => $h,
    'size_kb' => $up['file_size_kb'],
]);
