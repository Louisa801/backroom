# Backroom

A second-hand clothing shop with a coffee bar up front and the stock in a back-room archive. Customers browse the archive on tablets and ask staff to bring items to a fitting room. See [SPEC.md](SPEC.md) for what the MVP does.

## Demo login

The staff and manager screens need a login; the customer tablet (`/`) doesn't. Log in at `/staff/login.php`, or just open `/staff/` or `/manager/` and you are sent there and back.

| Name | Email | Password |
|---|---|---|
| Louisa | `staff@backroom.test` | `backroom-demo` |

This user comes from `sql/seed.sql`. It is for local development and the demo only. The staff header shows "Louisa" next to "Log out".

**On Render the password is different:** it is changed right after loading the demo data (see "Deploy to Render", step 4) and is not written anywhere in this repository.

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

### End-to-end tests (Playwright)

`tests/e2e/` clicks through the critical flows in a real browser (the Microsoft Edge installed on Windows, so no browser download):

| File | Flow |
|---|---|
| `customer.spec.js` | Filter the archive, open an item, put 2 items on the try-on list, send them to a fitting room, land on the status page. Also at a 375px phone size. |
| `staff.spec.js` | A customer sends 2 items; staff log in, start fetching, mark ready (the customer page updates by itself), sell one, put one back; the request closes, the archive is right. |
| `buyin.spec.js` | A buy-in with a real photo appears in the archive with that photo. |
| `two-tablets.spec.js` | Two tablets want the same item: one gets it, the other is told it was just taken. |
| `login.spec.js` | A staff page without a login goes to the login page and back afterwards; a wrong password shows a plain message. |

The tests never touch your own data. They use a separate test database, reset with `schema.sql` + `seed.sql` before **every** test, so each test runs on its own and in any order. Playwright starts its own PHP server on port 8100 with that database (your `localhost:8000` keeps running).

One-time setup (PowerShell):

```powershell
createdb -U postgres -h localhost -O backroom backroom_test
[Environment]::SetEnvironmentVariable('TEST_DATABASE_URL', 'postgresql://backroom:YOURPASSWORD@localhost:5432/backroom_test', 'User')
npm install
```

Restart VS Code after setting the variable. The test database name must end in `_test`; the tests refuse to run against `DATABASE_URL`.

Run (PowerShell):

- all tests: `npx playwright test`
- one file: `npx playwright test tests/e2e/customer.spec.js`
- see the HTML report (with screenshots and traces of failures): `npx playwright show-report`

The demo data uses dates relative to the moment you reset, so the dashboard always shows figures for today and this week. Reset again before a demo.

## Deploy to Render

The app runs on [Render](https://render.com) as a **Docker web service** (the `Dockerfile` in this repo: PHP 8.3 with Apache, web root `public/`) with a **Render PostgreSQL 17** database. Both on the free plan, in the same region. All commands below are for PowerShell on your laptop.

What the image already takes care of:
- **PHP:** `pdo_pgsql`, GD (JPEG, PNG and WebP), EXIF, and the upload and memory limits from `docker/php.ini`.
- **Port:** Apache listens on the port Render gives in `PORT` (10000 by default).
- **HTTPS:** Render handles HTTPS. The app sees it through the `X-Forwarded-Proto` header, so the session cookie gets the Secure flag.
- **Time zone:** the server runs in UTC. "Today" and "this week" on the dashboard are still Porto time (`Europe/Lisbon`), worked out in `src/Dashboard.php`.
- **Photos:** the seed placeholder photos (`public/assets/uploads/seed/`) are in git and in the image. Uploaded photos are stored in the database, so a restart doesn't lose them.

### 1. Push the code

Render builds from GitHub, so first make sure `main` is pushed:

```powershell
git push
```

### 2. Create the database

1. In the Render dashboard: **New → Postgres**.
2. Fill in:
   - **Name:** `backroom-db`
   - **Database:** `backroom`
   - **User:** `backroom`
   - **Region:** **Frankfurt (EU Central)**
   - **PostgreSQL version:** **17**
   - **Plan:** **Free**
3. **Create Database**, and wait until its status is *Available*.
4. On the database page, under **Connections**, copy two addresses:
   - **Internal Database URL**, for the web service (step 5)
   - **External Database URL**, for loading data from your laptop (steps 3 and 4).

### 3. Load the tables and the demo data

From the repo folder on your laptop. Render requires SSL from outside, hence `?sslmode=require`:

```powershell
$env:RENDER_DATABASE_URL = '<paste the External Database URL here>?sslmode=require'
psql $env:RENDER_DATABASE_URL -v ON_ERROR_STOP=1 -q -f sql/schema.sql -f sql/seed.sql
psql $env:RENDER_DATABASE_URL -c "SELECT count(*) AS items FROM items"
```

The last command should show `40`. `RENDER_DATABASE_URL` only lives in this PowerShell window and is never saved in the repo.

### 4. Change the staff password (always, right after step 3)

`seed.sql` creates the demo login `staff@backroom.test` / `backroom-demo`, and that password is public in this README. On a public site, replace it straight away. Run this in the same PowerShell window:

```powershell
$secure = Read-Host 'New staff password' -AsSecureString
$env:NEW_STAFF_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
$hash = php -r 'echo password_hash(getenv(''NEW_STAFF_PASSWORD''), PASSWORD_DEFAULT);'
psql $env:RENDER_DATABASE_URL -c "UPDATE staff_users SET password_hash = '$hash' WHERE email = 'staff@backroom.test'"
Remove-Item Env:NEW_STAFF_PASSWORD; Remove-Variable secure, hash
```

It should print `UPDATE 1`. The password is typed without being shown and doesn't end up in your PowerShell history or in the repo; only its hash goes to the database. It goes to PHP through a temporary environment variable, removed straight after, because PowerShell 5.1 can damage quotes when it passes text to another program as an argument.

> **Warning:** loading `seed.sql` again (step 3) **resets the password to the public demo password** `backroom-demo`. Every time you reload the demo data on Render, for example before a demo so the dates are fresh, do step 4 again straight after.

### 5. Create the web service

1. In the Render dashboard: **New → Web Service**, connect GitHub if asked, and choose the repository `Louisa801/backroom`.
2. Fill in:
   - **Name:** `backroom`
   - **Language:** **Docker**
   - **Branch:** `main`
   - **Region:** **Frankfurt (EU Central)**. It must be the same region as the database, otherwise the internal URL doesn't work.
   - **Instance type:** **Free**
   - **Dockerfile path:** leave the default (`./Dockerfile`).
3. Under **Environment Variables**, add `DATABASE_URL` with the **Internal Database URL** from step 2. It needs no `sslmode`; the connection stays inside Render.
4. **Create Web Service.** The first build takes a few minutes; the log ends with Apache starting.

### 6. Check

Open the `https://backroom-….onrender.com` address Render shows:

1. **Archive (`/`):** 27 items with placeholder photos.
2. **`/staff/login.php`:** log in with `staff@backroom.test` and the new password from step 4. The old demo password must be refused.
3. **Queue, buy-in and dashboard:** they work. A buy-in with a real photo shows its photo in the archive.

### Updating and good to know

- **Updates:** every `git push` to `main` makes Render build and deploy again (Auto-Deploy). The database and its data stay.
- **Errors:** PHP errors appear in the web service's **Logs** on Render, never on the page.
- **Free web service:** it falls asleep after about 15 minutes without visitors, so the first page after that takes up to a minute.
- **Free database:** it has an expiry date (shown on the database page). Upgrade it or make a new one before then.
- **Before a demo:** the demo data uses dates relative to the moment it's loaded. To get fresh "today" and "this week" figures, repeat step 3 **and then step 4**.
- **Local Docker test:** Docker isn't needed for Render. To test the image on a PC with Docker Desktop, run `docker build -t backroom .` and then `docker run --rm -p 10000:10000 -e DATABASE_URL=postgresql://backroom:YOURPASSWORD@host.docker.internal:5432/backroom backroom`, and open http://localhost:10000. Inside the container, `localhost` would be the container itself, which is why the database host is `host.docker.internal`.

## Demo data at a glance

- 40 items in 5 categories: 27 in the archive (5 of them slow movers), 3 requested, 3 in a fitting room, 7 sold.
- 7 try-on requests: one each `new`, `fetching` and `ready`, plus 4 `closed` this week (7 of 16 tried items bought).
- Item photos are placeholder SVGs in `public/assets/uploads/seed/`.
