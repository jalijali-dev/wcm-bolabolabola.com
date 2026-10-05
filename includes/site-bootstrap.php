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

/**
 * Active banners for a placement (e.g. 'home_hero'), set up in
 * cms-admin → Banners. Active = is_active=1, placement matches, and today
 * falls inside start_date/end_date (either or both may be empty = no limit).
 * Ordered by sort_order. Never throws: on any DB problem returns [] so a
 * banner-table hiccup can't take the public site down.
 */
function wpm_banners_active(PDO $pdo, string $placement): array
{
    try {
        try {
            // height_preset requires the migration in docs/migrations — fall back gracefully
            // if it hasn't been run yet on this environment.
            $stmt = $pdo->prepare(
                "SELECT id, title, subtitle, button_text, button_url, desktop_image, mobile_image, height_preset
                 FROM banners
                 WHERE is_active = 1
                   AND placement = :placement
                   AND (start_date IS NULL OR start_date <= CURDATE())
                   AND (end_date IS NULL OR end_date >= CURDATE())
                 ORDER BY sort_order ASC, id DESC"
            );
            $stmt->execute(['placement' => $placement]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $stmt = $pdo->prepare(
                "SELECT id, title, subtitle, button_text, button_url, desktop_image, mobile_image
                 FROM banners
                 WHERE is_active = 1
                   AND placement = :placement
                   AND (start_date IS NULL OR start_date <= CURDATE())
                   AND (end_date IS NULL OR end_date >= CURDATE())
                 ORDER BY sort_order ASC, id DESC"
            );
            $stmt->execute(['placement' => $placement]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['height_preset'] = '500';
            }
            unset($row);

            return $rows;
        }
    } catch (Throwable $e) {
        error_log('[wpm_banners_active] ' . $e->getMessage());

        return [];
    }
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

function wpm_ad_pick(PDO $pdo, string $positionSlug, string $scope, ?int $targetId = null, ?string $adType = null): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT a.* FROM advertisements a
             INNER JOIN ad_positions p ON p.id = a.position_id
             WHERE p.slug = :slug
               AND a.is_active = 1
               AND (:atype IS NULL OR a.ad_type = :atype2)
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
        $stmt->bindValue('atype', $adType, $adType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('atype2', $adType, $adType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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

/** Category row id for a nav slug (ads target categories by this id), or null. */
function wpm_category_id(PDO $pdo, string $slug): ?int
{
    try {
        $stmt = $pdo->prepare('SELECT id FROM article_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    } catch (Throwable $e) {
        return null;
    }
}

/** Ad slot wrapped in a spacing row; '' (no wrapper at all) when nothing is booked there. */
function wpm_ad_row(PDO $pdo, string $positionSlug, string $scope, ?int $targetId = null): string
{
    $html = wpm_render_ad_slot($pdo, $positionSlug, $scope, $targetId);

    return $html === '' ? '' : '<div class="wpm-ad-row wpm-ad-row--' . wpm_esc($positionSlug) . '">' . $html . '</div>';
}

/** Insert $inject after the middle </p> of $html; unchanged when there are < 3 paragraphs. */
function wpm_inject_midpoint(string $html, string $inject): string
{
    if ($inject === '' || preg_match_all('#</p>#i', $html, $m, PREG_OFFSET_CAPTURE) < 3) {
        return $html;
    }
    $mid = $m[0][(int) floor((count($m[0]) - 1) / 2)];
    $pos = $mid[1] + strlen($mid[0]);

    return substr($html, 0, $pos) . $inject . substr($html, $pos);
}

/**
 * Popup overlay for a position (e.g. 'homepage-popup'). One popup at most:
 * wpm_ad_pick() returns a single ad per request (rotation between several
 * active popups), restricted to ad_type = 'popup' so a non-popup ad booked
 * on this position can't leak into an overlay.
 *
 * Whether it actually opens is decided in the browser, per visitor: after the
 * ad's delay, unless the visitor was already shown a popup on this position
 * inside the ad's frequency window (session / 24h / every load). The state is
 * keyed by POSITION, not ad id — otherwise rotation would hand a returning
 * visitor a "different" popup each reload. The impression is counted when it
 * really opens (beacon to ad-click.php?imp=1), not on every page render.
 * CSS/JS are inline on purpose: .htaccess caches *.css/*.js for a month.
 */
function wpm_render_popup_ad(PDO $pdo, string $positionSlug, string $scope, ?int $targetId = null): string
{
    try {
        $stmt = $pdo->query('SELECT ads_enabled FROM ad_settings LIMIT 1');
        $enabled = $stmt ? $stmt->fetchColumn() : 1;
        if ($enabled !== false && (int) $enabled === 0) {
            return '';
        }
    } catch (Throwable $e) {
        // no settings row/table yet: ads default to on, like the other slots
    }

    $ad = wpm_ad_pick($pdo, $positionSlug, $scope, $targetId, 'popup');
    if ($ad === null || trim((string) $ad['banner_image']) === '') {
        return '';
    }

    $adId    = (int) $ad['id'];
    $delay   = max(0, min(30, (int) ($ad['popup_delay_seconds'] ?? 2)));
    $freq    = in_array($ad['popup_frequency'] ?? '', ['every_visit', 'once_per_session', 'once_per_day'], true) ? $ad['popup_frequency'] : 'once_per_session';
    $img     = '<img src="' . wpm_esc(wpm_image_url((string) $ad['banner_image'])) . '" alt="' . wpm_esc((string) ($ad['image_alt'] ?: $ad['name'])) . '" class="wpm-popup__img">';
    $hasLink = trim((string) $ad['target_url']) !== '';
    $newTab  = !empty($ad['open_in_new_tab']);
    $body    = $hasLink
        ? '<a href="' . wpm_esc(wpm_base_url('/ad-click.php?id=' . $adId)) . '"' . ($newTab ? ' target="_blank" rel="noopener sponsored"' : ' rel="sponsored"') . '>' . $img . '</a>'
        : $img;
    $label   = !empty($ad['show_sponsored_label'])
        ? '<span class="wpm-popup__label">' . wpm_esc((string) ($ad['advertiser_label'] ?: 'Ad')) . '</span>'
        : '';
    $cfg = json_encode([
        'key' => 'wpm_popup_' . $positionSlug,
        'delay' => $delay,
        'freq' => $freq,
        'imp' => wpm_base_url('/ad-click.php?imp=1&id=' . $adId),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    return <<<HTML
<style>
.wpm-popup{position:fixed;inset:0;z-index:1500;display:none;align-items:center;justify-content:center;padding:16px;}
.wpm-popup.is-open{display:flex;}
.wpm-popup__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.6);}
.wpm-popup__box{position:relative;max-width:min(520px,92vw);max-height:88vh;background:#fff;border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.4);overflow:hidden;}
.wpm-popup__img{display:block;max-width:100%;max-height:88vh;width:auto;height:auto;margin:0 auto;}
.wpm-popup__close{position:absolute;top:8px;right:8px;width:34px;height:34px;border-radius:50%;border:0;background:rgba(0,0,0,.65);color:#fff;font-size:20px;line-height:1;cursor:pointer;z-index:2;}
.wpm-popup__close:hover{background:var(--red,#d81922);}
.wpm-popup__label{position:absolute;left:8px;top:8px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:2px 7px;border-radius:4px;z-index:2;}
</style>
<div class="wpm-popup" id="wpm-popup" role="dialog" aria-modal="true" aria-label="Iklan">
  <div class="wpm-popup__backdrop" data-popup-close></div>
  <div class="wpm-popup__box">
    {$label}<button type="button" class="wpm-popup__close" data-popup-close aria-label="Tutup iklan">&times;</button>
    {$body}
  </div>
</div>
<script>
(function () {
  var cfg = {$cfg}, el = document.getElementById('wpm-popup');
  if (!el) return;
  function seen() {
    try {
      if (cfg.freq === 'every_visit') return false;
      if (cfg.freq === 'once_per_session') return !!sessionStorage.getItem(cfg.key);
      var t = parseInt(localStorage.getItem(cfg.key) || '0', 10);
      return t > 0 && Date.now() - t < 86400000;
    } catch (e) { return false; }
  }
  function mark() {
    try {
      if (cfg.freq === 'once_per_session') sessionStorage.setItem(cfg.key, '1');
      else if (cfg.freq === 'once_per_day') localStorage.setItem(cfg.key, String(Date.now()));
    } catch (e) {}
  }
  function close() { el.classList.remove('is-open'); }
  el.addEventListener('click', function (e) { if (e.target.closest('[data-popup-close]')) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  if (seen()) return;
  setTimeout(function () {
    if (seen() && cfg.freq !== 'every_visit') return;   // another tab got there first
    el.classList.add('is-open');
    mark();
    if (navigator.sendBeacon) navigator.sendBeacon(cfg.imp);
  }, cfg.delay * 1000);
})();
</script>
HTML;
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
