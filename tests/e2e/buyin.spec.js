// Flow 3: staff register a buy-in with a real photo; the item appears in the archive
// with that photo (served from the database by photo.php).

const path = require('node:path');
const { test, expect, logInAsStaff } = require('./helpers');

const TITLE = 'E2E green wool coat';

test('buy-in with a real photo appears in the archive with its photo', async ({ page }) => {
  await logInAsStaff(page);
  await page.getByRole('link', { name: 'Buy-in' }).first().click();
  await expect(page.getByRole('heading', { name: 'New buy-in' })).toBeVisible();

  await page.getByLabel('Photo').setInputFiles(path.join(__dirname, 'fixtures', 'coat.jpg'));
  await page.getByLabel('Title').fill(TITLE);
  await page.getByLabel('Category').selectOption({ label: 'Jackets & coats' });
  await page.getByLabel('Condition').selectOption({ label: 'Good' });
  await page.getByLabel('Size').selectOption('L');
  await page.getByLabel('Colour').selectOption('green');
  await page.getByLabel('Purchase price (€)').fill('12,50');
  await page.getByLabel('Sale price (€)').fill('45');
  await expect(page.getByLabel('Shelf code')).toHaveValue(/^[A-Z]-\d{2}$/); // next free spot suggested
  // The amounts are on the choices themselves (UI review): "Cash · €12.50", "Store credit · €15.00".
  await expect(page.getByRole('radio', { name: 'Cash · €12.50' })).toBeVisible();
  await expect(page.getByRole('radio', { name: 'Store credit · €15.00' })).toBeVisible();
  await page.getByRole('radio', { name: /^Cash/ }).check();
  await expect(page.locator('#payout-total')).toHaveText('Pay out €12.50 in cash.');

  await page.getByRole('button', { name: 'Save buy-in' }).click();
  await expect(page).toHaveURL(/\/staff\/intake\.php\?saved=\d+$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('saved');
  await expect(page.locator('.success__payout')).toContainText('Pay out €12.50 in cash.');

  // On the customer archive, with its photo.
  await page.goto('/?colour=green&size=L');
  const card = page.locator('li.card', { has: page.getByRole('link', { name: TITLE, exact: true }) });
  await expect(card).toBeVisible();
  await expect(card).toContainText('€45.00');
  const photo = card.locator('img.card__photo');
  await expect(photo).toHaveAttribute('src', /^\/photo\.php\?id=\d+$/);
  await photo.scrollIntoViewIfNeeded();
  await expect.poll(() => photo.evaluate((img) => img.complete && img.naturalWidth)).toBeGreaterThan(0);
});
