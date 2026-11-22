# Manual checklist

Checks done by hand after each user story, on desktop and on a phone-sized screen (375px, DevTools device mode). Reset the database first: `psql $env:DATABASE_URL -f sql/schema.sql -f sql/seed.sql`.

## Testing overview

What is checked automatically, and what is left for these checks by hand. Commands are in the README.

| Tests | Command (PowerShell) | What they cover |
|---|---|---|
| Unit and integration tests (PHPUnit) | `vendor/bin/phpunit` | The logic in `src/`: filters, payout, validation, photos, every status change and refusal (also two tablets or two staff members at once), the dashboard figures with fixed dates, login and the 5-attempt limit. `StaffAccessTest` checks over HTTP that every staff/manager page needs a login and that the customer pages stay public. |
| Database constraint tests | `psql $env:DATABASE_URL -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql` | The database itself refuses bad data (negative prices, unknown statuses, fitting room 4, a second sale of the same item, …). |
| End-to-end tests (Playwright) | `npx playwright test` | The critical flows in a real browser, on a separate test database: customer flow (also at 375px), full staff flow with the customer page updating by itself, buy-in with a real photo, two tablets wanting the same item, login protection. |
| End-to-end flows by hand | – | The same 5 flows as the Playwright tests, clicked through by hand at laptop width and at 375px (see "End-to-end flows (tested by hand)" below). |
| By hand (this file) | – | Look and feel, the mockups, readability, keyboard use, real phone photos, things a test can't judge. |

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

Checked 2026-11-18. Pages: `/` (archive) and `/request.php?id=`. To move a request on, use the staff screen `/staff/` (M4).

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

## M4/M6: Fetch queue and fitting rooms

Checked 2026-11-18. Page: `/staff/`. Compare with `docs/mockups/3-staff.png` (deviations are listed in `docs/UI_BACKLOG.md`).

| ✓ | Check |
|---|---|
| ✓ | The fetch queue shows new and fetching requests, oldest first, each item as "Title · Size" and "Shelf A-08 → Fitting room N", with the "New request" or "Fetching" pill. |
| ✓ | A request sent from the tablet appears in the queue within 10 seconds without reloading. |
| ✓ | "Start fetching", then "Ready in fitting room N", moves the request on one step per tap; the customer's status page follows within 5 seconds. |
| ✓ | When a request is ready, its items appear in the right fitting room with "Sold" and "Back to archive". |
| ✓ | "Sold" shows "Sold: Title (€price)" and the item shows "Sold". |
| ✓ | "Back to archive" shows the shelf code to put it back on, and the item is visible on the tablet again within 10 seconds. |
| ✓ | When every item of a request has an outcome, the request is closed, the room shows "Free", and the customer's page shows "finished". |
| ✓ | Double tap, or two browser windows tapping the same button: the second tap says "Nothing changed: …" and nothing is sold twice or skipped. |
| ✓ | The staff menu links "Queue & rooms" and "Buy-in". |
| ✓ | At 375px: one column, buttons easy to tap, "Sold" and "Back to archive" side by side, nothing scrolls sideways. On a tablet in landscape: two columns. |

## M7: Manager dashboard

First version checked 2026-11-18; redesigned and rechecked 2026-11-20. Page: `/manager/`. Compare with `docs/mockups/4-dashboard.png` (deviations and the redesign are explained in `docs/UI_BACKLOG.md`).

| ✓ | Check |
|---|---|
| ✓ | "Today" and "This week" switch the figures; the date range line shows the right period in Porto time (the week starts on Monday). |
| ✓ | The three main tiles (Sold, Revenue in whole euros, Gross margin) are what you see first, each with one short explanation. |
| ✓ | Under the tiles one quiet line: "27 items in the archive · €74.40 given as store credit this week" (after a fresh reset); no band at the bottom. |
| ✓ | After a fresh reset, this week shows 7 sold, €204 and 68%, and the try-ons read "Requested 22 → Decided 16 (73%) → Bought 7 (44%)" (decided ÷ requested, then bought ÷ decided). |
| ✓ | The try-on table adds up: 7 bought + 9 returned + 3 still in a fitting room + 3 on their way = 22 requested, and the bar shows the same split. |
| ✓ | The line under the table explains the buy rate (bought ÷ decided = 7 ÷ 16). |
| ✓ | Selling an item on the staff screen moves it from "still in a fitting room" to "bought" and raises Sold and Revenue after a reload. |
| ✓ | With no sales or no requests in the period (e.g. today early in the morning), margin shows "–" with a plain note, the try-ons say "No try-on requests today yet", and nothing breaks. |
| ✓ | Slow movers are a compact table (Item, Size, Shelf, Days, Price), oldest first; no "Mark down" buttons. |
| ✓ | At 375px: tiles stacked, try-ons above slow movers, the table fits, nothing scrolls sideways. On a laptop: three tiles in a row, try-ons and slow movers side by side. |

## Login

Checked 2026-11-20. Page: `/staff/login.php`. Demo login: `staff@backroom.test` / `backroom-demo`.

| ✓ | Check |
|---|---|
| ✓ | Opening `/staff/`, `/staff/intake.php` or `/manager/` without a login goes to the login page; after logging in you are back on the page you opened. |
| ✓ | A wrong password and an unknown email both show "Email or password is incorrect." |
| ✓ | After 5 wrong attempts, even the right password says "Too many attempts. Please wait a minute and try again."; a minute later it works. |
| ✓ | The staff header is one row on every staff screen: "Backroom", the menu (Queue & rooms · Buy-in · Dashboard · Archive) and on the right your name and "Log out", vertically centred. From 1,500px wide the menu starts at the same left edge as the page content; from 900 to 1,499px it follows "Backroom". |
| ✓ | Below 900px the header shows "Backroom" and a "☰ Menu" button. The button opens a dropdown with the four menu items (current one highlighted), then your name and "Log out". It closes with the button, Escape (focus back on the button), a tap outside, or choosing a page. It works with the keyboard (Tab, Enter/Space) and every row is at least 44px tall. |
| ✓ | "Log out" ends the session: the staff pages need a login again, and the login page says "You are logged out." |
| ✓ | A buy-in records who was logged in: `psql $env:DATABASE_URL -c "SELECT b.id, s.name FROM buy_ins b JOIN staff_users s ON s.id = b.staff_id ORDER BY b.id DESC LIMIT 1"` |
| ✓ | The customer tablet still needs no login: archive, try-on list, request status and photos work in a private window. |
| ✓ | On the staff board, the 10-second refresh keeps working while logged in; after logging out in another tab, the board goes to the login page. |
| ✓ | "Archive" in the staff menu opens the customer archive; there a logged-in staff member sees "← Staff screen" in the header, which goes back to the staff screen. In a private window (not logged in) the archive and request pages show no "Staff screen" link. |
| ✓ | At 375px the login form and the staff header (Backroom, ☰ Menu) fit, nothing overlaps and nothing scrolls sideways. |

## Item detail

Checked 2026-11-22. Page: `/item.php?id=` (open it by clicking a card's photo or title). Compare with `docs/mockups/2-tryon.png` (deviations are listed in `docs/UI_BACKLOG.md`).

| ✓ | Check |
|---|---|
| ✓ | Clicking a card's photo or title opens the item page, also after changing a filter (cards redrawn without reloading). The "Try this on" button on the card still works without opening the page. |
| ✓ | The item page shows a large photo, title, category, size, colour, condition and price; no shelf code. |
| ✓ | "Try this on" on the item page adds the item to the try-on bar ("On your list ✓"); pressing again removes it; with 5 items it says "List full". The list can be sent from this page too. |
| ✓ | "← Back to the archive" returns to the archive with the same filters as before. |
| ✓ | An item that was just requested or sold (e.g. `/item.php?id=12` after a reset) shows "This item has just been taken" with a working "Back to the archive" button, no error. |
| ✓ | At 375px: photo full width, then the details, nothing scrolls sideways. On a laptop: photo left, details right. |

## End-to-end flows (tested by hand)

The same 5 flows as the automated tests in `tests/e2e/`, clicked through by hand several times at laptop width and at 375px. Checked 2026-11-22.

| ✓ | Flow |
|---|---|
| ✓ | Customer: filter the archive, open an item, add 2 items to the try-on list, send them to a fitting room, and land on the status page. |
| ✓ | Full staff flow: log in, start fetching that request, mark it ready (the customer status page updates by itself), mark one item Sold and one Back to archive, and the request closes. The sold item is gone from the archive and the returned one is back. |
| ✓ | Buy-in: add an item with a real photo, and it appears in the archive with its photo. |
| ✓ | Two tablets, same item: two browsers request the same item; one gets it, the other gets the "taken" message. |
| ✓ | Login protection: a staff page without login goes to the login page, and after logging in you return to it. |