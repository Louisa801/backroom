# Backroom

A second-hand clothing shop with a coffee bar up front and the stock in a back-room archive. Customers browse the archive on tablets and ask staff to bring items to a fitting room. See [SPEC.md](SPEC.md) for what the MVP does.

## Demo login

The staff and manager screens need a login; the customer tablet (`/`) doesn't. Log in at `/staff/login.php`, or just open `/staff/` or `/manager/` and you are sent there and back.

| Email | Password |
|---|---|
| `staff@backroom.test` | `backroom-demo` |

This user comes from `sql/seed.sql`. It is for local development and the demo only.

- After 5 wrong attempts for an email, wait 1 minute before trying again.
- You are logged out after 8 hours without activity, or with "Log out" in the header.
- Every staff member can also see the dashboard (one role in the MVP).

## Run locally

Needs PHP 8.3, PostgreSQL 17, Composer and Node.js.

PHP extensions: `pdo_pgsql`, `gd` (JPEG, PNG, WebP), `exif`, `fileinfo`, `mbstring`. In `php.ini`: `upload_max_filesize = 8M`, `post_max_size = 90M`, `memory_limit = 256M` (needed for buy-in photo uploads).

1. Install PHP dependencies (PHPUnit): `composer install`
2. Create a database and set `DATABASE_URL`, for example
   `postgresql://backroom:YOURPASSWORD@localhost:5432/backroom`.
3. Reset the database (tables + demo data):
   - PowerShell: `psql $env:DATABASE_URL -f sql/schema.sql -f sql/seed.sql`
   - bash: `psql "$DATABASE_URL" -f sql/schema.sql -f sql/seed.sql`
4. Start the site: `php -S localhost:8000 -t public`

## Tests

Unit tests (PHPUnit). The database tests use `DATABASE_URL` and roll back everything they insert:

- `vendor/bin/phpunit`

`StaffAccessTest` starts its own PHP server on a free port and checks over HTTP that every staff and manager page needs a login, and that the customer pages stay public.

Database constraint tests check that the database refuses bad data (negative prices, unknown statuses, fitting room 4, and so on). Run them right after a reset; each test is rolled back, so the data is unchanged. Every line should say `PASS`; a failure stops the run with an error.

- PowerShell: `psql $env:DATABASE_URL -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql`
- bash: `psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql`

The demo data uses dates relative to the moment you reset, so the dashboard always shows figures for today and this week. Reset again before a demo.

## Demo data at a glance

- 40 items in 5 categories: 27 in the archive (5 of them slow movers), 3 requested, 3 in a fitting room, 7 sold.
- 7 try-on requests: one each `new`, `fetching` and `ready`, plus 4 `closed` this week (7 of 16 tried items bought).
- Item photos are placeholder SVGs in `public/assets/uploads/seed/`.
