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

## M2/M3: Request a try-on and follow its status

Checked 2026-11-18. Pages: `/` (archive) and `/request.php?id=`. To change a request's status before M4 exists:
`psql $env:DATABASE_URL -c "UPDATE tryon_requests SET status='fetching' WHERE id=<id>"` (for `ready`, also set `ready_at = now()`).

| ✓ | Check |
|---|---|
| ✓ | "Try this on" adds an item to the bar at the bottom ("Your try-on list · 2 items · Send to fitting room"); pressing it again removes it. |
| ✓ | The list survives a page reload and a filter change. |
| ✓ | With 5 items the other "Try this on" buttons say "List full" and can't be pressed. |
| ✓ | "Send to fitting room" asks for room 1, 2 or 3 and then opens the status page. |
| ✓ | Two browser windows: an item sent from window A disappears from window B within about 10 seconds. If B sends it anyway, B is told which item was taken and the rest still goes through. |
| ✓ | If every item on the list was taken, nothing is sent and a clear message explains why. |
| ✓ | The status page shows Sent → Being fetched → Ready and the fitting room, and updates by itself within 5 seconds after a status change. |
| ✓ | "Back to the archive" works. After `ready` or `closed` a countdown appears and the page returns to the archive after 60 seconds. |
| ✓ | An unsent list is cleared after 10 minutes without interaction (to check quickly, set `IDLE_MS` in `tryon.js` to 1 minute temporarily). |
| ✓ | At 375px: the bar doesn't cover the last cards, the three room buttons fit on one row, and nothing scrolls sideways. |
