/* Command palette (⌘K / Ctrl+K).
   Self-contained: injects a floating trigger + modal, filters role-aware
   navigation links, and offers a live "search items" action. */
(function () {
    "use strict";

    var BASE = (document.documentElement.getAttribute("data-base-url") || "")
        .replace(/\/+$/, "");

    var ICON = {
        search:
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<circle cx="11" cy="11" r="7"></circle>' +
            '<path d="m20 20-3.2-3.2"></path></svg>',
        page:
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z">' +
            '</path><path d="M14 3v5h5"></path></svg>',
        theme:
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path></svg>'
    };

    function navSet() {
        var admin = [
            ["Dashboard", "/admin/dashboard.php", ""],
            ["Report moderation", "/admin/reports.php", ""],
            ["Item claims", "/admin/claims.php", ""],
            ["Student accounts", "/admin/users.php", ""],
            ["Categories", "/admin/categories.php", ""],
            ["Contact submissions", "/admin/contact-submissions.php", ""],
            ["Activity logs", "/admin/activity-logs.php", ""],
            ["Data exports", "/admin/exports.php", ""],
            ["View website", "/index.php", ""]
        ];
        var student = [
            ["Dashboard", "/student/dashboard.php", ""],
            ["My Reports", "/student/my-reports.php", ""],
            ["Browse Items", "/student/browse.php", ""],
            ["My Claims", "/student/my-claims.php", ""],
            ["Report Item", "/student/report-create.php", ""],
            ["Notifications", "/student/notifications.php", ""],
            ["My Messages", "/student/my-messages.php", ""],
            ["Profile", "/student/profile.php", ""],
            ["Browse all items", "/pages/items.php", "Public"]
        ];
        var pub = [
            ["Home", "/index.php", ""],
            ["About", "/pages/about.php", ""],
            ["Browse Items", "/pages/items.php", ""],
            ["Information", "/pages/information.php", ""],
            ["Contact Us", "/pages/contact.php", ""],
            ["Login", "/auth/login.php", ""],
            ["Register", "/auth/register.php", ""]
        ];

        var links, group;
        if (document.querySelector(".admin-sidebar")) {
            links = admin;
            group = "Admin";
        } else if (document.querySelector(".student-header")) {
            links = student;
            group = "Student";
        } else {
            links = pub;
            group = "Site";
        }

        return links.map(function (item) {
            return {
                label: item[0],
                href: BASE + item[1],
                hint: item[2],
                icon: ICON.page
            };
        }).concat([{
            label: "Toggle dark mode",
            href: null,
            hint: "Theme",
            icon: ICON.theme,
            action: function () {
                var toggle = document.querySelector(".theme-toggle");
                if (toggle) {
                    toggle.click();
                }
            }
        }]);
    }

    function build() {
        if (document.getElementById("palette-root")) {
            return;
        }

        var trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "palette-trigger";
        trigger.setAttribute("aria-haspopup", "dialog");
        trigger.setAttribute("aria-label", "Open quick navigation");
        trigger.innerHTML =
            ICON.search +
            "<span>Quick nav</span>" +
            "<kbd>\u2318K</kbd>";

        var overlay = document.createElement("div");
        overlay.className = "palette-overlay";
        overlay.id = "palette-root";
        overlay.innerHTML =
            '<div class="palette" role="dialog" aria-modal="true" ' +
            'aria-label="Quick navigation">' +
            '<div class="palette-input-wrap">' +
            ICON.search +
            '<input class="palette-input" type="text" ' +
            'placeholder="Search pages or items\u2026" ' +
            'aria-label="Search pages or items" autocomplete="off">' +
            '<span class="palette-esc">Esc</span>' +
            "</div>" +
            '<div class="palette-list"></div>' +
            "</div>";

        document.body.appendChild(trigger);
        document.body.appendChild(overlay);

        var input = overlay.querySelector(".palette-input");
        var list = overlay.querySelector(".palette-list");
        var nav = navSet();
        var activeIndex = 0;
        var current = [];

        function open() {
            overlay.classList.add("is-open");
            input.value = "";
            render("");
            setTimeout(function () {
                input.focus();
            }, 20);
        }

        function close() {
            overlay.classList.remove("is-open");
        }

        function isActive() {
            return overlay.classList.contains("is-open");
        }

        function render(query) {
            var q = query.trim().toLowerCase();
            current = [];

            nav.forEach(function (item) {
                if (!q || item.label.toLowerCase().indexOf(q) !== -1) {
                    current.push(item);
                }
            });

            // Fall back to a live item search when the query matches no page.
            if (q) {
                current.push({
                    label: 'Search items for "' + query.trim() + '"',
                    href: BASE + "/pages/items.php?search=" +
                        encodeURIComponent(query.trim()),
                    hint: "Enter",
                    icon: ICON.search
                });
            }

            list.innerHTML = "";

            if (current.length === 0) {
                var empty = document.createElement("div");
                empty.className = "palette-empty";
                empty.textContent = "No matches found.";
                list.appendChild(empty);
                activeIndex = 0;
                return;
            }

            if (activeIndex >= current.length) {
                activeIndex = current.length - 1;
            }
            if (activeIndex < 0) {
                activeIndex = 0;
            }

            current.forEach(function (item, index) {
                var row = document.createElement("div");
                row.className = "palette-item" +
                    (index === activeIndex ? " is-active" : "");
                row.innerHTML =
                    item.icon +
                    "<span></span>" +
                    (item.hint
                        ? '<span class="palette-item-hint">' +
                          item.hint + "</span>"
                        : "");
                row.querySelector("span").textContent = item.label;

                row.addEventListener("mouseenter", function () {
                    activeIndex = index;
                    highlight();
                });

                row.addEventListener("click", function () {
                    choose(index);
                });

                list.appendChild(row);
            });
        }

        function highlight() {
            var rows = list.querySelectorAll(".palette-item");
            rows.forEach(function (row, index) {
                row.classList.toggle("is-active", index === activeIndex);
            });
            var active = rows[activeIndex];
            if (active && active.scrollIntoView) {
                active.scrollIntoView({ block: "nearest" });
            }
        }

        function choose(index) {
            var item = current[index];
            if (!item) {
                return;
            }
            if (typeof item.action === "function") {
                close();
                item.action();
            } else if (item.href) {
                window.location.href = item.href;
            }
        }

        trigger.addEventListener("click", open);

        input.addEventListener("input", function () {
            activeIndex = 0;
            render(input.value);
        });

        input.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown") {
                event.preventDefault();
                activeIndex = Math.min(activeIndex + 1, current.length - 1);
                highlight();
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                highlight();
            } else if (event.key === "Enter") {
                event.preventDefault();
                choose(activeIndex);
            } else if (event.key === "Escape") {
                close();
            }
        });

        overlay.addEventListener("click", function (event) {
            if (event.target === overlay) {
                close();
            }
        });

        document.addEventListener("keydown", function (event) {
            if ((event.metaKey || event.ctrlKey) &&
                    event.key.toLowerCase() === "k") {
                event.preventDefault();
                if (isActive()) {
                    close();
                } else {
                    open();
                }
            } else if (event.key === "Escape" && isActive()) {
                close();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", build);
    } else {
        build();
    }
})();