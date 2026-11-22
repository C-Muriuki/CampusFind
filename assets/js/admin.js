document.addEventListener('DOMContentLoaded', () => {
    const confirmationForms = document.querySelectorAll('form[data-confirm]');

    confirmationForms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm ||
                'Are you sure you want to continue?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reduceMotion) {
        document.querySelectorAll('.bar-fill[data-width]').forEach((bar) => {
            const target = bar.getAttribute('data-width') || '0';
            bar.style.width = '0%';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    bar.style.width = target + '%';
                });
            });
        });

        document.querySelectorAll('.type-seg').forEach((seg) => {
            const grow = seg.style.flexGrow;
            seg.style.flexGrow = '0';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    seg.style.flexGrow = grow;
                });
            });
        });
    }
});