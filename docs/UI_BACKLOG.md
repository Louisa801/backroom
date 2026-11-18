# UI backlog

Differences between the build and the Assignment 1 mockups in `docs/mockups/`, and UI ideas for later. Each entry says why. The deviations are built; the ideas are not, and an idea only gets built after it has been added to SPEC.md.

## M1: Browse and filter the archive (mockup `1-browse.png`)

Ideas noted after M1, not built yet:

1. **Multi-select filters.** Let a customer pick more than one size or colour (for example M and L). The MVP has one choice per filter, which was enough for SPEC M1.
2. **Price range up to the highest price.** Base the price steps on the most expensive item in the archive instead of fixed steps (€15, €25, €40, €60, €100).
3. **"Clear filters" only when a filter is active.** At the moment the button is always visible.
4. **Bigger cards on wide screens.** The cards stay at about 200px wide on a laptop; they could grow with the screen.

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
3. **A fifth tile, Revenue,** because SPEC M7 asks for revenue. "Avg. margin" is called **"Gross margin"**: the period total, (sum of sale prices − sum of purchase prices) ÷ sum of sale prices (SPEC Business rules).
4. **A different footer.** The mockup says "Bought in this week: 112 items · 71% of sellers chose store credit". SPEC M7 asks for the store credit given out, so the footer shows that amount.
5. **No "Sample data" label,** because the dashboard shows real data.
6. **A "Today | This week" toggle and a date range line,** which the mockup (this week only) doesn't show. Every tile has a short explanation under its number.
