<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/schema-guard.php';
require_once dirname(__DIR__) . '/includes/media-helpers.php';

$pageTitle = 'Media Library';
$currentNav = 'media-library';

$selfUrl = 'media-library.php';

/**
 * Auto-migration: idempotent, safe to run on every load. Creates the table
 * on a fresh database, then self-heals older tables (missing columns,
 * file_path still VARCHAR(255) — spec is 500 so long https URLs fit).
 * file_type/caption on older tables are wider than the spec's VARCHAR(20)/
 * VARCHAR(500); we leave them (a superset) and enforce the spec limits in
 * validation below instead of narrowing columns that may already hold data.
 */
$mediaSchemaError = null;
try {
    cms_media_ensure_schema($pdo);
} catch (Throwable $e) {
    $mediaSchemaError = $e->getMessage();
}

const ML_PER_PAGE = 24;
const ML_TYPES = ['image', 'document', 'video', 'other'];

/**
 * Read + validate the list filters (search/type/status/page) from a source
 * array ($_GET, or the "back" query string a POST carries). Unknown
 * type/status values are ignored (treated as "all").
 */
$ml_filters = static function (array $src): array {
    $search = trim((string) ($src['search'] ?? ''));
    if (mb_strlen($search, 'UTF-8') > 100) {
        $search = mb_substr($search, 0, 100, 'UTF-8');
    }
    $type = strtolower(trim((string) ($src['type'] ?? '')));
    $status = strtolower(trim((string) ($src['status'] ?? '')));

    return [
        'search' => $search,
        'type' => in_array($type, ML_TYPES, true) ? $type : '',
        'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
        'page' => max(1, (int) ($src['page'] ?? 1)),
    ];
};

/** Build the list query string from a filters array, omitting empty values. */
$ml_qs = static function (array $f, ?int $page = null): string {
    $q = [];
    if ($f['search'] !== '') { $q['search'] = $f['search']; }
    if ($f['type'] !== '') { $q['type'] = $f['type']; }
    if ($f['status'] !== '') { $q['status'] = $f['status']; }
    $page ??= $f['page'];
    if ($page > 1) { $q['page'] = $page; }

    return http_build_query($q);
};

$ml_redirect = static function (string $message, string $type = 'success', ?string $query = null) use ($selfUrl): void {
    $_SESSION['cms_flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $selfUrl . ($query ? '?' . $query : ''), true, 302);
    exit;
};

$ml_validate = static function (string $fileName, string $filePath, string $fileType, string $mimeType, string $fileSizeRaw, string $altText, string $caption): ?string {
    if ($fileName === '') {
        return 'File name is required.';
    }
    if (mb_strlen($fileName, 'UTF-8') > 255) {
        return 'File name is too long (max 255 characters).';
    }
    if ($filePath === '') {
        return 'File path is required.';
    }
    if (strlen($filePath) > 500) {
        return 'File path is too long (max 500 characters).';
    }
    if (!in_array($fileType, ML_TYPES, true)) {
        return 'File type is required (image, document, video or other).';
    }
    if ($mimeType !== '' && (strlen($mimeType) > 100 || preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', $mimeType) !== 1)) {
        return 'MIME type is not valid (example: image/jpeg).';
    }
    if ($fileSizeRaw !== '' && (preg_match('/^\d{1,10}$/', $fileSizeRaw) !== 1 || (int) $fileSizeRaw > 4294967295)) {
        return 'File size must be a whole number of KB.';
    }
    if (mb_strlen($altText, 'UTF-8') > 255) {
        return 'Alt text is too long (max 255 characters).';
    }
    if (mb_strlen($caption, 'UTF-8') > 500) {
        return 'Caption is too long (max 500 characters).';
    }

    return null;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    // List context (filters + page) a delete was triggered from, so the
    // admin lands back where they were instead of on an unfiltered page 1.
    $backRaw = [];
    parse_str((string) ($_POST['back'] ?? ''), $backRaw);
    $backQuery = $ml_qs($ml_filters($backRaw)) ?: null;

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId <= 0) {
            $ml_redirect('Invalid media file.', 'error', $backQuery);
        }
        $delete = $pdo->prepare('DELETE FROM media_library WHERE id = :id');
        $delete->execute(['id' => $deleteId]);
        if ($delete->rowCount() < 1) {
            $ml_redirect('Media file not found or already deleted.', 'error', $backQuery);
        }
        $ml_redirect('Media file deleted successfully.', 'success', $backQuery);
    }

    if ($action !== 'create' && $action !== 'update') {
        $ml_redirect('Unknown action.', 'error');
    }

    $updateId = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
    if ($action === 'update') {
        $exists = $pdo->prepare('SELECT id FROM media_library WHERE id = :id');
        $exists->execute(['id' => $updateId]);
        if ($updateId <= 0 || $exists->fetchColumn() === false) {
            $ml_redirect('Media file not found.', 'error');
        }
    }
    // Failed create goes back to the empty form; failed update to its own form.
    $errQuery = $action === 'update' ? 'edit=' . $updateId : 'new=1';

    // -------------------------------------------------------------------------
    // File upload (optional — falls back to the manual file_path if omitted).
    // Validation/saving is the shared cms_handle_media_upload(), the same
    // code the picker modal's drop zone uses.
    // -------------------------------------------------------------------------
    $uploadedRelPath  = '';
    $uploadedOrigName = '';
    $uploadedMime     = '';
    $uploadedSizeKb   = 0;
    $uploadedFileType = '';

    if (
        isset($_FILES['media_file']) && is_array($_FILES['media_file'])
        && (int) ($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        $up = cms_handle_media_upload($_FILES['media_file']);
        if (!$up['ok']) {
            $ml_redirect($up['error'], 'error', $errQuery);
        }
        $uploadedRelPath  = $up['file_path'];
        $uploadedOrigName = $up['file_name'];
        $uploadedMime     = $up['mime_type'];
        $uploadedSizeKb   = $up['file_size_kb'];
        $uploadedFileType = $up['file_type'];
    }
    // -------------------------------------------------------------------------

    $fileName    = trim((string) ($_POST['file_name']    ?? ''));
    $filePath    = trim((string) ($_POST['file_path']    ?? ''));
    $fileType    = strtolower(trim((string) ($_POST['file_type'] ?? '')));
    $mimeType    = trim((string) ($_POST['mime_type']    ?? ''));
    $fileSizeRaw = trim((string) ($_POST['file_size_kb'] ?? ''));
    $altText     = trim((string) ($_POST['alt_text']     ?? ''));
    $caption     = trim((string) ($_POST['caption']      ?? ''));
    $isActive    = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

    // A successful upload always wins over the (possibly placeholder) form fields.
    if ($uploadedRelPath !== '') {
        $filePath    = $uploadedRelPath;
        $mimeType    = $uploadedMime;
        $fileSizeRaw = (string) $uploadedSizeKb;
        $fileType    = $uploadedFileType;
        if ($fileName === '') {
            $fileName = $uploadedOrigName !== '' ? $uploadedOrigName : basename($uploadedRelPath);
        }
    }

    // Path rules: either a full https:// URL, or a local path that starts
    // with /uploads/ and contains no ".." (H-3). Anything else is rejected —
    // including plain http://, which would otherwise be mangled into
    // "/http://…" by the leading-slash normalisation below.
    if (preg_match('#^https://#i', $filePath) === 1) {
        if (preg_match('/\s/', $filePath) === 1 || filter_var($filePath, FILTER_VALIDATE_URL) === false) {
            $ml_redirect('Invalid file URL.', 'error', $errQuery);
        }
    } elseif ($filePath !== '') {
        $filePath = '/' . ltrim(str_replace('\\', '/', $filePath), '/');
        if (!app_is_safe_local_media_path($filePath)) {
            $ml_redirect('Invalid file path. Use a full https:// URL, or a local path that starts with /uploads/ and has no "..".', 'error', $errQuery);
        }
    }

    // Auto-fill the name from the path's basename if the admin left it empty.
    if ($fileName === '' && $filePath !== '') {
        $fileName = basename(parse_url($filePath, PHP_URL_PATH) ?: $filePath);
    }

    $validationError = $ml_validate($fileName, $filePath, $fileType, $mimeType, $fileSizeRaw, $altText, $caption);
    if ($validationError !== null) {
        $ml_redirect($validationError, 'error', $errQuery);
    }

    $payload = [
        'file_name' => $fileName,
        'file_path' => $filePath,
        'file_type' => $fileType,
        'mime_type' => $mimeType === '' ? null : $mimeType,
        'file_size_kb' => $fileSizeRaw === '' ? null : (int) $fileSizeRaw,
        'alt_text' => $altText === '' ? null : $altText,
        'caption' => $caption === '' ? null : $caption,
        'is_active' => $isActive,
    ];

    if ($action === 'create') {
        $insert = $pdo->prepare(
            'INSERT INTO media_library (
                file_name, file_path, file_type, mime_type, file_size_kb,
                alt_text, caption, is_active, created_at, updated_at
            ) VALUES (
                :file_name, :file_path, :file_type, :mime_type, :file_size_kb,
                :alt_text, :caption, :is_active, NOW(), NOW()
            )'
        );
        $insert->execute($payload);
        $ml_redirect('Media file created successfully.', 'success', 'edit=' . (int) $pdo->lastInsertId());
    }

    $update = $pdo->prepare(
        'UPDATE media_library
         SET file_name = :file_name,
             file_path = :file_path,
             file_type = :file_type,
             mime_type = :mime_type,
             file_size_kb = :file_size_kb,
             alt_text = :alt_text,
             caption = :caption,
             is_active = :is_active,
             updated_at = NOW()
         WHERE id = :id'
    );
    $update->execute($payload + ['id' => $updateId]);
    $ml_redirect('Media file updated successfully.', 'success', 'edit=' . $updateId);
}

// ---------------------------------------------------------------------------
// GET: decide which view to render. Two separate views, never both:
//   list  → media-library.php            (table + filters + pagination)
//   form  → ?new=1  /  ?edit=ID          (form only)
// An unknown/invalid ?edit= bounces to the list with an error (PRG).
// ---------------------------------------------------------------------------
$isEditView = array_key_exists('edit', $_GET);
$isNewView  = !$isEditView && array_key_exists('new', $_GET);
$isFormView = $isEditView || $isNewView;
$editRow    = null;

if ($isEditView) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        try {
            $editStmt = $pdo->prepare(
                'SELECT id, file_name, file_path, file_type, mime_type, file_size_kb, alt_text, caption, is_active
                 FROM media_library WHERE id = :id LIMIT 1'
            );
            $editStmt->execute(['id' => $editId]);
            $editRow = $editStmt->fetch() ?: null;
        } catch (PDOException $e) {
            $editRow = null;
        }
    }
    if ($editRow === null) {
        $ml_redirect('Media file not found.', 'error');
    }
}

$alerts = [];
if (isset($_SESSION['cms_flash']) && is_array($_SESSION['cms_flash'])) {
    $alerts[] = $_SESSION['cms_flash'];
    unset($_SESSION['cms_flash']);
}

if ($mediaSchemaError !== null) {
    $alerts[] = [
        'type' => 'error',
        'message' => 'Media Library belum bisa dipakai sepenuhnya: skema database belum lengkap dan '
            . 'perbaikan otomatis gagal dijalankan (' . $mediaSchemaError . ').',
    ];
}

// ---- List view data: server-side filter + pagination ----
$filters     = $ml_filters($_GET);
$mediaFiles  = [];
$totalRows   = 0;
$totalPages  = 1;
$currentPage = 1;

if (!$isFormView) {
    $where  = [];
    $params = [];
    if ($filters['search'] !== '') {
        // Escape LIKE wildcards so "100%" or "a_b" are matched literally. PDO runs
        // with ATTR_EMULATE_PREPARES=false, so one named placeholder can't be
        // reused in the same query — two names (:q1/:q2), same value.
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']) . '%';
        $where[] = '(m.file_name LIKE :q1 OR m.file_path LIKE :q2)';
        $params['q1'] = $like;
        $params['q2'] = $like;
    }
    if ($filters['type'] === 'other') {
        $where[] = "(m.file_type IS NULL OR m.file_type = '' OR m.file_type NOT IN ('image','document','video'))";
    } elseif ($filters['type'] !== '') {
        $where[] = 'm.file_type = :type';
        $params['type'] = $filters['type'];
    }
    if ($filters['status'] === 'active') {
        $where[] = 'm.is_active = 1';
    } elseif ($filters['status'] === 'inactive') {
        $where[] = 'm.is_active = 0';
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM media_library m' . $whereSql);
        $countStmt->execute($params);
        $totalRows   = (int) $countStmt->fetchColumn();
        $totalPages  = max(1, (int) ceil($totalRows / ML_PER_PAGE));
        $currentPage = min($filters['page'], $totalPages);

        $listStmt = $pdo->prepare(
            'SELECT m.id, m.file_name, m.file_path, m.file_type, m.mime_type, m.file_size_kb, m.is_active
             FROM media_library m' . $whereSql . '
             ORDER BY m.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $k => $v) {
            $listStmt->bindValue(':' . $k, $v);
        }
        $listStmt->bindValue(':limit', ML_PER_PAGE, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', ($currentPage - 1) * ML_PER_PAGE, PDO::PARAM_INT);
        $listStmt->execute();
        $mediaFiles = $listStmt->fetchAll();
    } catch (PDOException $e) {
        $mediaFiles = [];
        if ($mediaSchemaError === null) {
            $alerts[] = ['type' => 'error', 'message' => 'Gagal memuat daftar media: ' . $e->getMessage()];
        }
    }
    $filters['page'] = $currentPage;
}

$listUrl = static function (int $page) use ($selfUrl, $ml_qs, $filters): string {
    $qs = $ml_qs($filters, $page);

    return $selfUrl . ($qs !== '' ? '?' . $qs : '');
};

$val = static fn (array $row, string $key): string => (string) ($row[$key] ?? '');

// Base URL the browser needs to preview a stored local path (same logic as
// the list thumbnails, via app_asset_preview_url()).
$previewProbe = app_asset_preview_url('/uploads/__probe__');
$previewBase  = str_ends_with($previewProbe, 'uploads/__probe__')
    ? substr($previewProbe, 0, -strlen('uploads/__probe__'))
    : '';

$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => cms_dashboard_href()],
    ['label' => 'Media Library', 'href' => $isFormView ? $selfUrl : ''],
];
if ($isFormView) {
    $breadcrumbs[] = ['label' => $editRow ? 'Edit' : 'New', 'href' => ''];
}

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
require dirname(__DIR__) . '/includes/navbar.php';
require dirname(__DIR__) . '/includes/breadcrumb.php';
require dirname(__DIR__) . '/includes/alerts.php';
?>
<style>
/* ---- path preview (live image from typed path / chosen file) ---- */
.cms-path-upload__preview{display:block;max-width:100%;max-height:100px;margin:6px 0 0;border-radius:8px;object-fit:contain;border:1px solid var(--line)}
.cms-path-upload__preview[hidden]{display:none!important}
/* ---- list row thumbnail ---- */
.ml-thumb{flex-shrink:0;width:38px;height:38px;object-fit:cover;border-radius:6px;border:1px solid var(--line)}
.ml-thumb--ph{display:flex;align-items:center;justify-content:center;font-size:16px;background:var(--accent-soft);border:1px solid var(--line-subtle);border-radius:6px;width:38px;height:38px;flex-shrink:0}
.ml-filecell{display:flex;align-items:center;gap:8px;min-width:0}
.ml-fname{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
/* ---- filter controls bar ---- */
.ml-controls{display:flex;flex-wrap:wrap;gap:8px;padding:10px 14px;border-bottom:1px solid var(--line-subtle);margin:0}
.ml-ctrl-search{flex:1;min-width:120px;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--input-bg);color:var(--text);font-size:13px;font-family:inherit}
.ml-ctrl-select{padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--input-bg);color:var(--text);font-size:13px;font-family:inherit}
/* ---- table layout: fixed + percentage widths. Without this the File
   column absorbs all spare width (columns drift apart) and the Delete
   button falls off the row. min-width 660 (not 700): at ~1000px the panel
   is only ~678px wide, and 700 forced an inner scroll that clipped Delete. ---- */
.ml-table-wrap{overflow-x:auto}
.ml-table{table-layout:fixed;width:100%;min-width:660px}
.ml-col-file   {width:34%}
.ml-col-type   {width:11%}
.ml-col-size   {width:12%}
.ml-col-status {width:11%}
.ml-col-actions{width:212px}
.ml-table td{vertical-align:middle}
.ml-nowrap{white-space:nowrap}
/* three action buttons on ONE line, right-aligned */
.ml-actions{display:flex;flex-wrap:nowrap;align-items:center;justify-content:flex-end;gap:5px}
.ml-actions .inline-form{display:inline-flex;margin:0}
/* ---- pagination (same look as the article list's pg-pagination) ---- */
.ml-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin-top:14px}
.ml-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:8px;border:1px solid var(--line);background:var(--surface-soft);color:var(--text);font-size:13px;font-weight:500;text-decoration:none;font-family:inherit;transition:background .12s,border-color .12s}
a.ml-page-btn:hover{background:var(--navlink-hover-bg);border-color:var(--navlink-active-border)}
.ml-page-btn--active{background:var(--accent);border-color:var(--accent);color:var(--accent-text);cursor:default}
.ml-page-btn--disabled{color:var(--muted);border-color:var(--line-subtle);cursor:default}
.ml-page-ellipsis{padding:0 4px;color:var(--muted);font-size:13px;line-height:34px}
/* ---- form helper text ---- */
.ml-hint{font-size:11px;color:var(--muted);display:block;margin-top:4px;line-height:1.45}
.ml-hint code{background:var(--accent-soft);padding:1px 5px;border-radius:3px;font-size:11px}
</style>
<section class="admin-stack">
    <div class="toolbar">
        <div class="toolbar__left">
            <h2 class="section-title">Media library</h2>
            <p class="section-lead">Central file store — upload file atau masukkan path file.</p>
        </div>
        <div class="toolbar__right">
            <?php if ($isFormView) : ?>
                <a class="admin-btn admin-btn--secondary" href="<?= cms_esc($selfUrl) ?>">Back to List</a>
            <?php else : ?>
                <a class="admin-btn admin-btn--primary" href="<?= cms_esc($selfUrl . '?new=1') ?>">Add Media Path</a>
            <?php endif; ?>
        </div>
    </div>

<?php if (!$isFormView) : ?>
    <div class="panel">
        <div class="panel__head">
            <h3 class="panel__title">Media files</h3>
            <span class="panel__meta"><?= (int) $totalRows ?> file(s)</span>
        </div>

        <!-- Filters: plain GET form, filtered on the server (the table can hold thousands of rows) -->
        <form class="ml-controls" method="get" action="<?= cms_esc($selfUrl) ?>">
            <input type="search" name="search" class="ml-ctrl-search"
                   value="<?= cms_esc($filters['search']) ?>"
                   placeholder="Search media…" autocomplete="off" maxlength="100">
            <select name="type" class="ml-ctrl-select">
                <option value="">All types</option>
                <?php foreach (['image' => 'Image', 'document' => 'Document', 'video' => 'Video', 'other' => 'Other'] as $tKey => $tLabel) : ?>
                    <option value="<?= $tKey ?>"<?= $filters['type'] === $tKey ? ' selected' : '' ?>><?= $tLabel ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="ml-ctrl-select">
                <option value="">All statuses</option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="admin-btn admin-btn--sm admin-btn--secondary">Filter</button>
        </form>

        <div class="table-wrap ml-table-wrap">
            <table class="admin-table ml-table">
                <colgroup>
                    <col class="ml-col-file">
                    <col class="ml-col-type">
                    <col class="ml-col-size">
                    <col class="ml-col-status">
                    <col class="ml-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mediaFiles === []) : ?>
                        <tr><td colspan="5" class="muted">
                            <?= ($filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')
                                ? 'No files match your filters.'
                                : 'No media files yet.' ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($mediaFiles as $row) : ?>
                        <?php
                        $rowId     = (int) $row['id'];
                        $rowType   = strtolower($val($row, 'file_type'));
                        $rowMime   = strtolower($val($row, 'mime_type'));
                        $rowFPath  = $val($row, 'file_path');
                        $isImg     = $rowType === 'image' || str_starts_with($rowMime, 'image/');
                        $thumbSrc  = ($isImg && $rowFPath !== '') ? app_asset_preview_url($rowFPath) : '';
                        $isActiveRow = (int) ($row['is_active'] ?? 0) === 1;
                        $badgeKey  = in_array($rowType, ['image', 'document', 'video'], true) ? $rowType : 'other';
                        ?>
                        <tr>
                            <td>
                                <div class="ml-filecell">
                                    <?php if ($thumbSrc !== '') : ?>
                                        <img class="ml-thumb" src="<?= cms_esc($thumbSrc) ?>" alt="" loading="lazy" onerror="this.hidden=true">
                                    <?php else : ?>
                                        <div class="ml-thumb ml-thumb--ph" aria-hidden="true">📄</div>
                                    <?php endif; ?>
                                    <span class="ml-fname" title="<?= cms_esc($val($row, 'file_name')) ?>"><?= cms_esc($val($row, 'file_name')) ?></span>
                                </div>
                            </td>
                            <td><span class="ml-type-badge ml-type-badge--<?= $badgeKey ?>"><?= cms_esc($badgeKey) ?></span></td>
                            <td class="ml-nowrap"><?= $row['file_size_kb'] !== null && $row['file_size_kb'] !== '' ? cms_esc((string) $row['file_size_kb']) . ' KB' : '—' ?></td>
                            <td><span class="pill pill--<?= $isActiveRow ? 'ok' : 'muted' ?>"><?= $isActiveRow ? 'Active' : 'Inactive' ?></span></td>
                            <td class="table-actions">
                                <div class="ml-actions">
                                    <button type="button" class="admin-btn admin-btn--sm admin-btn--ghost ml-copy-btn"
                                            data-path="<?= cms_esc($rowFPath) ?>" title="Copy path to clipboard">Copy</button>
                                    <a class="admin-btn admin-btn--sm admin-btn--secondary" href="<?= cms_esc($selfUrl) ?>?edit=<?= $rowId ?>">Edit</a>
                                    <form class="inline-form" method="post" action="<?= cms_esc($selfUrl) ?>"
                                          onsubmit="return confirm('Delete this media file?');">
                                        <?= cms_csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $rowId ?>">
                                        <input type="hidden" name="back" value="<?= cms_esc($ml_qs($filters)) ?>">
                                        <button type="submit" class="admin-btn admin-btn--sm admin-btn--danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1) : ?>
        <?php
        // Always show page 1, the last page, and ±2 around the current page;
        // any gap in between collapses into "…".
        $shown = [1, $totalPages];
        for ($i = $currentPage - 2; $i <= $currentPage + 2; $i++) {
            if ($i >= 1 && $i <= $totalPages) { $shown[] = $i; }
        }
        $shown = array_values(array_unique($shown));
        sort($shown);
        ?>
        <nav class="ml-pagination" aria-label="Media library pages">
            <?php if ($currentPage > 1) : ?>
                <a class="ml-page-btn" href="<?= cms_esc($listUrl($currentPage - 1)) ?>">« Prev</a>
            <?php else : ?>
                <span class="ml-page-btn ml-page-btn--disabled">« Prev</span>
            <?php endif; ?>

            <?php $prevShown = 0; foreach ($shown as $pg) : ?>
                <?php if ($prevShown !== 0 && $pg - $prevShown > 1) : ?><span class="ml-page-ellipsis">…</span><?php endif; ?>
                <?php if ($pg === $currentPage) : ?>
                    <span class="ml-page-btn ml-page-btn--active" aria-current="page"><?= $pg ?></span>
                <?php else : ?>
                    <a class="ml-page-btn" href="<?= cms_esc($listUrl($pg)) ?>"><?= $pg ?></a>
                <?php endif; ?>
                <?php $prevShown = $pg; ?>
            <?php endforeach; ?>

            <?php if ($currentPage < $totalPages) : ?>
                <a class="ml-page-btn" href="<?= cms_esc($listUrl($currentPage + 1)) ?>">Next »</a>
            <?php else : ?>
                <span class="ml-page-btn ml-page-btn--disabled">Next »</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else : ?>
    <div class="panel" id="media-form">
        <div class="panel__head">
            <h3 class="panel__title"><?= $editRow ? 'Edit media file' : 'New media file' ?></h3>
        </div>
        <form class="form-stack" method="post" action="<?= cms_esc($selfUrl) ?>" enctype="multipart/form-data">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
            <?php if ($editRow) : ?>
                <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
            <?php endif; ?>
            <?php
            $editFileType = $editRow ? strtolower($val($editRow, 'file_type')) : '';
            if ($editFileType !== '' && !in_array($editFileType, ML_TYPES, true)) {
                $editFileType = 'other';
            }
            ?>

            <label class="field">Upload file
                <input type="file" name="media_file" id="ml-upload-file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
                <small class="ml-hint">
                    Allowed: JPG, PNG, WebP, GIF, PDF · Max 5 MB (images) / 10 MB (PDF).
                    Choosing a file auto-fills the fields below.
                    <?php if ($editRow && $val($editRow, 'file_path') !== '') : ?>
                        Leave empty to keep the current file.
                    <?php endif; ?>
                </small>
            </label>

            <label class="field">File path
                <input type="text" name="file_path" id="ml-file-path" class="cms-path-upload__input"
                       value="<?= cms_esc($editRow ? $val($editRow, 'file_path') : '') ?>"
                       required maxlength="500" placeholder="/uploads/media/YYYY/MM/file.webp" autocomplete="off">
                <small class="ml-hint">
                    Local path starting with <code>/uploads/</code>, or a full <code>https://</code> URL.
                    Example: <code>/uploads/media/2026/10/photo.webp</code>
                </small>
            </label>
            <img class="cms-path-upload__preview" id="ml-path-preview" alt="" hidden>

            <label class="field">File name
                <input type="text" name="file_name" id="ml-file-name"
                       value="<?= cms_esc($editRow ? $val($editRow, 'file_name') : '') ?>"
                       required maxlength="255" placeholder="Auto-filled from path, or enter manually">
                <small class="ml-hint">Auto-filled from the path above. You can edit it.</small>
            </label>

            <label class="field">File type
                <select name="file_type" id="ml-file-type" required>
                    <option value="">— Select type —</option>
                    <?php foreach (ML_TYPES as $tKey) : ?>
                        <option value="<?= $tKey ?>"<?= $editFileType === $tKey ? ' selected' : '' ?>><?= $tKey ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">MIME type
                <input type="text" name="mime_type" id="ml-mime-type" maxlength="100"
                       value="<?= cms_esc($editRow ? $val($editRow, 'mime_type') : '') ?>" placeholder="e.g. image/jpeg">
                <small class="ml-hint">Optional. Helps the media picker recognise image files. Examples: <code>image/jpeg</code>, <code>image/webp</code>, <code>application/pdf</code></small>
            </label>

            <label class="field">File size (KB)
                <input type="number" name="file_size_kb" id="ml-file-size" min="0" step="1"
                       value="<?= cms_esc($editRow && $editRow['file_size_kb'] !== null ? (string) $editRow['file_size_kb'] : '') ?>"
                       placeholder="e.g. 245">
                <small class="ml-hint">Optional. Filled automatically when you upload a file.</small>
            </label>

            <label class="field">Alt text
                <input type="text" name="alt_text" maxlength="255" value="<?= cms_esc($editRow ? $val($editRow, 'alt_text') : '') ?>">
            </label>
            <label class="field">Caption
                <input type="text" name="caption" maxlength="500" value="<?= cms_esc($editRow ? $val($editRow, 'caption') : '') ?>">
            </label>
            <label class="field">Status
                <select name="is_active" required>
                    <option value="1"<?= !$editRow || (int) ($editRow['is_active'] ?? 0) === 1 ? ' selected' : '' ?>>Active</option>
                    <option value="0"<?= $editRow && (int) ($editRow['is_active'] ?? 0) === 0 ? ' selected' : '' ?>>Inactive</option>
                </select>
            </label>
            <button type="submit" class="admin-btn admin-btn--primary"><?= $editRow ? 'Save changes' : 'Create media file' ?></button>
        </form>
    </div>
<?php endif; ?>
</section>
<script>
<?php if (!$isFormView) : ?>
// ---- Copy Path button ----
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ml-copy-btn');
    if (!btn) return;
    var path = btn.getAttribute('data-path') || '';
    if (!path) return;
    var orig = btn.textContent;
    function flash() {
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = orig; }, 1800);
    }
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        navigator.clipboard.writeText(path).then(flash, fallback);
    } else {
        fallback();
    }
    // Fallback for non-HTTPS contexts / older browsers.
    function fallback() {
        var ta = document.createElement('textarea');
        ta.value = path;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.select();
        try { if (document.execCommand('copy')) { flash(); } } catch (_) {}
        document.body.removeChild(ta);
    }
});
<?php else : ?>
(function () {
    var PREVIEW_BASE = <?= json_encode($previewBase, JSON_UNESCAPED_SLASHES) ?>;
    var pathInput   = document.getElementById('ml-file-path');
    var nameInput   = document.getElementById('ml-file-name');
    var typeInput   = document.getElementById('ml-file-type');
    var mimeInput   = document.getElementById('ml-mime-type');
    var sizeInput   = document.getElementById('ml-file-size');
    var pathPreview = document.getElementById('ml-path-preview');
    var uploadInput = document.getElementById('ml-upload-file');
    var objectUrl   = null;

    // Same resolution rule as app_asset_preview_url() on the server.
    function previewUrl(path) {
        path = (path || '').trim();
        if (!path) return '';
        if (/^https:\/\//i.test(path)) return path;
        return PREVIEW_BASE + path.replace(/^\/+/, '');
    }
    function showPreview(url) {
        if (!url) { pathPreview.hidden = true; pathPreview.removeAttribute('src'); return; }
        pathPreview.onerror = function () { pathPreview.hidden = true; };
        pathPreview.onload  = function () { pathPreview.hidden = false; };
        pathPreview.src = url;
    }
    function syncPreview() { showPreview(previewUrl(pathInput.value)); }

    pathInput.addEventListener('input', function () {
        syncPreview();
        // Auto-fill the name from the path's basename while it is still empty.
        if (nameInput.value.trim() === '') {
            var base = pathInput.value.trim().split('?')[0].replace(/\\/g, '/').split('/').pop() || '';
            if (base) { nameInput.value = base; }
        }
    });
    syncPreview();

    // Choosing a file fills name/type/MIME/size from the file itself; the
    // server re-detects all of it from the real bytes and ignores these
    // values, they're only here so the form looks right before saving.
    var pathBeforeUpload = null;
    uploadInput.addEventListener('change', function () {
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        var file = uploadInput.files && uploadInput.files[0];

        if (!file) {                       // selection cancelled: restore the path
            if (pathBeforeUpload !== null) { pathInput.value = pathBeforeUpload; pathBeforeUpload = null; }
            syncPreview();
            return;
        }
        if (pathBeforeUpload === null) { pathBeforeUpload = pathInput.value; }

        var now = new Date();
        pathInput.value = '/uploads/media/' + now.getFullYear() + '/' + String(now.getMonth() + 1).padStart(2, '0') + '/(random name assigned on save)';

        if (nameInput.value.trim() === '') { nameInput.value = file.name; }
        var mime = file.type || '';
        mimeInput.value = mime;
        typeInput.value = mime.indexOf('image/') === 0 ? 'image' : (mime === 'application/pdf' ? 'document' : '');
        if (file.size) { sizeInput.value = Math.ceil(file.size / 1024); }

        if (mime.indexOf('image/') === 0) {
            objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl);
        } else {
            showPreview('');
        }
    });
})();
<?php endif; ?>
</script>
<?php
require dirname(__DIR__) . '/includes/footer.php';
