// Flow 1: a customer filters the archive, opens an item, puts 2 items on the try-on
// list, sends them to a fitting room and lands on the status page.
// Runs at desktop size and at a 375px phone size.

const { test, expect, addCardToList, sendList } = require('./helpers');

async function customerFlow(page) {
  await page.goto('/');

  // Filter: only navy items (4 in the demo data).
  await page.getByLabel('Colour').selectOption('navy');
  await expect(page.locator('#result-count')).toHaveText('4 items');
  await expect(page).toHaveURL(/colour=navy/);

  // Open an item from its card.
  await page.getByRole('link', { name: 'Wide-leg sailor trousers', exact: true }).click();
  await expect(page).toHaveURL(/\/item\.php\?id=\d+&colour=navy/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Wide-leg sailor trousers');
  await expect(page.getByText('Trousers · Size M · Navy · Condition: Good')).toBeVisible();

  // Put it on the try-on list from the item page.
  await page.getByRole('button', { name: 'Try this on' }).click();
  await expect(page.getByRole('button', { name: 'On your list ✓' })).toBeVisible();
  await expect(page.locator('#tryon-count')).toHaveText('1 item');

  // Back to the archive: the filter is kept.
  await page.getByRole('link', { name: '← Back to the archive' }).click();
  await expect(page).toHaveURL(/\/\?colour=navy$/);
  await expect(page.locator('#result-count')).toHaveText('4 items');

  // A second item, straight from its card.
  await addCardToList(page, 'Quilted gilet');
  await expect(page.locator('#tryon-count')).toHaveText('2 items');

  // Send to fitting room 2.
  await sendList(page, 2);
  await expect(page).toHaveURL(/\/request\.php\?id=\d+$/);
  await expect(page.locator('#request-label')).toContainText('Request sent');
  await expect(page.locator('#request-room')).toHaveText('2');
  await expect(page.locator('.request__item')).toHaveCount(2);
}

test('customer: filter, open an item, try on 2 items, send to a fitting room', async ({ page }) => {
  await customerFlow(page);
});

test.describe('on a phone (375px)', () => {
  test.use({ viewport: { width: 375, height: 812 }, hasTouch: true, isMobile: true });

  test('customer flow at 375px, without sideways scrolling', async ({ page }) => {
    await customerFlow(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });
});
