<?php
declare(strict_types=1);

/**
 * Prompt 11: robots.txt (dynamic).
 *
 * Outputs a robots.txt that references the sitemap and disallows
 * private / sensitive paths. Served as text/plain.
 */

require_once __DIR__ . '/config/app.php';

$base = rtrim(BASE_URL, '/');

header('Content-Type: text/plain; charset=utf-8');

echo "# CampusFind robots.txt\n";
echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";
echo "# Private and sensitive paths — not for indexing\n";
echo "Disallow: /admin/\n";
echo "Disallow: /auth/\n";
echo "Disallow: /student/\n";
echo "Disallow: /uploads/\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /database/\n";
echo "Disallow: /logs/\n";
echo "Disallow: /tests/\n";
echo "Disallow: /doc/\n";
echo "Disallow: /documentation/\n";
echo "\n";
echo "# Private pages\n";
echo "Disallow: /pages/privacy.php\n";
echo "\n";
echo "Sitemap: {$base}/sitemap.php\n";