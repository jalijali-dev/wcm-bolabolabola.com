<?php
declare(strict_types=1);
/**
 * includes/site-footer.php — shared footer partial. Pair of
 * includes/site-header.php: each page opens its own <main>, this file only
 * renders the dark footer + closing tags.
 */
$navCategories = wpm_site_nav_categories();
?>
<footer class="footer">
  <div class="wrap">
    <div class="footer-wordmark">
      <div class="logo-badge">
        <img src="<?= wpm_esc(wpm_base_url('/assets/img/logo-red.png')) ?>" alt="<?= wpm_esc(WPM_SITE_NAME) ?>" style="width:100%;height:100%;object-fit:contain;">
      </div>
      <h2><?= wpm_esc(WPM_SITE_NAME) ?></h2>
      <p><?= wpm_esc(WPM_SITE_TAGLINE) ?></p>
    </div>

    <div class="footer-grid">
      <div>
        <h5>Berita</h5>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Terbaru</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Sorotan</a>
        <a href="<?= wpm_esc(wpm_base_url('/cari.php')) ?>">Cari Berita</a>
      </div>
      <div>
        <h5>Kategori</h5>
        <?php foreach ($navCategories as $navSlug => $navLabel): ?>
        <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>"><?= wpm_esc($navLabel) ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h5>Tentang</h5>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Redaksi</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Kontak</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Kirim Berita</a>
      </div>
      <div>
        <h5>Legal</h5>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Kebijakan Privasi</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Ketentuan Layanan</a>
      </div>
      <div>
        <h5>Ikuti Kami</h5>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Instagram</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">Twitter / X</a>
        <a href="<?= wpm_esc(wpm_base_url('/')) ?>">YouTube</a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="copy">&copy; <?= date('Y') ?> <?= wpm_esc(WPM_SITE_NAME) ?> (bolabolabola.com). Seluruh hak cipta dilindungi.</div>
    <div class="socials">
      <a href="#" aria-label="Instagram">IG</a>
      <a href="#" aria-label="Twitter">X</a>
      <a href="#" aria-label="YouTube">YT</a>
    </div>
  </div>
</footer>
</body>
</html>
