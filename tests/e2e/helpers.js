// Shared helpers for the end-to-end tests.
//
// The tests only ever use the database in TEST_DATABASE_URL. It is reset with
// sql/schema.sql + sql/seed.sql before EVERY test, so the tests are independent of
// each other and of the order they run in. Your own database (DATABASE_URL) is never touched.

const { execFileSync } = require('node:child_process');
const path = require('node:path');
const base = require('@playwright/test');

const ROOT = path.resolve(__dirname, '..', '..');
const DEMO_EMAIL = 'staff@backroom.test';
const DEMO_PASSWORD = 'backroom-demo';

/** The test database URL, after checking it can't be the real one. */
function testDatabaseUrl() {
  const url = process.env.TEST_DATABASE_URL || '';
  if (url === '') {
    throw new Error('TEST_DATABASE_URL is not set. See README: "End-to-end tests".');
  }
  const name = new URL(url).pathname.replace(/^\//, '');
  if (!name.endsWith('_test')) {
    throw new Error(`Refusing to run: the test database name must end in "_test" (got "${name}").`);
  }
  if (process.env.DATABASE_URL && new URL(process.env.DATABASE_URL).pathname === new URL(url).pathname
      && new URL(process.env.DATABASE_URL).host === new URL(url).host) {
    throw new Error('Refusing to run: TEST_DATABASE_URL points at the same database as DATABASE_URL.');
  }
  return url;
}

/** Empty the test database and load the demo data again. */
function resetDatabase() {
  execFileSync('psql', [
    testDatabaseUrl(), '-v', 'ON_ERROR_STOP=1', '-q',
    '-f', path.join(ROOT, 'sql', 'schema.sql'),
    '-f', path.join(ROOT, 'sql', 'seed.sql'),
  ], { stdio: 'pipe' });
}

/** One value from the test database, e.g. sql("SELECT status FROM items WHERE id = 1"). */
function sql(query) {
  return execFileSync('psql', [testDatabaseUrl(), '-tAc', query], { encoding: 'utf8' }).trim();
}

/** `test` with a fresh database before every test. */
const test = base.test.extend({
  freshDatabase: [async ({}, use) => {
    resetDatabase();
    await use();
  }, { auto: true }],
});

// ---------------- steps a real user takes ----------------

/** Log in on the staff login page (optionally after being sent there). */
async function logIn(page) {
  await page.getByLabel('Email').fill(DEMO_EMAIL);
  await page.getByLabel('Password').fill(DEMO_PASSWORD);
  await page.getByRole('button', { name: 'Log in' }).click();
}

/** Open the staff screen and log in. */
async function logInAsStaff(page) {
  await page.goto('/staff/login.php');
  await logIn(page);
  await base.expect(page).toHaveURL(/\/staff\/$/);
}

/** Press "Try this on" on the archive card with this title. */
async function addCardToList(page, title) {
  const card = page.locator('li.card', { has: page.getByRole('link', { name: title, exact: true }) });
  await card.getByRole('button', { name: 'Try this on' }).click();
  await base.expect(card.getByRole('button', { name: 'On your list ✓' })).toBeVisible();
}

/** Send the try-on list to a fitting room (from the bar at the bottom). */
async function sendList(page, room) {
  await page.getByRole('button', { name: 'Send to fitting room' }).click();
  await page.getByLabel(`Room ${room}`).check();
  await page.getByRole('button', { name: 'Send request' }).click();
}

module.exports = {
  test, expect: base.expect, testDatabaseUrl, resetDatabase, sql,
  logIn, logInAsStaff, addCardToList, sendList, ROOT,
};
