<?php
declare(strict_types=1);

/**
 * includes/site-bootstrap.php — public-frontend bootstrap for Bola Update
 * Indonesia (WCM 3 - Version 1, "Tentakel 3", cabang langsung dari
 * WCM 1 V.1 / olahraga77.com — sejajar dengan WCM 2 V.1 / Biang Olahraga).
 *
 * Mirrors the pattern used in wcm1_version1/includes/site-bootstrap.php:
 * separate from cms-admin entirely, reuses only cms-admin/config/database.php
 * (DB connection) and cms-admin/includes/schema-guard.php — does NOT reuse
 * cms-admin's session/auth/admin UI.
 *
 * di-clone dari WCM 2 V.1 (Biang Olahraga) per Work Order 004 (18 Agu 2026).
 * Niche situs ini: bola & seputar olahraga umum. Kategori final (19 Agu
 * 2026, operator): Liga Indonesia, Liga Eropa, Timnas, Transfer.
 *
 * NOTE (belum final): domain dan struktur permalink masih menunggu
 * konfirmasi operator (lihat docs/HANDOFF.md). Sementara pakai pola
 * /kategori/{slug} dan /artikel/{slug} yang sama dengan wcm1_version1,
 * TAPI di database yang terpisah — ganti kalau operator kasih struktur lain.
 */

require_once dirname(__DIR__) . '/cms-admin/config/database.php';
require_once dirname(__DIR__) . '/cms-admin/includes/schema-guard.php';

/** cms_slugify() polyfill — avoids pulling in all of cms-admin/functions.php. */
if (!function_exists('cms_slugify')) {
    function cms_slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

// ─── Site identity ──────────────────────────────────────────────────────────
define('WPM_SITE_NAME', 'Bola Bola Bola');
define('WPM_SITE_TAGLINE', 'Update Berita Bola & Olahraga Terkini');

/**
 * Site can be deployed either at the domain root (production) or under a
 * subfolder (local dev, e.g. http://localhost:8008/wcm3_version1/). Every
 * internal URL MUST go through wpm_base_url() — see the extended note in
 * wcm1_version1/includes/site-bootstrap.php for why (bit that project on
 * first local test).
 */
if (!defined('WPM_BASE_PATH')) {
    $wpmScriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $wpmScriptDir = str_replace('\\', '/', $wpmScriptDir);
    define('WPM_BASE_PATH', $wpmScriptDir === '/' ? '' : rtrim($wpmScriptDir, '/'));
}

function wpm_base_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return WPM_BASE_PATH . $path;
}

/**
 * The 4 nav categories, in menu order. slug => label. Single source of
 * truth for header/footer nav, the vertical rail, and the category
 * migration below.
 */
function wpm_site_nav_categories(): array
{
    return [
        'sepak-bola'     => 'Sepak Bola',
        'basket'         => 'Basket',
        'voli'           => 'Voli',
        'bursa-transfer' => 'Bursa Transfer',
    ];
}

/**
 * Maps a category slug to one of 4 accent color classes (c1-c4), matching
 * the approved mockup (docs/homepage-mockup-v1.html): c1=merah (Liga
 * Indonesia), c2=navy (Liga Eropa), c3=hijau (Timnas), c4=amber
 * (Transfer). Falls back to c1 for anything unrecognized so a stray/
 * future category never renders unstyled.
 */
function wpm_category_color_class(?string $slug): string
{
    return match ($slug) {
        'basket'         => 'c2',
        'voli'           => 'c3',
        'bursa-transfer' => 'c4',
        default          => 'c1', // sepak-bola + fallback
    };
}

/**
 * One-time-ish idempotent migration: ensure the 4 final categories exist
 * with the canonical labels, and reassign any article sitting in a
 * category outside the final 4 to "Tips" (the closest thing to a catch-all
 * here) before deleting the stale category row. Mirrors
 * wpm_site_migrate_categories() in wcm1_version1 — including the fix for
 * the array_keys()-vs-array_values() bug found there on 12 Agu 2026 (the
 * NOT IN list here is built from array_keys($ids), i.e. slugs, not IDs).
 */
function wpm_site_migrate_categories(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $final = wpm_site_nav_categories();

    try {
        $ids = [];
        foreach ($final as $slug => $label) {
            $stmt = $pdo->prepare('SELECT id FROM article_categories WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $ins = $pdo->prepare('INSERT INTO article_categories (name, slug) VALUES (:name, :slug)');
                $ins->execute(['name' => $label, 'slug' => $slug]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $upd = $pdo->prepare('UPDATE article_categories SET name = :name WHERE id = :id');
                $upd->execute(['name' => $label, 'id' => (int) $id]);
            }
            $ids[$slug] = (int) $id;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stale = $pdo->prepare("SELECT id, slug FROM article_categories WHERE slug NOT IN ($placeholders)");
        $stale->execute(array_keys($ids));
        $staleRows = $stale->fetchAll();

        if (!$staleRows) {
            return;
        }

        $fallbackId = $ids['sepak-bola'];
        $staleIds = array_map(static fn($r) => (int) $r['id'], $staleRows);

        $reassignPlaceholders = implode(',', array_fill(0, count($staleIds), '?'));
        $reassign = $pdo->prepare("UPDATE pages SET category_id = ? WHERE category_id IN ($reassignPlaceholders)");
        $reassign->execute(array_merge([$fallbackId], $staleIds));

        $delete = $pdo->prepare("DELETE FROM article_categories WHERE id IN ($reassignPlaceholders)");
        $delete->execute($staleIds);
    } catch (Throwable $e) {
        error_log('[wpm_site_migrate_categories] ' . $e->getMessage());
    }
}

wpm_site_migrate_categories($pdo);

// ─── URL helpers ────────────────────────────────────────────────────────────

function wpm_article_url(string $slug): string
{
    return wpm_base_url('/artikel/' . rawurlencode($slug));
}

function wpm_category_url(string $slug): string
{
    return wpm_base_url('/kategori/' . rawurlencode($slug));
}

/**
 * True if the current request path matches $path (both compared with
 * WPM_BASE_PATH stripped, so it works the same on production root and
 * local subfolder dev). Pass $prefix = true to match any path that
 * starts with $path — used for the mobile bottom nav's "Live" tab, since
 * live pages are /live/{id}/{slug}, not a single fixed path.
 */
function wpm_is_active_path(string $path, bool $prefix = false): bool
{
    $current = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (WPM_BASE_PATH !== '' && str_starts_with($current, WPM_BASE_PATH)) {
        $current = substr($current, strlen(WPM_BASE_PATH));
    }
    $current = '/' . ltrim($current, '/');
    $path = '/' . trim($path, '/');

    if ($prefix) {
        return $path === '/' ? $current === '/' : str_starts_with($current . '/', $path . '/');
    }

    return rtrim($current, '/') === rtrim($path, '/');
}

// ─── Data helpers ───────────────────────────────────────────────────────────

/** Published articles, newest first, optionally filtered by category slug. */
function wpm_get_articles(PDO $pdo, int $limit = 10, int $offset = 0, ?string $categorySlug = null): array
{
    $sql = 'SELECT p.page_id, p.title, p.slug, p.excerpt, p.featured_image, p.published_at,
                   p.is_featured, p.is_trending, p.views, c.name AS category_name, c.slug AS category_slug
            FROM pages p
            LEFT JOIN article_categories c ON c.id = p.category_id
            WHERE p.status = "published" AND p.published_at IS NOT NULL AND p.published_at <= NOW()';
    $params = [];
    if ($categorySlug !== null) {
        $sql .= ' AND c.slug = :slug';
        $params['slug'] = $categorySlug;
    }
    $sql .= ' ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function wpm_count_articles(PDO $pdo, ?string $categorySlug = null): int
{
    $sql = 'SELECT COUNT(*) FROM pages p LEFT JOIN article_categories c ON c.id = p.category_id
            WHERE p.status = "published" AND p.published_at IS NOT NULL AND p.published_at <= NOW()';
    $params = [];
    if ($categorySlug !== null) {
        $sql .= ' AND c.slug = :slug';
        $params['slug'] = $categorySlug;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function wpm_get_article_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM pages p
         LEFT JOIN article_categories c ON c.id = p.category_id
         WHERE p.slug = :slug AND p.status = "published"
         LIMIT 1'
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function wpm_get_related_articles(PDO $pdo, int $categoryId, int $excludePageId, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        'SELECT page_id, title, slug, featured_image, published_at
         FROM pages
         WHERE status = "published" AND category_id = :cat AND page_id != :exclude
         ORDER BY published_at DESC LIMIT :limit'
    );
    $stmt->bindValue('cat', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue('exclude', $excludePageId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function wpm_get_popular_articles(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        'SELECT page_id, title, slug, featured_image
         FROM pages WHERE status = "published"
         ORDER BY views DESC, published_at DESC LIMIT :limit'
    );
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** One "headline" article per category (newest), in nav order — feeds the bento hero. */
function wpm_get_category_headlines(PDO $pdo): array
{
    $out = [];
    foreach (array_keys(wpm_site_nav_categories()) as $slug) {
        $rows = wpm_get_articles($pdo, 1, 0, $slug);
        if ($rows) {
            $out[$slug] = $rows[0];
        }
    }
    return $out;
}

function wpm_increment_views(PDO $pdo, int $pageId): void
{
    try {
        $stmt = $pdo->prepare('UPDATE pages SET views = views + 1 WHERE page_id = :id');
        $stmt->execute(['id' => $pageId]);
    } catch (Throwable $e) {
        // non-critical
    }
}

// ─── Advertisements ─────────────────────────────────────────────────────────
// Same wpm_render_ad_slot() contract as wcm1_version1 — cms-admin's
// Advertisements module (advertisements/ad_positions tables) is generic
// plumbing shared across projects. Device targeting is stored but not
// filtered here (no server-side device detection on this frontend).

function wpm_ad_pick(PDO $pdo, string $positionSlug, string $scope, ?int $targetId = null): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT a.* FROM advertisements a
             INNER JOIN ad_positions p ON p.id = a.position_id
             WHERE p.slug = :slug
               AND a.is_active = 1
               AND (a.start_date IS NULL OR a.start_date <= CURDATE())
               AND (a.end_date IS NULL OR a.end_date >= CURDATE())
               AND (
                     a.placement_scope = 'global'
                     OR (a.placement_scope = :scope
                         AND (a.placement_target_id IS NULL OR a.placement_target_id = :target_id))
                   )
             ORDER BY a.sort_order ASC, RAND()
             LIMIT 1"
        );
        $stmt->bindValue('slug', $positionSlug);
        $stmt->bindValue('scope', $scope);
        $stmt->bindValue('target_id', $targetId, $targetId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        $ad = $stmt->fetch();

        return $ad ?: null;
    } catch (Throwable $e) {
        error_log('[wpm_ad_pick] ' . $e->getMessage());

        return null;
    }
}

function wpm_render_ad_slot(PDO $pdo, string $positionSlug, string $scope, ?int $targetId = null): string
{
    $ad = wpm_ad_pick($pdo, $positionSlug, $scope, $targetId);
    if ($ad === null) {
        return '';
    }

    try {
        $pdo->prepare('UPDATE advertisements SET impressions = impressions + 1 WHERE id = :id')
            ->execute(['id' => (int) $ad['id']]);
    } catch (Throwable $e) {
        // non-critical
    }

    $clickUrl = wpm_base_url('/ad-click.php?id=' . (int) $ad['id']);
    $targetAttr = !empty($ad['open_in_new_tab']) ? ' target="_blank" rel="noopener sponsored"' : ' rel="sponsored"';
    $sponsoredLabel = !empty($ad['show_sponsored_label'])
        ? '<span class="wpm-ad__label">' . wpm_esc((string) ($ad['advertiser_label'] ?: 'Ad')) . '</span>'
        : '';

    $adType = (string) $ad['ad_type'];

    if ($adType === 'html' || $adType === 'external_code') {
        $code = $adType === 'html' ? (string) $ad['html_code'] : (string) $ad['external_code'];
        return '<div class="wpm-ad-slot wpm-ad-slot--' . wpm_esc($adType) . '">' . $sponsoredLabel . $code . '</div>';
    }

    if ($adType === 'video') {
        $videoSrc = (string) ($ad['video_path'] ?: $ad['video_url']);
        if ($videoSrc === '') {
            return '';
        }
        $attrs = [];
        if (!empty($ad['video_autoplay'])) { $attrs[] = 'autoplay'; }
        if (!empty($ad['video_muted'])) { $attrs[] = 'muted'; }
        if (!empty($ad['video_loop'])) { $attrs[] = 'loop'; }
        if (!empty($ad['video_controls'])) { $attrs[] = 'controls'; }
        $poster = trim((string) ($ad['video_poster'] ?? ''));

        $html = '<div class="wpm-ad-slot wpm-ad-slot--video">' . $sponsoredLabel;
        $html .= '<video src="' . wpm_esc(wpm_image_url($videoSrc)) . '"'
            . ($poster !== '' ? ' poster="' . wpm_esc(wpm_image_url($poster)) . '"' : '')
            . ' ' . implode(' ', $attrs) . ' playsinline></video>';
        $html .= '</div>';

        return $html;
    }

    $headline = trim((string) ($ad['headline'] ?? ''));
    $description = trim((string) ($ad['description'] ?? ''));
    $ctaText = trim((string) ($ad['cta_text'] ?? ''));
    $hasLink = trim((string) ($ad['target_url'] ?? '')) !== '';

    $inner = $sponsoredLabel;
    if ($adType === 'image' && !empty($ad['banner_image'])) {
        $inner .= '<img src="' . wpm_esc(wpm_image_url((string) $ad['banner_image'])) . '" alt="'
            . wpm_esc((string) ($ad['image_alt'] ?: $headline)) . '" loading="lazy">';
    }
    if ($headline !== '') {
        $inner .= '<span class="wpm-ad__headline">' . wpm_esc($headline) . '</span>';
    }
    if ($description !== '') {
        $inner .= '<span class="wpm-ad__desc">' . wpm_esc($description) . '</span>';
    }
    if ($ctaText !== '') {
        $inner .= '<span class="wpm-ad__cta">' . wpm_esc($ctaText) . '</span>';
    }

    $tag = $hasLink ? 'a' : 'div';
    $hrefAttr = $hasLink ? ' href="' . wpm_esc($clickUrl) . '"' . $targetAttr : '';

    return '<' . $tag . ' class="wpm-ad-slot wpm-ad-slot--' . wpm_esc($adType) . '"' . $hrefAttr . '>' . $inner . '</' . $tag . '>';
}

// ─── Display helpers ────────────────────────────────────────────────────────

function wpm_esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function wpm_image_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return wpm_base_url('/assets/img/placeholder.svg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return wpm_base_url($path);
}

function wpm_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit yang lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam yang lalu';
    }
    if ($diff < 86400 * 7) {
        return floor($diff / 86400) . ' hari yang lalu';
    }

    return date('d M Y', $ts);
}
