// Playwright: end-to-end tests of the critical flows (tests/e2e/).
// Run: npx playwright test   (see README, "End-to-end tests")

const { defineConfig } = require('@playwright/test');
const { testDatabaseUrl } = require('./tests/e2e/helpers');

const PORT = 8100; // not 8000, so it never clashes with your own `php -S localhost:8000`

module.exports = defineConfig({
  testDir: './tests/e2e',
  // The tests share one test database, which is reset before each test: one at a time.
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    channel: 'msedge',            // the Edge already installed on Windows: no browser download
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  // Playwright starts its own PHP server, with the TEST database.
  webServer: {
    command: `php -S 127.0.0.1:${PORT} -t public`,
    // A static file: the server is "up" even before the first test has reset the (empty) test database.
    url: `http://127.0.0.1:${PORT}/assets/css/style.css`,
    reuseExistingServer: false,
    timeout: 30_000,
    stdout: 'ignore',
    stderr: 'ignore',             // php -S logs every request; failures are in the trace and screenshots
    env: { ...process.env, DATABASE_URL: testDatabaseUrl() },
  },
});
