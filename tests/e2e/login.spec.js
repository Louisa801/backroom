// Flow 5: a staff page without a login goes to the login page; after logging in you
// are back on the page you wanted.

const { test, expect, logIn } = require('./helpers');

test('a staff page needs a login and returns there afterwards', async ({ page }) => {
  await page.goto('/manager/?period=today');

  await expect(page).toHaveURL(/\/staff\/login\.php\?return=%2Fmanager%2F%3Fperiod%3Dtoday$/);
  await expect(page.getByRole('heading', { name: 'Staff login' })).toBeVisible();

  await logIn(page);

  await expect(page).toHaveURL(/\/manager\/\?period=today$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Today');
  await expect(page.getByRole('link', { name: 'Today' })).toHaveAttribute('aria-current', 'page');
});

test('a wrong password stays on the login page with a plain message', async ({ page }) => {
  await page.goto('/staff/');
  await page.getByLabel('Email').fill('staff@backroom.test');
  await page.getByLabel('Password').fill('not the password');
  await page.getByRole('button', { name: 'Log in' }).click();

  await expect(page.getByRole('alert')).toHaveText('Email or password is incorrect.');
  await expect(page).toHaveURL(/\/staff\/login\.php/);
});
