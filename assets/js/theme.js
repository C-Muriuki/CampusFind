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

/* Mobile navigation: hamburger toggles a collapsible menu (admin drawer +
   student dropdown). Works for any `.menu-toggle` whose aria-controls points
   at the collapsible element. Closes on link click, backdrop click, Escape
   and resize. */
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".menu-toggle").forEach(button => {
        const target = document.getElementById(
            button.getAttribute("aria-controls") || ""
        );

        if (!target) {
            return;
        }

        const backdrop =
            document.querySelector(".nav-backdrop");

        const setOpen = open => {
            button.setAttribute(
                "aria-expanded",
                String(open)
            );

            button.setAttribute(
                "aria-label",
                open ? "Close navigation" : "Open navigation"
            );

            target.classList.toggle("is-open", open);
            document.body.classList.toggle("nav-open", open);

            if (backdrop) {
                backdrop.classList.toggle("is-visible", open);
            }
        };

        const isOpen = () =>
            button.getAttribute("aria-expanded") === "true";

        button.addEventListener("click", () => {
            setOpen(!isOpen());
        });

        target.querySelectorAll("a").forEach(link => {
            link.addEventListener("click", () => setOpen(false));
        });

        if (backdrop) {
            backdrop.addEventListener("click", () => setOpen(false));
        }

        document.addEventListener("keydown", event => {
            if (event.key === "Escape") {
                setOpen(false);
            }
        });

        window.addEventListener("resize", () => {
            setOpen(false);
        });
    });
});