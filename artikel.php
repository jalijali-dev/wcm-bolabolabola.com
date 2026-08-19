<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$article = $slug !== '' ? wpm_get_article_by_slug($pdo, $slug) : null;

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Artikel tidak ditemukan — ' . WPM_SITE_NAME;
    $breadcrumbLabel = 'Artikel tidak ditemukan';
    require __DIR__ . '/includes/site-header.php';
    echo '<main class="wrap main-layout"><div><h1 class="wpm-page-title">Artikel tidak ditemukan</h1><p><a href="' . wpm_esc(wpm_base_url('/')) . '">Kembali ke beranda</a></p></div></main>';
    require __DIR__ . '/includes/site-footer.php';
    exit;
}

wpm_increment_views($pdo, (int) $article['page_id']);
$related = $article['category_id']
    ? wpm_get_related_articles($pdo, (int) $article['category_id'], (int) $article['page_id'], 5)
    : [];
$popular = wpm_get_popular_articles($pdo, 5);
$colorClass = wpm_category_color_class($article['category_slug'] ?? null);
$navCategories = wpm_site_nav_categories();

$pageTitle = $article['meta_title'] ?: ($article['title'] . ' — ' . WPM_SITE_NAME);
$metaDescription = $article['meta_description'] ?: $article['excerpt'];
$activeNavSlug = $article['category_slug'] ?? '';
$breadcrumbLabel = $article['category_name'] ?: $article['title'];
require __DIR__ . '/includes/site-header.php';
?>

<main class="wrap wpm-article">
  <div>
    <?php if ($article['category_name']): ?><a href="<?= wpm_esc(wpm_category_url($article['category_slug'])) ?>" class="badge <?= $colorClass ?>"><?= wpm_esc($article['category_name']) ?></a><?php endif; ?>
    <h1 class="wpm-article__title"><?= wpm_esc($article['title']) ?></h1>
    <div class="wpm-article__meta"><?= wpm_esc(wpm_time_ago($article['published_at'])) ?> &middot; <?= (int) $article['views'] ?> views</div>

    <?php if (!empty($article['featured_image'])): ?>
    <img class="wpm-article__cover" src="<?= wpm_esc(wpm_image_url($article['featured_image'])) ?>" alt="<?= wpm_esc($article['title']) ?>">
    <?php endif; ?>

    <?= wpm_render_ad_slot($pdo, 'article-before-title', 'article', (int) $article['page_id']) ?>

    <div class="wpm-article__body"><?= $article['content'] ?></div>

    <?= wpm_render_ad_slot($pdo, 'article-after-title', 'article', (int) $article['page_id']) ?>
  </div>

  <aside>
    <?= wpm_render_ad_slot($pdo, 'sidebar-left', 'article', (int) $article['page_id']) ?>
    <?php if ($related): ?>
    <div class="sidebar-box">
      <h3>Artikel Terkait</h3>
      <div class="bar"></div>
      <div class="sidebar-list">
        <?php foreach ($related as $r): ?>
        <a href="<?= wpm_esc(wpm_article_url($r['slug'])) ?>"><?= wpm_esc($r['title']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="sidebar-box">
      <h3>Terpopuler</h3>
      <div class="bar"></div>
      <div class="sidebar-list">
        <?php foreach ($popular as $p): ?>
        <a href="<?= wpm_esc(wpm_article_url($p['slug'])) ?>"><?= wpm_esc($p['title']) ?></a>
        <?php endforeach; ?>
        <?php if (!$popular): ?><p class="sidebar-empty">Belum ada data.</p><?php endif; ?>
      </div>
    </div>
    <?= wpm_render_ad_slot($pdo, 'sidebar-right', 'article', (int) $article['page_id']) ?>
  </aside>
</main>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
