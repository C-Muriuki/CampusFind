TODO - CampusFind nav + dark-mode hardening
[x] Create assets/js/nav.js (single owner of all .menu-toggle: public #primary-navigation, student #studentNav, admin #adminSidebar + .nav-backdrop; aria-expanded/label, Escape, backdrop click, link click, resize-close)
[x] Strip mobile-menu block from assets/js/theme.js (keep theme apply/toggle only)
[x] Remove app.js menu handling / app.js script tag from public_header.php (nav.js owns public nav)
[x] Load nav.js in includes/{public,student,admin}_header.php
[x] CSS: admin sidebar fixed dark navy bg in both themes + legible foreground
[x] CSS: dark-mode hover fix on admin dashboard (remove white-on-white hover, fix text)
[x] CSS: dark-mode text legibility pass (size/weight/brightness) across admin.css, student.css, style.css, stage8-polish.css
[x] CSS: shared mobile polish block (overflow-wrap, bottom space for palette, overflow-x, no overlap)
[x] Lint: node --check JS, php -l PHP, CSS brace balance
[x] Deploy changed files via Git push -> GitHub Actions -> InfinityFree (verified live: theme.js 79 lines, admin.css + student.css + stage8-polish.css dark overrides deployed), verify live HTTP 200 + nav + dark text
[x] Committed c09070d + pushed to origin/main; deploy verified live

[x] Card containment fix committed (73970a2) + pushed to origin/main; live on InfinityFree