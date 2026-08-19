<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-bootstrap.php';

$navCategories = wpm_site_nav_categories();
$popular = wpm_get_popular_articles($pdo, 5);

$latestRows = wpm_get_articles($pdo, 8, 0, null);
$highlightRows = wpm_get_articles($pdo, 4, 8, null);

$pageTitle = WPM_SITE_NAME . ' — ' . WPM_SITE_TAGLINE;
$metaDescription = 'Berita bola dan update olahraga terkini di ' . WPM_SITE_NAME . ': Sepak Bola, Basket, Voli, dan Bursa Transfer.';
$activeNavSlug = 'home';
$heroTitle = 'Sorotan Utama';
$heroSubtitle = 'Berita bola & olahraga terkini — diperbarui setiap hari';
require __DIR__ . '/includes/site-header.php';
?>

<main class="wrap main-layout">
  <div>

    <div class="section-head"><span class="bar"></span><h2>Berita Terbaru</h2></div>
    <div class="news-list">
      <?php foreach ($latestRows as $item): ?>
      <div class="news-card">
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-thumb wpm-photo">
          <img src="<?= wpm_esc(wpm_image_url($item['featured_image'])) ?>" alt="<?= wpm_esc($item['title']) ?>">
        </a>
        <div class="news-body">
          <h3><a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>"><?= wpm_esc($item['title']) ?></a></h3>
          <div class="news-meta">
            <?php if ($item['category_name']): ?><a href="<?= wpm_esc(wpm_category_url($item['category_slug'])) ?>" class="badge <?= wpm_category_color_class($item['category_slug']) ?>"><?= wpm_esc($item['category_name']) ?></a><?php endif; ?>
            <span class="news-time"><?= wpm_esc(wpm_time_ago($item['published_at'])) ?></span>
          </div>
        </div>
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-cta">Baca</a>
      </div>
      <?php endforeach; ?>
      <?php if (!$latestRows): ?>
      <p class="sidebar-empty">Belum ada artikel. Artikel yang dipublikasikan di kategori Sepak Bola, Basket, Voli, atau Bursa Transfer akan tampil di sini.</p>
      <?php endif; ?>
    </div>

    <?php if ($highlightRows): ?>
    <div class="section-head"><span class="bar"></span><h2>Sorotan Pekan Ini</h2></div>
    <div class="news-list">
      <?php foreach ($highlightRows as $item): ?>
      <div class="news-card">
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-thumb wpm-photo">
          <img src="<?= wpm_esc(wpm_image_url($item['featured_image'])) ?>" alt="<?= wpm_esc($item['title']) ?>">
        </a>
        <div class="news-body">
          <h3><a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>"><?= wpm_esc($item['title']) ?></a></h3>
          <div class="news-meta">
            <?php if ($item['category_name']): ?><a href="<?= wpm_esc(wpm_category_url($item['category_slug'])) ?>" class="badge <?= wpm_category_color_class($item['category_slug']) ?>"><?= wpm_esc($item['category_name']) ?></a><?php endif; ?>
            <span class="news-time"><?= wpm_esc(wpm_time_ago($item['published_at'])) ?></span>
          </div>
        </div>
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-cta">Baca</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <aside>
    <div class="sidebar-box">
      <h3>Kategori</h3>
      <div class="bar"></div>
      <div class="sidebar-list">
        <?php foreach ($navCategories as $navSlug => $navLabel): ?>
        <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>"><?= wpm_esc($navLabel) ?></a>
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
    <a href="<?= wpm_esc(wpm_category_url('bursa-transfer')) ?>" class="promo-banner"><span>Bursa Transfer Terkini &rarr;</span></a>
  </aside>
</main>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
