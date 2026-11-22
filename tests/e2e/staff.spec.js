// Flow 2: the full staff flow. A customer sends 2 items; staff fetch them, mark the
// request ready (the customer's status page updates by itself), sell one item and put
// the other back. The request closes; the sold item is gone from the archive and the
// returned one is back.

const { test, expect, addCardToList, sendList, logInAsStaff, sql } = require('./helpers');

const SOLD = 'Wide-leg sailor trousers';
const RETURNED = 'Quilted gilet';

test('staff: fetch, ready, sold and back to archive; the request closes', async ({ browser }) => {
  // The customer, on the tablet.
  const tablet = await browser.newContext();
  const customer = await tablet.newPage();
  await customer.goto('/?colour=navy');
  await addCardToList(customer, SOLD);
  await addCardToList(customer, RETURNED);
  await sendList(customer, 2);
  await expect(customer).toHaveURL(/\/request\.php\?id=\d+$/);
  const requestId = new URL(customer.url()).searchParams.get('id');
  await expect(customer.locator('#request-label')).toContainText('Request sent');

  // Staff, on the staff screen.
  const desk = await browser.newContext();
  const staff = await desk.newPage();
  await logInAsStaff(staff);

  const card = staff.locator(`article[aria-label="Request ${requestId}"]`);
  await expect(card).toContainText(SOLD);
  await expect(card).toContainText('Fitting room 2');
  await card.getByRole('button', { name: 'Start fetching' }).click();
  await expect(staff.locator('#staff-flash')).toHaveText(`Request #${requestId}: fetching started.`);

  await staff.locator(`article[aria-label="Request ${requestId}"]`)
    .getByRole('button', { name: 'Ready in fitting room 2' }).click();
  await expect(staff.locator('#staff-flash')).toHaveText(`Request #${requestId} is ready in fitting room 2.`);

  // The customer's page follows by itself (it checks every 5 seconds), without reloading.
  await expect(customer.locator('#request-label')).toHaveText('Ready in fitting room 2. You can go there now.', { timeout: 15_000 });
  await expect(customer.locator('.steps__step--current')).toContainText('Ready');

  // In fitting room 2: one sold, one back to the archive.
  const room = staff.locator('#room-2');
  await room.getByRole('button', { name: `Sold: ${SOLD}` }).click();
  await expect(staff.locator('#staff-flash')).toHaveText(`Sold: ${SOLD} (€28.00).`);
  // The message is on screen even though the page jumped to the fitting room (UI review S1).
  await expect(staff.locator('[data-toast]')).toBeInViewport();

  await staff.locator('#room-2').getByRole('button', { name: `Back to archive: ${RETURNED}` }).click();
  await expect(staff.locator('#staff-flash')).toContainText(`${RETURNED} is back in the archive. Put it on shelf`);
  await expect(staff.locator('#staff-flash')).toContainText(`Request #${requestId} is closed.`);
  await expect(staff.locator('#room-2')).toContainText('Free');

  // The customer sees the request is finished.
  await expect(customer.locator('#request-label')).toHaveText('This request is finished. Thank you!', { timeout: 15_000 });

  // The archive: the sold item is gone, the returned one is back.
  await customer.goto('/?colour=navy');
  await expect(customer.getByRole('link', { name: RETURNED, exact: true })).toBeVisible();
  await expect(customer.getByRole('link', { name: SOLD, exact: true })).toHaveCount(0);

  // And in the database: one sale at the sale price, the request closed.
  expect(sql(`SELECT count(*) || ' ' || max(s.price) FROM sales s JOIN items i ON i.id = s.item_id WHERE i.title = '${SOLD}'`)).toBe('1 28.00');
  expect(sql(`SELECT status FROM tryon_requests WHERE id = ${Number(requestId)}`)).toBe('closed');

  await tablet.close();
  await desk.close();
});
