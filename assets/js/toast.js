/* Stage 6.1: toast notifications.
   Reads messages from the #toast-container data-messages attribute
   (populated by includes/toast.php from the flash store) and renders
   auto-dismissing toasts. No inline scripts — CSP compatible. */

document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("toast-container");

    if (!container) {
        return;
    }

    let messages = [];

    try {
        const raw = container.getAttribute("data-messages");

        if (raw) {
            messages = JSON.parse(raw);
        }
    } catch (error) {
        return;
    }

    if (!Array.isArray(messages) || messages.length === 0) {
        return;
    }

    function dismiss(toast) {
        if (!toast || toast.classList.contains("toast-exit")) {
            return;
        }

        toast.classList.add("toast-exit");

        setTimeout(() => {
            toast.remove();
        }, 200);
    }

    messages.forEach(entry => {
        if (!entry || typeof entry.message !== "string" || entry.message === "") {
            return;
        }

        const type = entry.type === "error" ? "error" : "success";

        const toast = document.createElement("div");
        toast.className = "toast toast-" + type;
        toast.setAttribute("role", type === "error" ? "alert" : "status");

        const text = document.createElement("span");
        text.className = "toast-message";
        text.textContent = entry.message;
        toast.appendChild(text);

        const close = document.createElement("button");
        close.type = "button";
        close.className = "toast-close";
        close.setAttribute("aria-label", "Dismiss notification");
        close.textContent = "\u00D7";
        close.addEventListener("click", () => {
            dismiss(toast);
        });
        toast.appendChild(close);

        container.appendChild(toast);

        setTimeout(() => {
            dismiss(toast);
        }, 5000);
    });
});