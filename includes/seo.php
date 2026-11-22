<?php
declare(strict_types=1);

/**
 * Stage 6b / Prompt 11: SEO helpers.
 *
 * Provides canonical URL resolution, Open Graph / Twitter Card meta
 * rendering, and optional JSON-LD structured data for public pages.
 *
 * Usage: set $seoTitle, $seoDescription and optional $seoImage /
 * $seoNoindex before including public_header.php, or call
 * renderSeoMeta([...]) directly.
 */

function canonicalUrl(): string
{
    $base = rtrim(BASE_URL, '/');
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    if ($path === '' || $path === '/') {
        return $base . '/';
    }

    return $base . $path;
}

/**
 * Render SEO meta tags (canonical, Open Graph, Twitter Card) and
 * optional JSON-LD structured data into the <head>.
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   image?: string,
 *   image_alt?: string,
 *   canonical?: string,
 *   type?: string,
 *   noindex?: bool,
 *   structured_data?: array
 * } $opts
 */
function renderSeoMeta(array $opts = []): void
{
    $base = rtrim(BASE_URL, '/');

    $title = $opts['title'] ?? 'CampusFind';
    $description = $opts['description']
        ?? 'Report, search and recover lost items on campus with CampusFind.';
    $canonical = $opts['canonical'] ?? canonicalUrl();
    $image = $opts['image'] ?? $base . '/assets/images/campusfind-backpack-search.png';
    $imageAlt = $opts['image_alt'] ?? 'CampusFind lost and found on campus';
    $type = $opts['type'] ?? 'website';
    $noindex = !empty($opts['noindex']);

    if ($noindex) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    } else {
        echo '<meta name="robots" content="index, follow">' . "\n";
    }

    echo '    <link rel="canonical" href="' . e($canonical) . '">' . "\n";

    // Open Graph
    echo '    <meta property="og:site_name" content="CampusFind">' . "\n";
    echo '    <meta property="og:type" content="' . e($type) . '">' . "\n";
    echo '    <meta property="og:title" content="' . e($title) . '">' . "\n";
    echo '    <meta property="og:description" content="' . e($description) . '">' . "\n";
    echo '    <meta property="og:url" content="' . e($canonical) . '">' . "\n";
    echo '    <meta property="og:image" content="' . e($image) . '">' . "\n";
    echo '    <meta property="og:image:alt" content="' . e($imageAlt) . '">' . "\n";

    // Twitter Card
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . e($title) . '">' . "\n";
    echo '    <meta name="twitter:description" content="' . e($description) . '">' . "\n";
    echo '    <meta name="twitter:image" content="' . e($image) . '">' . "\n";

    // JSON-LD structured data (only for verified visible facts)
    $structured = $opts['structured_data'] ?? null;

    if (is_array($structured) && $structured) {
        $json = json_encode(
            $structured,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES
        );

        if ($json !== false) {
            echo '    <script type="application/ld+json">' . $json . '</script>' . "\n";
        }
    }
}

/**
 * Build the default WebSite + Organization structured data block.
 * Only contains verified, visible facts (no fake ratings/reviews).
 */
function defaultStructuredData(string $pageDescription): array
{
    $base = rtrim(BASE_URL, '/');

    return [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'WebSite',
                'name'  => 'CampusFind',
                'url'   => $base . '/',
                'description' => $pageDescription,
                'inLanguage'  => 'en',
            ],
            [
                '@type' => 'Organization',
                'name'  => 'CampusFind',
                'url'   => $base . '/',
                'logo'  => $base . '/assets/images/campusfind-backpack-search.png',
            ],
        ],
    ];
}