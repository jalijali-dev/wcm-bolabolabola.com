<?php
declare(strict_types=1);

/**
 * Shared "feature not installed yet" page — used by pages that depend on
 * an optional service (e.g. services/PromptLoader.php) not being present
 * on this environment. Renders the normal admin chrome instead of a bare
 * die() so an operator hitting a guarded page still gets a readable page,
 * not a raw PHP string.
 *
 * Caller sets $pageTitle, $currentNav, $breadcrumbs, and
 * $featureUnavailableMessage before requiring this file, then exit()s
 * right after.
 */

$featureUnavailableMessage = $featureUnavailableMessage
    ?? 'Fitur ini belum tersedia di environment ini.';

// Wrap any `path/to/File.php`-looking substring in <code> for a monospace
// look (matches the styling other admin pages use for file/path mentions).
// Escape first, THEN wrap — the pattern only matches safe path characters
// (word chars, slashes, dots), so it can't reintroduce any markup the
// escaping just stripped.
$featureUnavailableMessageHtml = preg_replace(
    '/\b([\w\-]+(?:\/[\w\-]+)*\.php)\b/',
    '<code>$1</code>',
    cms_esc($featureUnavailableMessage)
);

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
require dirname(__DIR__) . '/includes/navbar.php';
require dirname(__DIR__) . '/includes/breadcrumb.php';
// alerts.php opens the .admin-content wrapper (flex:1 + page padding) that
// every other admin page relies on for its layout — omitting it left the
// .admin-stack section as a bare flex child of .admin-main with no side
// padding, and its single grid row free to stretch across the leftover
// min-height:100vh space, which is what caused the oversized empty-looking
// box below the message. Pass an empty $alerts explicitly since this page
// has no dismissible notices of its own.
$alerts = [];
require dirname(__DIR__) . '/includes/alerts.php';
?>
<section class="admin-stack">
    <div class="empty-state empty-state--feature-unavailable">
        <h2 class="section-title">Fitur Belum Tersedia</h2>
        <p><?= $featureUnavailableMessageHtml ?></p>
    </div>
</section>
<?php
require dirname(__DIR__) . '/includes/footer.php';
