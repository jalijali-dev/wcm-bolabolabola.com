<?php
declare(strict_types=1);

/**
 * Ad positions the PUBLIC site actually renders (slug => label). Single list
 * so ads.php and ad-positions.php seed the same set; seeding is INSERT IGNORE
 * (slug is UNIQUE), so it only ever adds what is missing and never touches
 * positions an admin renamed or created by hand.
 */
function cms_ad_default_positions(): array
{
    return [
        'article-before-title'    => 'Article — Before Title',
        'article-after-title'     => 'Article — After Title',
        'sidebar-left'            => 'Sidebar (Left)',
        'sidebar-right'           => 'Sidebar (Right)',
        'below-main-menu'         => 'Below Main Menu (all pages)',
        'homepage-hero'           => 'Homepage — Under Hero',
        'homepage-before-popular' => 'Homepage — Before "Terpopuler"',
        'between-article-cards'   => 'Between Article Cards (home & category lists)',
        'above-article'           => 'Article — Above Article',
        'middle-of-article'       => 'Article — Middle of Content',
        'below-article'           => 'Article — Below Article',
        'footer'                  => 'Footer (above footer, all pages)',
        'homepage-popup'          => 'Homepage Popup (overlay)',
        'stream-above-player'     => 'Live Stream — Above Player',
        'stream-below-player'     => 'Live Stream — Below Player',
        'stream-sidebar'          => 'Live Stream — Sidebar (300x600)',
    ];
}

function cms_ad_seed_positions(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO ad_positions (name, slug) VALUES (:name, :slug)');
    foreach (cms_ad_default_positions() as $slug => $name) {
        $stmt->execute(['name' => $name, 'slug' => $slug]);
    }
}
