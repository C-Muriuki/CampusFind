document.addEventListener("DOMContentLoaded", () => {
    const root = document.documentElement;

    const themeButtons =
        document.querySelectorAll(".theme-toggle");

    function readTheme() {
        const cookie = document.cookie
            .split("; ")
            .find(item =>
                item.startsWith("campusfind_theme=")
            );

        if (!cookie) {
            return "light";
        }

        const value =
            decodeURIComponent(cookie.split("=")[1]);

        return value === "dark" ? "dark" : "light";
    }

    function saveTheme(theme) {
        const secure =
            window.location.protocol === "https:"
                ? "; Secure"
                : "";

        document.cookie =
            `campusfind_theme=${theme}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    }

    function applyTheme(theme) {
        root.setAttribute("data-theme", theme);

        themeButtons.forEach(button => {
            const dark = theme === "dark";

            button.textContent =
                dark ? "Light mode" : "Dark mode";

            button.setAttribute(
                "aria-label",
                dark
                    ? "Switch to light mode"
                    : "Switch to dark mode"
            );

            button.setAttribute(
                "aria-pressed",
                String(dark)
            );
        });

        const themeColor = document.querySelector(
            'meta[name="theme-color"]'
        );

        if (themeColor) {
            themeColor.setAttribute(
                "content",
                theme === "dark" ? "#080F2B" : "#FFFFFF"
            );
        }
    }

    applyTheme(readTheme());

    themeButtons.forEach(button => {
        button.addEventListener("click", () => {
            const current =
                root.getAttribute("data-theme") || "light";

            const next =
                current === "dark" ? "light" : "dark";

            saveTheme(next);
            applyTheme(next);
        });
    });
});