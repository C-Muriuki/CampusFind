<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Stage 6.3: Information Hub.
 *
 * Demonstrates required PHP concepts without turning into a code dump:
 *  - associative arrays for FAQs and safety tips
 *  - string functions (cf_strimwidth / str_replace / implode / count)
 *  - array operations (in_array / array_filter / count)
 *  - a non-sensitive theme preference cookie read
 *  - JOIN + GROUP BY reporting against the live database
 */

$faqs = [
    [
        'q' => 'How do I report a lost or found item?',
        'a' => 'Create a free account, open Report Item, and share the item name, category, location, date and description. Admins review reports before they appear publicly.',
    ],
    [
        'q' => 'Who can see my report?',
        'a' => 'Only approved reports appear in the public directory. Your private contact details (phone and email) are never shown publicly.',
    ],
    [
        'q' => 'How do I claim an item?',
        'a' => 'Open the approved item and submit a claim with proof of ownership. An administrator reviews claims and may contact you to verify before hand-over.',
    ],
    [
        'q' => 'How long does review take?',
        'a' => 'Most reports are reviewed within one to two working days. You can track the status from your dashboard and in notifications.',
    ],
    [
        'q' => 'Can I edit or withdraw a report?',
        'a' => 'Yes. Open My Reports, then choose Edit or Withdraw. Withdrawn reports leave the public directory immediately.',
    ],
    [
        'q' => 'What should I do if I find something valuable?',
        'a' => 'Report it as Found and hand it to the campus security office or administration. CampusFind helps reconnect items; physical hand-over is arranged safely.',
    ],
];

$safetyTips = [
    'Never share your password or one-time codes with anyone.',
    'Meet in a public, staffed campus location to exchange items.',
    'Do not post private contact details in public reports.',
    'Verify ownership with proof before handing an item over.',
    'Report suspicious behaviour to campus security immediately.',
];

$theme = CURRENT_THEME === 'dark' ? 'Dark' : 'Light';

// Category summary via JOIN + GROUP BY (live database reporting).
$categorySummary = [];
$totalReports = 0;
$pdo = db();

if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT c.name AS category_name, COUNT(r.id) AS total
             FROM categories c
             LEFT JOIN reports r ON r.category_id = c.id AND r.status = 'approved'
             WHERE c.is_active = 1
             GROUP BY c.id, c.name
             ORDER BY total DESC, c.name ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $count = (int) $row['total'];
            if ($count > 0) {
                $categorySummary[] = [
                    'name'  => (string) $row['category_name'],
                    'count' => $count,
                ];
            }
            $totalReports += $count;
        }
    } catch (Throwable $exception) {
        error_log('[CampusFind info] summary: ' . $exception->getMessage());
    }
}

// String / array demonstration values.
$previewText = 'CampusFind helps the campus community reconnect with lost belongings quickly and safely.';
$previewShort = cf_strimwidth($previewText, 48);
$keywords = array_filter(['lost', 'found', 'report', 'claim'], static function (string $word) use ($previewText): bool {
    return str_contains($previewText, $word);
});
$keywordList = $keywords ? implode(', ', $keywords) : 'none';

$pageTitle = 'Information | CampusFind';
$activeNav = 'info';
$seoDescription = 'Frequently asked questions, safety tips and a live category summary for CampusFind lost and found service.';
require_once __DIR__ . '/../includes/public_header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow">INFORMATION HUB</p>
        <h1>How CampusFind works</h1>
        <p class="page-hero-lead">
            Quick answers, safety tips and a live category summary.
        </p>
    </div>
</section>

<section class="page-section">
    <div class="container">

        <div class="info-grid">
            <div class="info-block">
                <h2>Frequently asked questions</h2>
                <div class="faq-list">
                    <?php foreach ($faqs as $faq): ?>
                        <details class="faq-item">
                            <summary><?= e($faq['q']) ?></summary>
                            <p><?= e($faq['a']) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="info-aside">
                <div class="info-card">
                    <h2>Safety tips</h2>
                    <ul class="info-list">
                        <?php foreach ($safetyTips as $tip): ?>
                            <li><?= e($tip) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="info-card">
                    <h2>Your theme</h2>
                    <p>
                        You are using the <strong><?= e($theme) ?></strong> theme.
                        Change it any time with the switch in the navigation.
                        Your preference is saved in a small cookie.
                    </p>
                </div>
            </aside>
        </div>

        <div class="info-reporting">
            <h2>Approved reports by category</h2>
            <?php if (!$categorySummary): ?>
                <p class="muted">No approved reports yet. Check back soon.</p>
            <?php else: ?>
                <p class="info-reporting-lead">
                    <?= (int) count($categorySummary) ?> categories with
                    <?= number_format($totalReports) ?>
                    approved report<?= $totalReports === 1 ? '' : 's' ?>.
                </p>
                <ul class="category-summary">
                    <?php foreach ($categorySummary as $entry): ?>
                        <li>
                            <span class="category-summary-name"><?= e($entry['name']) ?></span>
                            <span class="category-summary-bar" aria-hidden="true">
                                <span style="width: <?= (int) round(($entry['count'] / max(1, $totalReports)) * 100) ?>%"></span>
                            </span>
                            <span class="category-summary-count"><?= (int) $entry['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="info-concept-demo">
            <h2>About this site</h2>
            <p class="concept-demo-line">
                <strong>Short preview:</strong> <?= e($previewShort) ?>
            </p>
            <p class="concept-demo-line">
                <strong>Matched keywords:</strong> <?= e($keywordList) ?>
            </p>
            <p class="concept-demo-line muted">
                FAQs: <?= count($faqs) ?> &middot;
                Safety tips: <?= count($safetyTips) ?>
            </p>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>