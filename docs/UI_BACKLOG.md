# UI backlog

Differences between the build and the Assignment 1 mockups in `docs/mockups/`, and UI ideas for later. Each entry says why. The deviations are built; the ideas are not, and an idea only gets built after it has been added to SPEC.md.

## M1: Browse and filter the archive (mockup `1-browse.png`)

Ideas noted after M1, not built yet:

1. **Multi-select filters.** Let a customer pick more than one size or colour (for example M and L). The MVP has one choice per filter, which was enough for SPEC M1.
2. **Price range up to the highest price.** Base the price steps on the most expensive item in the archive instead of fixed steps (€15, €25, €40, €60, €100).
3. **"Clear filters" only when a filter is active.** At the moment the button is always visible.
4. **Bigger cards on wide screens.** The cards stay at about 200px wide on a laptop; they could grow with the screen.

### Added later: staff header

The mockups show no staff header. The staff and manager screens have one row: "Backroom", the menu (Queue & rooms · Buy-in · Dashboard · Archive), then the staff member's name and "Log out". "Archive" opens the customer archive; on the customer pages a logged-in staff member sees "← Staff screen" in the header to go back (customers never see it).
- **From 1,500px wide,** the menu starts at the same left edge as the page content.
- **From 900 to 1,499px,** it follows "Backroom", because there is no room for both: at 1,280px the content starts at 64px and "Backroom" ends at about 140px.
- **Below 900px,** a "☰ Menu" button opens a dropdown with the same links, the name and "Log out".

### Added later: "Staff" link in the footer

The customer pages (archive and request status) have a small "Staff" link at the bottom right, linking to `/staff/login.php`. The mockups have no footer. It gives staff a way in from the shared tablet without typing a URL. It's muted so customers don't notice it, but still 16px, 7.3:1 contrast and a 44px tap target (CLAUDE.md UI rules). Staff pages don't show it.

## Item detail (mockup `2-tryon.png`)

Deviations in the build:

1. **One photo, no thumbnails.** Each item has one photo (M5), so the row of small photos under the main photo is left out.
2. **No "Your request" panel.** The request status has its own page (`/request.php`, M3), which the customer sees after sending the list. The detail page shows the try-on toggle and the try-on bar instead.
3. **The category is added** to the details line ("Trousers · Size M · Navy · Condition: Good"), because SPEC lists it for this page.
4. **The price is shown as €28.00,** the same as on the cards, not "€32" as in the mockup.
5. **"← Back to the archive"** instead of "Back to results": it returns to the archive with the customer's filters.
## M4/M6: Fetch queue and fitting rooms (mockup `3-staff.png`)

Deviations in the build:

1. **Fitting rooms replace buy-in on the staff screen.** The right column of the mockup shows buy-in. Buy-in is its own page now (`/staff/intake.php`, M5), so the right column shows the three fitting rooms with "Sold" and "Back to archive" (M6). Both pages are linked in the staff menu in the header.
2. **The queue groups items per request.** The mockup shows one card per item. One tap moves a whole request on (SPEC M4), so each card is one request with all its items and one button.
3. **Shelf codes look like `A-08`, not `A-03-12`.** One letter for the rack and two digits for the spot, as decided for the database and in SPEC (M5).
4. **Action buttons.** The static mockup shows no buttons. The build adds "Start fetching" and "Ready in fitting room N" in the queue, and "Sold" and "Back to archive" in the rooms.

## M7: Manager dashboard (mockup `4-dashboard.png`)

Deviations in the build:

1. **No "Mark down" buttons.** Slow movers are shown as a plain list (title, size, days, shelf, price). Suggested markdown prices are out of scope in SPEC, and there is no markdown flow yet.
2. **No "Viewed" bar in the funnel.** The tablet doesn't record which items customers look at, so the funnel starts at "Requested" (then "Tried", "Bought").
3. **"Avg. margin" is called "Gross margin":** the period total, (sum of sale prices − sum of purchase prices) ÷ sum of sale prices (SPEC Business rules).
4. **No "Sample data" label,** because the dashboard shows real data.
5. **A "Today | This week" toggle and a date range line,** which the mockup (this week only) doesn't show.

### Redesign after the hand check (2026-11-18)

The first build (commit `7760b8e`) had five tiles, a separate "Try-on → buy" tile, a store-credit band at the bottom and a funnel of three bars. Louisa found it too crowded and not intuitive, and its numbers could contradict each other. The funnel counted each step by when it happened: requested this week, tried this week, decided this week. Those are three different groups of items, so "requested − tried" didn't have to match anything, and could even be negative across a week boundary.

What changed and why:

1. **Three main tiles: Sold, Revenue (in whole euros) and Gross margin,** each with one short explanation. The mockup has four tiles. Fewer numbers at the top make the important ones what you see first.
2. **"In the archive" and store credit moved to one quiet line under the tiles,** and the store-credit band at the bottom is gone. They're useful facts, but not headline numbers.
3. **The "Try-on → buy" tile is merged into the funnel.** The buy rate only makes sense next to the numbers it comes from.
4. **The funnel shows where every requested item is now.** It follows one group, the items requested in the period: on their way, still in a fitting room (undecided), bought, returned. A step line ("Requested 22 → Decided 16 (73%) → Bought 7 (44%)": decided ÷ requested, then bought ÷ decided, the buy rate), one stacked bar and a table with a total make the numbers add up visibly. The buy rate (bought ÷ decided) is explained in one line. This needed one logic change in `src/Dashboard.php`, recorded in SPEC.md.
5. **Slow movers as a compact table** (Item, Size, Shelf, Days, Price), easier to scan than a list.
6. **More white space, fewer boxes:** only the three main tiles have a background.

Note: "Sold" (by sale date) and "Bought" in the funnel (items requested in the period) can differ at the start of a week. For example, an item requested on Sunday and bought on Monday counts in Monday's Sold but in last week's funnel. The funnel's heading says which items it covers.
