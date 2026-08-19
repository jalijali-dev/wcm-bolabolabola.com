<?php
declare(strict_types=1);

/**
 * includes/TimeHelpers.php
 *
 * Required at project root by cms-admin/pages/pages.php
 * (require_once dirname(__DIR__) . '/../includes/TimeHelpers.php'). Added
 * here proactively — wcm1_version1 was missing this file after its
 * cms-admin/ was cloned (only the cms-admin/ folder itself was copied),
 * which caused a dormant fatal error the first time an admin published an
 * article without an explicit published_at. Included from day one here to
 * avoid the same bug in Biang Olahraga's admin panel.
 */

if (!function_exists('wpm_now_wib')) {
    /**
     * Current time in WIB (Asia/Jakarta, UTC+7) — matches wpm_time_ago() on
     * the public frontend (includes/site-bootstrap.php), which renders
     * dates assuming WIB, not this server's UTC default. Used to auto-fill
     * `published_at` when an admin publishes an article without picking a
     * date manually.
     */
    function wpm_now_wib(): DateTime
    {
        return new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    }
}
