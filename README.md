# CampusFind

**A lost-and-found website for Zetech University.** Students report items they
lost or found, upload a photo, and the community helps reunite items with their
owners. Administrators moderate every report to keep the board trustworthy.

In one sentence: *CampusFind is where lost things on campus get posted, verified,
and claimed by the right person.*

---

## Table of contents

- [What the site does](#what-the-site-does)
- [Tech stack](#tech-stack)
- [Features](#features)
- [Requirements](#requirements)
- [Run it locally](#run-it-locally)
- [Demo accounts](#demo-accounts)
- [Deploy to production](#deploy-to-production)
- [Push to GitHub](#push-to-github)
- [Project structure](#project-structure)
- [Security](#security)

---

## What the site does

Lost a wallet, keys, laptop bag or student ID on campus? Found one? CampusFind
is the notice board that connects the two.

1. **Anyone can browse** the public lost-and-found board and search/filter items.
2. **Students sign up** with their Zetech email (`@zetech.ac.ke`) and student
   number, then **post a report** — lost or found — with a photo and details.
3. **Found an item that matches your lost one?** You can **file a claim**; the
   owner/admin reviews it and approves or rejects it.
4. **Admins moderate** everything from a dashboard: approve/reject reports,
   review claims, manage users and categories, read and **reply to** contact
   messages, and export data as CSV.
5. Everyone gets **notifications**, a personal dashboard, and a light/dark theme.

The whole point is simple and safe: report → verify → claim → reunite.

---

## Tech stack

| Layer      | Technology                                                        |
|------------|-------------------------------------------------------------------|
| Language   | PHP 8.2+ (no frameworks)                                          |
| Database   | MySQL 8.0+ / MariaDB, accessed through **PDO** prepared statements |
| Front end  | HTML5, hand-written CSS3 (design tokens, light/dark themes), vanilla JavaScript |
| Icons      | Inline SVG (Lucide-style), no external icon library               |
| Fonts      | Inter (Google Fonts, self-hosted-friendly)                        |
| Dev image tool | Python 3 + Pillow (only used to generate demo photos, not required to run) |

No build step, no Node, no Composer — just PHP and a database.

---

## Features

**Accounts & access**
- Registration restricted to `@zetech.ac.ke` emails, with student number and
  data-privacy consent.
- Secure login with password hashing, "remember me" (selector/validator tokens),
  and self-service password reset.
- Change your password from the profile page; every password field (login,
  register, reset, change) has a show/hide eye toggle and a live "passwords match"
  hint on confirmation fields.
- Role separation: students and admins see different dashboards and permissions.
- Login rate limiting to slow down brute-force attempts.

**Reporting lost/found items**
- Create a report (lost **or** found) with a photo, category, location,
  incident date and description.
- Photo upload with validation: real MIME-type check, size limit, random filename,
  stored outside executable paths.
- Edit, resolve, or delete your own reports.

**Browsing & search**
- Public lost-and-found directory with **search** (keyword) and **filters**
  (lost/found, category) and pagination.
- Item detail pages with photo, status badge, and claim action.

**Claims**
- Claim a found item; owner and admins review claims (pending / approved / rejected).
- Duplicate/self-claim protection, race-safe claim handling.

**Dashboards**
- **Student:** my reports, status progress, my claims, notifications, profile,
  and **My Messages** (replies to support requests).
- **Admin:** moderation queue, all reports, claims, users, categories, contact
  submissions, activity log, and **CSV exports**.
- **Admin analytics** (all computed live from SQL): reports by status, lost vs
  found, reports over time, and a category breakdown — rendered as lightweight
  inline SVG charts (no chart library).

**Communication**
- Contact form with server-side validation. Each message is **emailed to the
  support inbox** (with the sender's address as reply-to) *and* saved to the
  database.
- Admins reply from the dashboard; the reply is **emailed to the sender** and
  also appears in the student's **My Messages** page and notifications — so the
  support loop works over both email and in-app.
- Toast notifications for flash messages.
- Information hub: FAQs, safety tips, and a live category summary.

**Polish**
- Light/dark theme, remembered via a cookie, no flash on load.
- Fully responsive (desktop, tablet, mobile).
- Subtle animations that respect `prefers-reduced-motion`.
- SEO: canonical URLs, Open Graph/Twitter cards, JSON-LD, `robots.txt`, `sitemap.xml`.

---

## Requirements

- **PHP 8.2+** with the `pdo_mysql` and `fileinfo` extensions (bundled by default
  on most installs).
- **MySQL 8.0+** or **MariaDB**.
- A web server: Apache (with `mod_rewrite` + `.htaccess`) or the PHP built-in
  server for local development.

> On a fresh PHP install you may need to enable the extensions in `php.ini`
> (e.g. `extension=pdo_mysql`, `extension=fileinfo`).

---

## Run it locally

```bash
# 1. Create the database
mysql -u root -p -e "CREATE DATABASE campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import the schema (tables only)
mysql -u root -p campusfind < database/schema.sql

# 3. Configure the database connection
cp config/database.example.php config/database.local.php
#    Then edit config/database.local.php with your MySQL host, port, user and password.

# 4. Load demo data + demo photos (optional, but recommended for a quick tour)
php database/seed_demo.php

# 5. Start the built-in PHP server
php -S localhost:8000
```

Open <http://localhost:8000>.

- Step 3 is how the app reads your DB credentials: `config/database.php` loads
  `config/database.local.php`, which you create from the example file.
  **Never commit `database.local.php`** (it is already git-ignored).
- Step 4 creates a ready-to-explore demo: students, an admin, 22 reports with
  photos, claims, and contact messages. It is safe to re-run.

### Demo accounts

| Role    | Email                     | Password      |
|---------|---------------------------|---------------|
| Admin   | `admin@zetech.ac.ke`      | `Admin@12345` |
| Student | `amina.wanjiru@zetech.ac.ke` | `DemoPass123!` |

(Other demo students use the same `DemoPass123!` password.)

---

## Deploy to production

The app auto-detects its own URL, so it works the same locally and on a real
domain. Two environment variables control production behaviour:

| Variable              | Purpose                                                        |
|-----------------------|----------------------------------------------------------------|
| `CAMPUSFIND_ENV`      | Set to `production` on a live host. Turns **off** debug output and verbose errors. |
| `CAMPUSFIND_BASE_URL` | Optional. Pin the exact public URL, e.g. `https://campusfind.zetech.ac.ke`. Auto-detected if omitted. |

**Production checklist**

1. **Set the environment** (Apache vhost, Nginx, or your host's env settings):
   ```bash
   export CAMPUSFIND_ENV=production
   export CAMPUSFIND_BASE_URL=https://your-domain.example
   ```
2. **Point the document root** at the project root (so `index.php` and `.htaccess`
   are honoured). On Apache ensure `AllowOverride All` so `.htaccess` security
   headers and caching apply.
3. **Enable HTTPS.** Secure session cookies and HSTS activate automatically on HTTPS.
4. **Configure the database** in `config/database.local.php` with the production
   credentials (keep this file off the repo and readable only by the web user).
5. **Create the DB and tables** on the server:
   ```bash
   mysql -u root -p -e "CREATE DATABASE campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p campusfind < database/schema.sql
   ```
   Optionally load demo data with `php database/seed_demo.php`, or create your own
   admin account. **Remove demo accounts before real use.**
6. **Upload directory** `uploads/reports/` must be writable by the web server
   (`chmod` as appropriate). The included `.htaccess` blocks script execution there.
7. **Enable OPcache** in `php.ini` for performance (`opcache.enable=1`).
8. **Set up backups** — `database/backup.sh` is provided as a starting point.
9. **Mail:** password reset, contact-message notifications, and admin replies all
   use PHP's `mail()`, so a working MTA (sendmail/Postfix) must be configured on
   the server for those emails to send. Set `CAMPUSFIND_MAIL_DRIVER=log` to write
   them to the error log instead (useful in development).

That's it — with `CAMPUSFIND_ENV=production` the app already suppresses debug
output, sets secure headers, and serves correctly on HTTPS.

---

## Push to GitHub

```bash
# from the project root
git init                     # if not already a repo
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/<your-username>/CampusFind.git
git push -u origin main
```

The `.gitignore` already excludes everything that should **not** be pushed:
`config/database.local.php` (secrets), user uploads, logs, Python caches, the
`doc/` and `documentation/` folders, and the `.docx` assignment file. Only the
application code, schema, demo-image fixtures, and this `README.md` are tracked.

---

## Project structure

```
CampusFind/
├── index.php                # Homepage
├── items.php                # Redirects to the public board
├── robots.php               # Dynamic robots.txt
├── sitemap.php              # Dynamic XML sitemap
├── health.php               # JSON health check endpoint
├── .htaccess                # Security headers, static-file caching
├── config/                  # app.php (constants/env), database.php (PDO),
│                            #   database.example.php (template)
├── includes/                # bootstrap, functions, guards, layout templates,
│                            #   SEO, icons, charts, toasts, upload security
├── auth/                    # login, register, logout, forgot/reset password
├── pages/                   # Public: items, item detail, contact, information,
│                            #   about, privacy
├── student/                 # Student portal: dashboard, reports, claims,
│                            #   my-messages, profile, notifications
├── admin/                   # Admin portal: dashboard, reports, claims, users,
│                            #   categories, contact submissions, exports, logs
├── assets/
│   ├── css/                 # style, responsive, student, admin, auth, toast
│   ├── js/                  # theme, app, auth (password toggle), student, admin, toast
│   └── images/              # Brand/backpack artwork
├── database/
│   ├── schema.sql           # All tables (DDL)
│   ├── seed_demo.php        # Optional demo data + demo photos
│   ├── demo-images/         # Demo photos copied into uploads on seed
│   └── backup.sh            # Example backup script
├── uploads/                 # User/report images (git-ignored; .htaccess blocks exec)
├── logs/                    # Activity log (git-ignored)
└── tests/                   # Auth tests, route-matrix test, full-system harness
```

> **Run the automated tests** before deploying (needs the local server on
> `localhost:8000` and the demo database):
>
> ```bash
> php tests/auth_test.php          # auth: register/login/reset (29 assertions)
> bash tests/route_matrix_test.sh  # every route + CSRF (36 checks)
> bash tests/full_system_test.sh   # end-to-end: pages, auth, report create,
>                                  #   contact loop, password change, admin,
>                                  #   CSV export, CSRF, production sim (52 checks)
> ```
>
> `full_system_test.sh` is non-destructive — it creates temporary `AUTOTEST`
> rows and removes them, and restores the demo student password.

### How it fits together

- **No framework, no front controller:** every `.php` file is a directly reachable
  page. Shared layout lives in `includes/` (public/student/admin templates).
- **Database access** goes through one PDO connection (`db()` in
  `includes/functions.php`); every query is a prepared statement.
- **URLs are built from `BASE_URL`**, which the app works out automatically from
  the current host — so links, images, and redirects are correct both locally and
  in production with zero configuration.

---

## Security

- All output is escaped (`htmlspecialchars`) to prevent XSS.
- Every state-changing form requires a CSRF token.
- PDO prepared statements everywhere — no SQL injection.
- Passwords hashed with `password_hash()`; login rate limiting; session id
  regenerated on login; HttpOnly/SameSite cookies, Secure under HTTPS.
- Uploads validated by real MIME type, size-limited, randomly named, and stored
  where they cannot be executed.
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
  HSTS) and sensible cache policies.

---

## Notes

- Light mode is the first-visit default; dark mode is optional and remembered.
- The Inter font loads from Google Fonts; the CSP already allows it. For fully
  offline hosting, self-host the font and update the CSP `font-src`/`style-src`.
- Demo data is for evaluation only — remove or change the seeded credentials
  before using the site for real.