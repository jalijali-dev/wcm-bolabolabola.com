<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];
if ($q !== '') {
    $stmt = $pdo->prepare(
        'SELECT p.page_id, p.title, p.slug, p.excerpt, p.featured_image, p.published_at,
                c.name AS category_name, c.slug AS category_slug
         FROM pages p
         LEFT JOIN article_categories c ON c.id = p.category_id
         WHERE p.status = "published" AND (p.title LIKE :q1 OR p.excerpt LIKE :q2)
         ORDER BY p.published_at DESC LIMIT 20'
    );
    $stmt->execute(['q1' => '%' . $q . '%', 'q2' => '%' . $q . '%']);
    $results = $stmt->fetchAll();
}

$pageTitle = ($q !== '' ? 'Hasil pencarian: ' . $q : 'Cari') . ' — ' . WPM_SITE_NAME;
$breadcrumbLabel = 'Cari';
require __DIR__ . '/includes/site-header.php';
?>

<main class="wrap main-layout">
  <div>
    <div class="section-head"><span class="bar"></span><h2><?= $q !== '' ? 'Hasil untuk &ldquo;' . wpm_esc($q) . '&rdquo;' : 'Cari Berita' ?></h2></div>

    <div class="news-list">
      <?php foreach ($results as $item): ?>
      <div class="news-card">
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-thumb wpm-photo">
          <img src="<?= wpm_esc(wpm_image_url($item['featured_image'])) ?>" alt="<?= wpm_esc($item['title']) ?>">
        </a>
        <div class="news-body">
          <h3><a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>"><?= wpm_esc($item['title']) ?></a></h3>
          <div class="news-meta">
            <?php if (!empty($item['category_name'])): ?><a href="<?= wpm_esc(wpm_category_url($item['category_slug'])) ?>" class="badge <?= wpm_category_color_class($item['category_slug']) ?>"><?= wpm_esc($item['category_name']) ?></a><?php endif; ?>
            <span class="news-time"><?= wpm_esc(wpm_time_ago($item['published_at'])) ?></span>
          </div>
        </div>
        <a href="<?= wpm_esc(wpm_article_url($item['slug'])) ?>" class="news-cta">Baca</a>
      </div>
      <?php endforeach; ?>
      <?php if ($q !== '' && !$results): ?><p class="sidebar-empty">Tidak ada hasil untuk "<?= wpm_esc($q) ?>".</p><?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="sidebar-box">
      <h3>Kategori</h3>
      <div class="bar"></div>
      <div class="sidebar-list">
        <?php foreach (wpm_site_nav_categories() as $navSlug => $navLabel): ?>
        <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>"><?= wpm_esc($navLabel) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>
</main>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
