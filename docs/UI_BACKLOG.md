# UI backlog

Differences between the build and the Assignment 1 mockups in `docs/mockups/`, and UI ideas for later. Each entry says why. The deviations are built; the ideas are not, and an idea only gets built after it has been added to SPEC.md.

## M1: Browse and filter the archive (mockup `1-browse.png`)

Deviations in the build:

1. **Dropdowns instead of filter chips.** Size, category, colour and max price are four labelled `<select>`s (SPEC M1: filters can be combined). They work the same with a mouse, touch, keyboard and screen reader, without building a custom chip control.
2. **No search field and no "New in today" filter.** Neither is in SPEC M1, so they're left out (see "Next version").
3. **A "Try this on" button on every card.** The mockup has no button; SPEC M2 needs a way to add an item to the try-on list straight from the archive. The photo and title open the item page.
4. **"24 items" instead of "38 items match".** The same count, shorter, and it also reads well for "0 items" and "1 item".
5. **Prices as "€45.00" and the size as "Size L"** on their own line, instead of "M · €32". The same price format is used everywhere on the customer side.

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

The try-on panel (on the archive and the item page) has a visible "✕ Close" button since the UI review (2026-11-22), because a tablet has no Escape key.

## M4/M6: Fetch queue and fitting rooms (mockup `3-staff.png`)

Deviations in the build:

1. **Fitting rooms replace buy-in on the staff screen.** The right column of the mockup shows buy-in. Buy-in is its own page now (`/staff/intake.php`, M5), so the right column shows the three fitting rooms with "Sold" and "Back to archive" (M6). Both pages are linked in the staff menu in the header.
2. **The queue groups items per request.** The mockup shows one card per item. One tap moves a whole request on (SPEC M4), so each card is one request with all its items and one button.
3. **Shelf codes look like `A-08`, not `A-03-12`.** One letter for the rack and two digits for the spot, as decided for the database and in SPEC (M5).
4. **Action buttons.** The static mockup shows no buttons. The build adds "Start fetching" and "Ready in fitting room N" in the queue, and "Sold" and "Back to archive" in the rooms.
5. **A toast with the result of each tap** (UI review, 2026-11-22). After a tap, the result appears at the bottom of the screen and is announced to screen readers. It shows the shelf code in bold for "Back to archive", and "Nothing changed: …" in a different style. Before, the message sat at the top of the page, out of view after the page jumped to the room.
6. **Waiting times as "waiting 2 h 23 min"** from 60 minutes on, instead of "143 min".

## Buy-in (mockup `3-staff.png`, right column)

The mockup shows buy-in as a compact card next to the fetch queue. The build has its own page, `/staff/intake.php`. Deviations:

1. **A full form instead of the compact card.** Every required field of SPEC M5 has its own labelled field (photo, title, category, size, colour, condition, purchase price, sale price, shelf code), with an error message next to each, and typed values kept after an error.
2. **Several items per buy-in** ("Add item", up to 10), because SPEC M5 says one buy-in can contain several items. The mockup shows one.
3. **"Save buy-in" instead of "Add to archive",** because it saves the whole buy-in with all its items and the payout.
4. **"Shelf code" with a hint ("Next free spot suggested. You can change it.")** instead of "Store at: C-07-03 (suggested)". It's a field you can edit, and it uses the `A-08` format.
5. **A real photo upload** instead of the "Photo" placeholder box (JPEG, PNG or WebP, up to 8 MB, resized and stored in the database).
6. **The payout amounts on the choices,** as in the mockup ("Cash · €8.00", "Store credit · €9.60"), since the UI review (2026-11-22). Before any price is typed, the store credit choice says "(cash × 1.2)". The sentence under the choices stays as a summary.

## M7: Manager dashboard (mockup `4-dashboard.png`)

Deviations in the build:

1. **No "Mark down" buttons.** Slow movers are shown as a compact table (item, size, shelf, days, price). Suggested markdown prices are out of scope in SPEC, and there is no markdown flow yet.
2. **No "Viewed" bar in the funnel.** The tablet doesn't record which items customers look at, so the funnel starts at "Requested" (then "Decided", "Bought").
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
6. **More white space, fewer boxes.** Since the UI review (2026-11-22), the Try-ons and Slow movers sections each have their own beige block, in the same style as the three tiles. That way the two sections are easy to tell apart, and at 375px they stack as two blocks.

Note: "Sold" (by sale date) and "Bought" in the funnel (items requested in the period) can differ at the start of a week. For example, an item requested on Sunday and bought on Monday counts in Monday's Sold but in last week's funnel. The funnel's heading says which items it covers.

## Next version (from the UI review, 2026-11-22)

Findings from [`UI_REVIEW.md`](UI_REVIEW.md) that weren't fixed in this round. Each has its ID in the review, with the problem, the reason and a suggested fix. Only built after they have been added to SPEC.md where needed.

**Should fix**

| ID | Screen | What |
|---|---|---|
| A1 | Archive | The filters push the first item below the middle of a phone screen: show them as one row of chips or behind a "Filters" button. |
| A3 | Archive | The 10-second refresh can move cards under the customer's finger: redraw only when the screen isn't being touched, keep the focus. |
| A6 | Archive | "Clear filters" only when a filter is active (M1 idea 3 above). |
| T1 | Try-on list | An item taken by another tablet stays on the list until "Send": mark it "Just taken" straight away. |
| T3 | Try-on list | Adding an item isn't announced: a polite live region "… added. 2 items on your list." |
| G2 / L1 | Login | The "Staff" label in the header looks like a button: plain text or leave it out. |
| S2 | Staff | On a phone or a tablet in portrait the fitting rooms are far below the queue: a jump bar or tabs "Queue · Rooms". |
| S4 | Staff | The 10-second board refresh can take away keyboard focus: put the focus back on the same button. |
| B1 | Buy-in | The photo field shows the Windows language ("Bestand kiezen"): a styled "Choose photo" button, and the camera on phones. |

**Nice to have**

| ID | Screen | What |
|---|---|---|
| A4 | Archive | A hint that a card opens the item page (a "›" or a press effect). |
| A6 | Archive | Multi-select filters, price steps up to the highest price, bigger cards on wide screens (M1 ideas 1, 2 and 4 above). |
| — | Archive | A search field and a "New in today" filter (mockup 1; not in SPEC). |
| I1 | Item page | The size first and bold in the details line. |
| I2 | Item page | The "taken" page could suggest similar items (needs SPEC). |
| T4 | Try-on bar | A shorter bar text on narrow phones ("Try-on list · 5"). |
| T5 | Try-on bar | The send button in light terracotta as in the mockup. |
| R2 | Request status | A "Stay on this page" option during the 60-second countdown (needs SPEC). |
| G3 | All | The "Free" pill without a border, so only buttons have borders. |
| G4 | Buy-in | Taller links in the error summary (44px). |
| L2 | Login | Link the error to the password field too (`aria-describedby`). |
| S5 | Staff | "Start fetching request 7" as the accessible name of the queue buttons. |
| B4 | Buy-in | "Save buy-in" at least as prominent as "Add item". |
| D1 | Dashboard | A compact row of the three numbers on phones. |
| D2 | Dashboard | Stronger colours in the funnel bar (partly done: "on their way" is darker inside the new block). |
