# Manual checklist

Checks done by hand after each user story, on desktop and on a phone-sized screen (375px, DevTools device mode). Reset the database first: `psql $env:DATABASE_URL -f sql/schema.sql -f sql/seed.sql`.

## M1: Browse and filter the archive

Checked 2026-11-17.

| ✓ | Check |
|---|---|
| ✓ | Changing a filter updates the results without reloading the page. |
| ✓ | A combination with no results (e.g. pink, up to €15) shows "Nothing found"; "Clear filters" brings all items back. |
| ✓ | Reloading the page keeps the chosen filters and shows the same results. |
| ✓ | At 375px wide there is no horizontal scroll. |
| ✓ | At 375px wide the cards show in 2 columns. |

## M5: Buy-in from a walk-in seller

Checked 2026-11-18. Page: `/staff/intake.php`.

| ✓ | Check |
|---|---|
| ✓ | "Add item" adds an item with the next free shelf code; "Remove item" removes it and the item numbers update. "Add item" stops at 10 items. |
| ✓ | The payout total updates while typing purchase prices and when switching between cash and store credit (store credit = cash × 1.2). |
| ✓ | A real phone photo taken in portrait is stored the right way up. |
| ✓ | A mistake (e.g. sale price lower than purchase price) shows the error list at the top and a message at the field; typed values and the photo ("Photo kept") come back. |
| ✓ | A file that is not an image is refused with a clear message. |
| ✓ | After saving, the confirmation shows the payout and each item's shelf code; refreshing it does not save the buy-in twice. |
| ✓ | The new items appear in the archive with their photo. |
| ✓ | At 375px wide the form has no horizontal scroll and every field is easy to tap. |
