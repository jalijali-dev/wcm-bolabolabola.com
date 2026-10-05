<?php
declare(strict_types=1);

/**
 * JSON feed for the "Select from Media Library" picker modal
 * (includes/tinymce-media-picker.php). The modal used to render EVERY image
 * into the page at load time (one getimagesize() per file, on every open of
 * the article form) and filter them client-side; it now asks for one page at
 * a time and searches on the server, so it stays fast however big the
 * library gets.
 *
 * Request:  GET q (optional, filename/path), offset (default 0), limit (default 48, max 96)
 * Response: {success, total, offset, has_more, items:[{id, file_name, file_path,
 *            thumb_url, alt_text, width, height, size_kb}]}
 *
 * Auth: auth.php session gate — any logged-in admin role (same tier as the
 * Media Library menu itself).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/schema-guard.php';
require_once dirname(__DIR__) . '/includes/media-helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** @return never */
function cms_media_list_respond(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    cms_media_list_respond(['success' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    cms_media_ensure_schema($pdo);
} catch (Throwable $e) {
    cms_media_list_respond(['success' => false, 'error' => 'Media Library schema is not ready: ' . $e->getMessage()], 500);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q, 'UTF-8') > 100) {
    $q = mb_substr($q, 0, 100, 'UTF-8');
}
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit  = min(96, max(1, (int) ($_GET['limit'] ?? 48)));

$where  = ["m.is_active = 1", "(m.file_type = 'image' OR m.mime_type LIKE 'image/%')"];
$params = [];
if ($q !== '') {
    // Wildcards escaped so "100%" / "a_b" match literally. ATTR_EMULATE_PREPARES
    // is off, so one named placeholder can't repeat in a query: :q1 / :q2.
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(m.file_name LIKE :q1 OR m.file_path LIKE :q2)';
    $params['q1'] = $like;
    $params['q2'] = $like;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM media_library m' . $whereSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT m.id, m.file_name, m.file_path, m.alt_text, m.file_size_kb
         FROM media_library m' . $whereSql . '
         ORDER BY m.id DESC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    cms_media_list_respond(['success' => false, 'error' => 'Could not load media: ' . $e->getMessage()], 500);
}

$items = [];
foreach ($rows as $row) {
    $path = (string) $row['file_path'];
    [$w, $h] = cms_media_image_size($path);

    $sizeKb = $row['file_size_kb'] !== null ? (int) $row['file_size_kb'] : null;
    if ($sizeKb === null) {
        $disk = app_safe_media_disk_path($path, CMS_PROJECT_ROOT);
        if ($disk !== null && is_file($disk)) {
            $sizeKb = (int) ceil(filesize($disk) / 1024);
        }
    }

    $items[] = [
        'id' => (int) $row['id'],
        'file_name' => (string) $row['file_name'],
        'file_path' => $path,
        'thumb_url' => cms_media_thumb_url($path),
        'alt_text' => (string) ($row['alt_text'] ?? ''),
        'width' => $w,
        'height' => $h,
        'size_kb' => $sizeKb,
    ];
}

cms_media_list_respond([
    'success' => true,
    'total' => $total,
    'offset' => $offset,
    'has_more' => $offset + count($items) < $total,
    'items' => $items,
]);
