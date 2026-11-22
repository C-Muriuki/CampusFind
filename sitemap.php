<?php
declare(strict_types=1);

/**
 * Prompt 11: sitemap.xml (dynamic).
 *
 * Outputs an XML sitemap containing only public, indexable pages.
 * Served as application/xml.
 */

require_once __DIR__ . '/config/app.php';

$base = rtrim(BASE_URL, '/');

$today = date('Y-m-d');

$urls = [
    ['loc' => $base . '/',            'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => $base . '/pages/items.php',   'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/pages/contact.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => $base . '/pages/information.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => $base . '/pages/about.php',   'priority' => '0.5', 'changefreq' => 'monthly'],
];

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $url) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>' . "\n";
    echo '    <lastmod>' . $today . '</lastmod>' . "\n";
    echo '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>' . "\n";