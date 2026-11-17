# CLAUDE.md — project rules for Backroom

Read `SPEC.md` before every task. It is the source of truth for what to build. If a request conflicts with SPEC.md, ask first.

## Stack (fixed)
- PHP 8.3, plain PHP: no framework.
- PostgreSQL 17 through PDO.
- HTML, CSS and vanilla JavaScript (`fetch` for live updates). No front-end framework, no build step.
- Hosting: Render (Docker web service, `php:8.3-apache`) with a PostgreSQL 17 database (same version as local). Config comes from the `DATABASE_URL` environment variable.
- Uploaded item photos are stored in the database (`photos` table) and served by `public/photo.php?id=`, because Render's free tier loses files on every restart. Only the seed placeholder SVGs are static files.
- PHP extensions needed: `pdo_pgsql`, `gd` (JPEG, PNG, WebP), `exif`, `fileinfo`, `mbstring`. `php.ini`: `upload_max_filesize = 8M`, `post_max_size = 90M`, `memory_limit = 256M`. The Docker image must install and set the same.

## Folder structure
```
public/            web root: only files here are reachable
  index.php        customer: archive
  item.php         customer: item detail
  request.php      customer: request status
  photo.php        serves an uploaded item photo from the database
  api/             JSON endpoints used by the JS
  staff/           staff screens (login required)
  manager/         dashboard (login required)
  assets/          css/, js/, uploads/seed/ (placeholder photos of the demo data)
src/               PHP logic, no HTML
  bootstrap.php    loaded by every page/API file: error handling, includes
  db.php           PDO connection
  helpers.php      e(), format_price(), json_response()
  auth.php         login, session, CSRF
  Photos.php       check, resize and store item photos
  Items.php, Requests.php, Intake.php, Sales.php, Dashboard.php
templates/         shared header/footer and other partials
sql/
  schema.sql       tables, keys, constraints
  seed.sql         realistic demo data
tests/
  db/              SQL tests of the database constraints
  unit/            PHPUnit tests of src/
  e2e/             Playwright tests of the critical flows
  MANUAL_CHECKLIST.md
docs/              data model diagram, screenshots
```

## Commands
- Run locally: `php -S localhost:8000 -t public`
- Reset database:
  - bash: `psql "$DATABASE_URL" -f sql/schema.sql -f sql/seed.sql`
  - PowerShell: `psql $env:DATABASE_URL -f sql/schema.sql -f sql/seed.sql`
- Database constraint tests (on a freshly reset database; every test is rolled back):
  - bash: `psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql`
  - PowerShell: `psql $env:DATABASE_URL -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql`
- Unit tests: `vendor/bin/phpunit`
- End-to-end tests: `npx playwright test`

## Code rules
- Business logic lives in `src/`, never in page files. Pages only read input, call `src/`, and render.
- Always use prepared statements. Never put user input in SQL strings.
- Escape all output with the `e()` helper (`htmlspecialchars`).
- Forms that change data use POST and a CSRF token.
- Passwords stored with `password_hash`, checked with `password_verify`.
- Status changes go through one function per transition, inside a transaction, and check the current status first (so two tablets can't request the same item).
- Money is stored as `NUMERIC(8,2)` in euros.
- Validate on the server; client-side checks are only for convenience.
- Show errors to the user in plain language; never show PHP errors or stack traces.

## UI rules
- Same look as the Assignment 1 deck: warm off-white `#F5EFE4`, dark brown `#2B211C`, terracotta accent `#A9472A`. Headings in Fraunces, text in DM Sans.
- Mobile first: works at 375px wide. Tap targets at least 44px.
- Text at least 16px, contrast at least 4.5:1 (WCAG AA).
- Every form field has a label. Every image has alt text.
- Consistent spacing scale: 4, 8, 16, 24, 32, 48px.

## Way of working
1. Build one user story at a time, in this order: database → M1 → M5 → M2/M3 → M4 → M6 → M7 → login.
2. Before coding, state the plan in a few lines. Keep changes small.
3. Write or update the unit tests with the change. Run all tests before saying a task is done.
4. Do not add features that are not in SPEC.md.
5. After each story: Louisa checks it by hand, then commit with a message like `M1: browse and filter archive`.
