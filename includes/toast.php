<?php
declare(strict_types=1);

/**
 * Stage 6.1: toast notification partial.
 *
 * Reads success/error messages from the flash store and renders them as
 * toasts via toast.js. The messages are serialised into a data attribute
 * (not an inline script) so they are compatible with the site CSP
 * (script-src 'self').
 *
 * Include this near the end of the page body, after the flash keys have
 * been set. Callers that already render flash() inline (student/admin
 * portals) should NOT include this file to avoid double display.
 */

$toastMessages = [];

$toastSuccess = flash('success');
if ($toastSuccess !== null && $toastSuccess !== '') {
    $toastMessages[] = ['type' => 'success', 'message' => (string) $toastSuccess];
}

$toastError = flash('error');
if ($toastError !== null && $toastError !== '') {
    $toastMessages[] = ['type' => 'error', 'message' => (string) $toastError];
}

$toastPayload = json_encode(
    $toastMessages,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<div
    id="toast-container"
    class="toast-container"
    aria-live="polite"
    aria-atomic="false"
    data-messages="<?= e((string) $toastPayload) ?>"
></div>