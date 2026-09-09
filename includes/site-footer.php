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

<?php
/**
 * Mobile bottom nav — only visible <=768px (see .wpm-mobilenav in
 * site.css), desktop keeps using the header nav above untouched. Lives
 * here (not a per-page include) so it shows up on every page, live.php
 * included.
 *
 * "Live" tab points to sagagoal.com/live (external), not a path on this
 * site — this site has no live-match LISTING (see docs/HANDOFF.md: the
 * whole point of live.php here is that the listing stays on
 * sagagoal.com, this site only renders individual match players at
 * /live/{id}/{slug}). A bare /live on this domain has no id to render
 * and would just show live.php's empty-state, so linking there would be
 * a dead tab. It still lights up as "active" while actually viewing a
 * /live/... page on this site, via wpm_is_active_path('/live', true).
 *
 * "Cari Berita"/"Redaksi"/"Kontak"/"Kirim Berita" reuse the same routes
 * (or placeholder '/') the desktop header/footer already use — no new
 * pages invented here.
 */
?>
<nav class="wpm-mobilenav" aria-label="Navigasi mobile">
  <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="wpm-mobilenav__item<?= wpm_is_active_path('/') ? ' is-active' : '' ?>">
    <span class="wpm-mobilenav__icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
    </span>
    <span class="wpm-mobilenav__label">Beranda</span>
  </a>

  <a href="<?= wpm_esc(wpm_category_url('sepak-bola')) ?>" class="wpm-mobilenav__item<?= wpm_is_active_path('/kategori/sepak-bola') ? ' is-active' : '' ?>">
    <span class="wpm-mobilenav__icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v6l5 3-2 6H9l-2-6 5-3z"/></svg>
    </span>
    <span class="wpm-mobilenav__label">Sepak Bola</span>
  </a>

  <a href="https://sagagoal.com/live" rel="noopener" class="wpm-mobilenav__item wpm-mobilenav__item--live<?= wpm_is_active_path('/live', true) ? ' is-active' : '' ?>">
    <span class="wpm-mobilenav__icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8a4 4 0 100 8 4 4 0 000-8z"/><path d="M5 5a11 11 0 000 14M19 5a11 11 0 010 14"/></svg>
      <span class="wpm-mobilenav__dot"></span>
    </span>
    <span class="wpm-mobilenav__label">Live</span>
  </a>

  <a href="<?= wpm_esc(wpm_category_url('bursa-transfer')) ?>" class="wpm-mobilenav__item<?= wpm_is_active_path('/kategori/bursa-transfer') ? ' is-active' : '' ?>">
    <span class="wpm-mobilenav__icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 8h13M17 4l3 4-3 4"/><path d="M17 16H4M7 20l-3-4 3-4"/></svg>
    </span>
    <span class="wpm-mobilenav__label">Transfer</span>
  </a>

  <button type="button" class="wpm-mobilenav__item" id="wpmMobilenavMenuBtn" aria-haspopup="true" aria-expanded="false">
    <span class="wpm-mobilenav__icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </span>
    <span class="wpm-mobilenav__label">Menu</span>
  </button>
</nav>

<div class="wpm-mobilemenu" id="wpmMobilemenuPanel" hidden>
  <div class="wpm-mobilemenu__backdrop" id="wpmMobilemenuBackdrop"></div>
  <div class="wpm-mobilemenu__sheet">
    <div class="wpm-mobilemenu__handle"></div>
    <a href="<?= wpm_esc(wpm_category_url('basket')) ?>" class="wpm-mobilemenu__link">Basket</a>
    <a href="<?= wpm_esc(wpm_category_url('voli')) ?>" class="wpm-mobilemenu__link">Voli</a>
    <a href="<?= wpm_esc(wpm_base_url('/cari.php')) ?>" class="wpm-mobilemenu__link">Cari Berita</a>
    <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="wpm-mobilemenu__link">Redaksi</a>
    <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="wpm-mobilemenu__link">Kontak</a>
    <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="wpm-mobilemenu__link">Kirim Berita</a>
  </div>
</div>

<script>
(function(){
  var btn = document.getElementById('wpmMobilenavMenuBtn');
  var panel = document.getElementById('wpmMobilemenuPanel');
  var backdrop = document.getElementById('wpmMobilemenuBackdrop');
  if (!btn || !panel || !backdrop) return;
  function open(){ panel.hidden = false; btn.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function close(){ panel.hidden = true; btn.setAttribute('aria-expanded','false'); document.body.style.overflow=''; }
  btn.addEventListener('click', function(){ panel.hidden ? open() : close(); });
  backdrop.addEventListener('click', close);
})();
</script>
</body>
</html>
