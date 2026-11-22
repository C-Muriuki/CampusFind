document.addEventListener("DOMContentLoaded", () => {
    // Password visibility toggles: any .password-toggle button reveals/hides the
    // input referenced by its data-target attribute.
    document.querySelectorAll(".password-toggle").forEach((button) => {
        const targetId = button.getAttribute("data-target");

        if (!targetId) {
            return;
        }

        const input = document.getElementById(targetId);

        if (!input) {
            return;
        }

        button.addEventListener("click", () => {
            // If the field is currently hidden (password), this click will show it.
            const willShow = input.type === "password";

            input.type = willShow ? "text" : "password";

            button.setAttribute(
                "aria-label",
                willShow ? "Hide password" : "Show password"
            );

            button.setAttribute("aria-pressed", String(willShow));

            // Show the "eye" glyph while hidden, the "eye-off" glyph while visible.
            const icons = button.querySelectorAll("[data-icon-show], [data-icon-hide]");

            icons.forEach((icon) => {
                const isShow = icon.hasAttribute("data-icon-show");
                const visible = willShow ? !isShow : isShow;
                icon.style.display = visible ? "" : "none";
            });
        });
    });

    // Password-match hint for "confirm password" fields.
    document.querySelectorAll("[data-match]").forEach((confirm) => {
        const sourceId = confirm.getAttribute("data-match");

        if (!sourceId) {
            return;
        }

        const source = document.getElementById(sourceId);

        if (!source) {
            return;
        }

        const hint = document.getElementById(confirm.getAttribute("aria-describedby") || "");

        const update = () => {
            if (!hint || confirm.value === "") {
                if (hint) {
                    hint.textContent = "";
                    hint.className = "field-hint";
                }
                return;
            }

            if (confirm.value === source.value) {
                hint.textContent = "Passwords match";
                hint.className = "field-hint is-match";
            } else {
                hint.textContent = "Passwords do not match";
                hint.className = "field-hint is-mismatch";
            }
        };

        confirm.addEventListener("input", update);
        source.addEventListener("input", update);
    });
});