<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-bootstrap.php';

$navCategories = wpm_site_nav_categories();
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '' || !isset($navCategories[$slug])) {
    http_response_code(404);
    $pageTitle = 'Kategori tidak ditemukan — ' . WPM_SITE_NAME;
    $breadcrumbLabel = 'Kategori tidak ditemukan';
    require __DIR__ . '/includes/site-header.php';
    echo '<main class="wrap main-layout"><div><h1 class="wpm-page-title">Kategori tidak ditemukan</h1><p><a href="' . wpm_esc(wpm_base_url('/')) . '">Kembali ke beranda</a></p></div></main>';
    require __DIR__ . '/includes/site-footer.php';
    exit;
}

$label = $navCategories[$slug];
$colorClass = wpm_category_color_class($slug);
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$articles = wpm_get_articles($pdo, $perPage, $offset, $slug);
$total = wpm_count_articles($pdo, $slug);
$totalPages = (int) max(1, ceil($total / $perPage));
$popular = wpm_get_popular_articles($pdo, 5);

$pageTitle = $label . ' — ' . WPM_SITE_NAME;
$metaDescription = 'Kumpulan berita ' . $label . ' terbaru di ' . WPM_SITE_NAME . '.';
$activeNavSlug = $slug;
$breadcrumbLabel = $label;
require __DIR__ . '/includes/site-header.php';
?>

<main class="wrap main-layout">
  <div>
    <div class="section-head"><span class="bar"></span><h2><?= wpm_esc($label) ?></h2></div>

    <div class="news-list">
      <?php foreach ($articles as $item): ?>
      <div class="news-card">
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-thumb wpm-photo">
          <img src="<?= wpm_esc(wpm_image_url($item['featured_image'])) ?>" alt="<?= wpm_esc($item['title']) ?>">
        </a>
        <div class="news-body">
          <h3><a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>"><?= wpm_esc($item['title']) ?></a></h3>
          <div class="news-meta">
            <span class="badge <?= $colorClass ?>"><?= wpm_esc($label) ?></span>
            <span class="news-time"><?= wpm_esc(wpm_time_ago($item['published_at'])) ?></span>
          </div>
        </div>
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-cta">Baca</a>
      </div>
      <?php endforeach; ?>
      <?php if (!$articles): ?>
      <p class="sidebar-empty">Belum ada artikel di kategori <?= wpm_esc($label) ?>.</p>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="wpm-pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p === $page): ?>
          <span class="is-active"><?= $p ?></span>
        <?php else: ?>
          <a href="<?= wpm_esc(wpm_category_url($slug)) ?>?page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="sidebar-box">
      <h3>Kategori</h3>
      <div class="bar"></div>
      <div class="sidebar-list">
        <?php foreach ($navCategories as $navSlug => $navLabel): ?>
        <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>" class="<?= $navSlug === $slug ? 'is-active' : '' ?>"><?= wpm_esc($navLabel) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
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
  </aside>
</main>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
