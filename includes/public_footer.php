<?php
declare(strict_types=1);

/**
 * Stage 6.2: shared public layout footer.
 *
 * Slim, non-redundant footer: it deliberately does NOT repeat the primary
 * navigation (that lives in the header). It carries only brand identity, a
 * short tagline and a utility "back to top" link, plus the copyright line.
 * Also renders the toast container so flash messages appear as toasts.
 */

$base = rtrim(BASE_URL, '/');
?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">

        <a class="brand brand-footer" href="<?= e($base) ?>/index.php"
           aria-label="CampusFind home">
            <img src="<?= e($base) ?>/assets/images/campusfind-backpack-search.png"
                 alt="" class="brand-artwork">
            <span class="brand-copy">
                <span class="brand-name">Campus<span>Find</span></span>
                <span class="brand-caption">Lost &amp; Found</span>
            </span>
        </a>

        <p class="footer-tagline">Find. Report. Reconnect.</p>

        <a class="footer-top" href="#main-content">Back to top &uarr;</a>

    </div>

    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> CampusFind &middot; Built for the Zetech University community.</p>
    </div>
</footer>

<?php require_once __DIR__ . '/toast.php'; ?>

<?php
// Floating WhatsApp contact button — visible on all public pages.
// Links directly to the team's WhatsApp chat (wa.me uses the number with no
// "+" or leading zeros). Pure HTML/CSS: no JS, so it is CSP-safe.
$waNumber = defined('SUPPORT_WHATSAPP') ? SUPPORT_WHATSAPP : '';
$waLink   = $waNumber !== '' ? 'https://wa.me/' . $waNumber : '';
?>
<?php if ($waLink !== ''): ?>
    <a class="whatsapp-float"
       href="<?= e($waLink) ?>"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="Chat with the CampusFind team on WhatsApp">
        <?= icon('whatsapp', 28) ?>
        <span class="whatsapp-float-label">Chat with us</span>
    </a>
<?php endif; ?>

</body>
</html>