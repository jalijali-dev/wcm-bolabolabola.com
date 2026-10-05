<?php
declare(strict_types=1);
/**
 * includes/site-header.php — shared header partial for Bola Bola Bola.
 * Implements the approved mockup (docs/homepage-mockup-v2.html): solid
 * red header, logo (assets/img/logo-red.png), utility bar, nav, sub-bar
 * with breadcrumb + CTA. Optionally renders the dark hero banner + category
 * strip when $heroTitle is set (homepage only).
 *
 * Outputs everything up through the sub-bar (and hero banner/strip if
 * requested) but does NOT open <main> — each page opens its own
 * <main class="wrap main-layout"> (or a page-specific layout) and must
 * close it before requiring site-footer.php.
 *
 * Expects (optional): $pageTitle, $metaDescription, $activeNavSlug,
 * $breadcrumbLabel (string shown after "Beranda ›"), $heroTitle,
 * $heroSubtitle, $activeHeroSlug.
 */
$pageTitle = $pageTitle ?? WPM_SITE_NAME;
$metaDescription = $metaDescription ?? WPM_SITE_TAGLINE;
$activeNavSlug = $activeNavSlug ?? '';
$breadcrumbLabel = $breadcrumbLabel ?? null;
$navCategories = wpm_site_nav_categories();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= wpm_esc($pageTitle) ?></title>
<meta name="description" content="<?= wpm_esc($metaDescription) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Rajdhani:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= wpm_esc(wpm_base_url('/assets/css/site.css')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= wpm_esc(wpm_base_url('/assets/img/favicon.svg')) ?>">
<link rel="alternate icon" href="<?= wpm_esc(wpm_base_url('/assets/img/favicon.ico')) ?>">
</head>
<body>

<header class="hdr">
  <div class="wrap">
    <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="brand">
      <span class="brand__mark">
        <img src="<?= wpm_esc(wpm_base_url('/assets/img/logo-red.png')) ?>" alt="<?= wpm_esc(WPM_SITE_NAME) ?>">
      </span>
      <span class="brand__text">
        <span class="brand__title">Bola Bola Bola</span>
        <span class="brand__sub">Update Bola &amp; Olahraga</span>
      </span>
    </a>
    <nav class="nav" aria-label="Navigasi utama">
      <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="<?= $activeNavSlug === 'home' ? 'is-active' : '' ?>">Beranda<small>HOME</small></a>
      <?php foreach ($navCategories as $navSlug => $navLabel): ?>
      <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>" class="<?= $activeNavSlug === $navSlug ? 'is-active' : '' ?>"><?= wpm_esc($navLabel) ?><small><?= wpm_esc(strtoupper($navLabel)) ?></small></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<div class="subbar">
  <div class="wrap">
    <div class="breadcrumb"><a href="<?= wpm_esc(wpm_base_url('/')) ?>"><b>Beranda</b></a><?php if ($breadcrumbLabel !== null): ?> &nbsp;&rsaquo;&nbsp; <?= wpm_esc($breadcrumbLabel) ?><?php endif; ?></div>
    <div class="cta-row">
      <form class="wpm-search" action="<?= wpm_esc(wpm_base_url('/cari.php')) ?>" method="get" role="search">
        <input type="text" name="q" placeholder="Cari berita bola&hellip;" aria-label="Cari berita">
        <button type="submit" class="wpm-search__btn" aria-label="Cari">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        </button>
      </form>
    </div>
  </div>
</div>

<?php if (!empty($heroTitle)): ?>
<?php if (!empty($heroBanners)): $bnMulti = count($heroBanners) > 1; ?>
<div class="hero-promo" id="hero-promo">
  <?php foreach ($heroBanners as $bnIdx => $bn):
      $bnDesktop = trim((string) $bn['desktop_image']);
      $bnMobile  = trim((string) $bn['mobile_image']);
      $bnMain    = $bnDesktop !== '' ? $bnDesktop : $bnMobile;   // desktop first, mobile as fallback
      $bnUrl     = trim((string) $bn['button_url']);
  ?>
  <a href="<?= wpm_esc($bnUrl !== '' ? $bnUrl : '#') ?>" class="hero-promo__slide<?= $bnIdx === 0 ? ' is-active' : '' ?>">
    <picture>
      <?php if ($bnMobile !== '' && $bnDesktop !== ''): ?><source media="(max-width:768px)" srcset="<?= wpm_esc(wpm_image_url($bnMobile)) ?>"><?php endif; ?>
      <img src="<?= wpm_esc(wpm_image_url($bnMain)) ?>" alt="<?= wpm_esc((string) $bn['title']) ?>" class="hero-promo__img">
    </picture>
  </a>
  <?php endforeach; ?>
  <?php if ($bnMulti): ?>
  <div class="hero-promo__nav">
    <button type="button" class="hero-promo__btn" data-dir="-1" aria-label="Banner sebelumnya">&lsaquo;</button>
    <button type="button" class="hero-promo__btn" data-dir="1" aria-label="Banner berikutnya">&rsaquo;</button>
  </div>
  <script>
  (function () {
    var box = document.getElementById('hero-promo');
    var slides = box.querySelectorAll('.hero-promo__slide');
    var i = 0, timer;
    function go(n) { slides[i].classList.remove('is-active'); i = (n + slides.length) % slides.length; slides[i].classList.add('is-active'); }
    function auto() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 6000); }
    box.querySelectorAll('.hero-promo__btn').forEach(function (b) {
      b.addEventListener('click', function () { go(i + parseInt(b.getAttribute('data-dir'), 10)); auto(); });
    });
    box.addEventListener('mouseenter', function () { clearInterval(timer); });
    box.addEventListener('mouseleave', auto);
    auto();
  })();
  </script>
  <?php endif; ?>
</div>
<div class="hero-compact">
  <div class="wrap">
    <h1><?= wpm_esc($heroTitle) ?></h1>
    <?php if (!empty($heroSubtitle)): ?><p><?= wpm_esc($heroSubtitle) ?></p><?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="hero-banner">
  <div class="hero-banner-title">
    <h1><?= wpm_esc($heroTitle) ?></h1>
    <?php if (!empty($heroSubtitle)): ?><p><?= wpm_esc($heroSubtitle) ?></p><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<div class="hero-strip">
  <div class="wrap">
    <?php foreach ($navCategories as $navSlug => $navLabel): ?>
    <a href="<?= wpm_esc(wpm_category_url($navSlug)) ?>" class="<?= ($activeHeroSlug ?? '') === $navSlug ? 'is-active' : '' ?>"><?= wpm_esc($navLabel) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
