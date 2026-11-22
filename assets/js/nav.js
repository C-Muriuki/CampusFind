/* assets/js/nav.js — Single mobile navigation controller.
   Owns every `.menu-toggle` whose `aria-controls` points at a collapsible nav:
   - public   #primary-navigation  (breakpoint 680px)
   - student  #studentNav          (breakpoint 900px)
   - admin    #adminSidebar        (breakpoint 800px) + .nav-backdrop

   One instance per button, so theme.js (colour theme only) and palette.js
   (command palette) never fight over the hamburger. Closes on link click,
   backdrop click, Escape and resize-above-breakpoint. */
document.addEventListener("DOMContentLoaded", () => {
    const buttons = document.querySelectorAll(".menu-toggle");
    if (!buttons.length) {
        return;
    }

    const instances = [];

    buttons.forEach((button) => {
        if (button.dataset.navBound) {
            return; // guard against double binding
        }

        const target = document.getElementById(
            button.getAttribute("aria-controls") || ""
        );
        if (!target) {
            return;
        }

        const backdrop = document.querySelector(".nav-backdrop");
        const breakpoint = parseInt(
            button.getAttribute("data-breakpoint") || "0",
            10
        );

        let open = false;

        function setOpen(next) {
            open = next;
            button.setAttribute("aria-expanded", String(open));
            button.setAttribute(
                "aria-label",
                open ? "Close navigation" : "Open navigation"
            );
            target.classList.toggle("is-open", open);
            document.body.classList.toggle("nav-open", open);
            if (backdrop) {
                backdrop.classList.toggle("is-visible", open);
            }
        }

        button.dataset.navBound = "1";
        instances.push({ setOpen, isOpen: () => open, breakpoint });

        button.addEventListener("click", () => setOpen(!open));

        target.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => setOpen(false));
        });

        if (backdrop) {
            backdrop.addEventListener("click", () => setOpen(false));
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }
        instances.forEach((inst) => {
            if (inst.isOpen()) {
                inst.setOpen(false);
            }
        });
    });

    window.addEventListener("resize", () => {
        instances.forEach((inst) => {
            if (
                inst.breakpoint &&
                window.innerWidth > inst.breakpoint &&
                inst.isOpen()
            ) {
                inst.setOpen(false);
            }
        });
    });
});
