// Flow 4: two tablets put the same item on their list. The first to send gets it;
// the second is told it was just taken.

const { test, expect, addCardToList, sendList } = require('./helpers');

const ITEM = 'Striped Breton top';

test('two tablets, same item: one gets it, the other is told it was taken', async ({ browser }) => {
  const tabletA = await (await browser.newContext()).newPage();
  const tabletB = await (await browser.newContext()).newPage();

  // Both customers choose the same item before either sends.
  for (const tablet of [tabletA, tabletB]) {
    await tablet.goto('/?colour=navy');
    await addCardToList(tablet, ITEM);
    await expect(tablet.locator('#tryon-count')).toHaveText('1 item');
  }

  // Tablet A is first.
  await sendList(tabletA, 1);
  await expect(tabletA).toHaveURL(/\/request\.php\?id=\d+$/);
  await expect(tabletA.locator('.request__item')).toContainText(ITEM);

  // Tablet B sends a moment later and is told, in plain words.
  await sendList(tabletB, 3);
  await expect(tabletB.locator('#tryon-message')).toHaveText(`Sorry, this item was just taken by someone else: ${ITEM}.`);
  await expect(tabletB).toHaveURL(/\/\?colour=navy$/); // stays on the archive
  await expect(tabletB.locator('#tryon-count')).toHaveText('0 items');

  await tabletA.context().close();
  await tabletB.context().close();
});
