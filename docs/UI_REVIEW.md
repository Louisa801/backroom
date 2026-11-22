# UI review

Review of every screen, 2026-11-22. Each finding below has a **Status** line saying what happened to it.

## Status after the fixes (2026-11-22)

- **✅ Fixed (9):**
  - G1 field borders, S1 staff toast, B2 payout amounts on the choices, S3 waiting times, T2 Close button, A2 unique "Try this on" names
  - the docs: A5 and B3 (missing deviations) and D3 (out of date text).

  Also changed at Louisa's request: the dashboard's Try-ons and Slow movers sections each have their own block, in the tile style.
- **➡ Moved (23)** to [`UI_BACKLOG.md` → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22):
  - G2, G3, G4
  - A1, A3, A4, A6
  - I1, I2
  - T1, T3, T4, T5
  - R2
  - L1, L2
  - S2, S4, S5
  - B1, B4
  - D1, D2.
- **No action needed (7):** I3, R1, R3, L3, S6, B5, D4.
- **Tests:** after the fixes all PHPUnit tests, the constraint tests and the 7 end-to-end tests pass. The end-to-end tests now also check that the staff message is on screen and that "Close" closes the try-on panel.

**How it was checked:**
- Screenshots of every screen at 375px and at laptop width (1280px), logged out and logged in, with the local demo data.
- The contrast of every colour pair used in `public/assets/css/style.css`, calculated with the WCAG formula.
- A scan of the CSS for type sizes, spacing values and focus styles.
- Reading the markup for labels, alt texts, heading order and accessible names.
- A comparison with `docs/mockups/` and with the open items in `docs/UI_BACKLOG.md`.

**Priorities:**
- **Must fix:** breaks a CLAUDE.md UI rule (WCAG AA), or makes a core flow unclear.
- **Should fix:** a real usability or accessibility problem that has a workaround.
- **Nice to have:** polish.

## Must fix (summary)

| # | Screen | Problem | Status |
|---|---|---|---|
| G1 | All forms | Borders of text fields, selects, the file field and the choice boxes (fitting room, payout) are 1.4–1.6:1 against their background. WCAG 1.4.11 needs 3:1 for the outline of a form control. | ✅ Fixed |
| S1 | Staff queue & rooms | After a tap ("Start fetching", "Sold", "Back to archive"), the page jumps to `#queue` or `#room-N`. The confirmation or "Nothing changed: …" message is at the top of the page, out of view. Staff can't see whether the tap worked. | ✅ Fixed |

---

## Across all screens

### What already meets the rules
- **Text contrast:** every text colour passes AA. The lowest are accent links and secondary buttons on the background at **5.07:1**, and the browser's placeholder "0.00" at about **4.5:1**. Body text ranges from 6.8:1 to 15:1.

  | Pair | Ratio |
  |---|---|
  | Dark brown on off-white / surface | 13.7 / 15.5 |
  | Soft brown text on off-white / beige tiles | 7.3 / 6.8 |
  | White on terracotta buttons | 5.8 |
  | Terracotta text (links, secondary buttons) on off-white | 5.07 |
  | Dark text on pills: Fetching / In fitting room / New request | 7.4 / 10.3 / 12.7 |
  | Header text, the name "Louisa" on the dark header | 13.7 / 10.0 |
  | "Sold" outcome in green | 6.1 |
- **Type:** every CSS font size is 16px or larger. The sizes used are 16, 18, 20, 22, 24, 28, 32, 36, 40 and 48px.
- **Spacing:** every margin, padding and gap uses the 4/8/16/24/32/48 scale through the `--s1`…`--s6` tokens. The only exceptions are sizes, not spacing (the 56px large buttons, the 140px radio width, the 120px room under the try-on bar).
- **Tap targets:** every button, select, menu link, card title link, segmented toggle and footer link is at least 44px tall.
- **Focus:** there's a global 3px terracotta `:focus-visible` outline (5.07:1 on the background). Elements on the dark header and the dark try-on bar have their own light outline (13.7:1).
- **Labels and alt texts:** every form field has a `<label>`, the radio groups have a `<legend>`, and every image has `alt` = the item title. The decorative copy of a card photo inside its link is `aria-hidden`.
- **Headings:** one `h1` per page and a logical order (h1 → h2 → h3). `<html lang="en">`, and a page `<title>` on every screen.

### G1. Form field borders are too light (**must fix**)
**Status:** ✅ Fixed 2026-11-22: form fields use `--field-border` #8C7B6E (3.3–4.0:1); decorative lines keep `--line`.

- **Problem:** the `.field select`, `.field input` and `.radio` boxes use `--line` (#D8CDBB) for their 2px border. That's **1.55:1** on the white-ish field and **1.37:1** on the page background.
  - Affected: the archive filters, the buy-in form, the login form, the fitting-room choice and the payout choice.
- **Why it matters:** WCAG 1.4.11 (non-text contrast, AA) needs 3:1 for what identifies a control. On a tablet in a bright shop the empty fields are hard to find, especially the empty ones on the buy-in form.
- **Fix:** give form controls their own border colour, for example `--field-border: #8C7B6E` (about 3.9:1 on #FFFDF8) or the soft text colour #5C4A3F (8.2:1). Keep `--line` for decorative card borders and dividers, which don't need 3:1.

### G2. The "Staff" label in the header looks like a button (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** on the login page (and the "expired" page of `action.php`), the header shows "Staff" in a bordered box. It has the same shape as the "Log out" and "← Staff screen" buttons, but nothing happens when you tap it.
- **Why it matters:** inconsistent signals. A bordered box reads as a button.
- **Fix:** show it as plain muted text ("Staff login") without a border. Or leave it out, because the page heading already says "Staff login".

### G3. Outlined pills look like outlined buttons (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the "Free" pill on the staff screen and the secondary buttons ("Clear filters", "Back to archive", "Add item") are both outlined shapes with similar text.
- **Why it matters:** at a glance it's not clear what can be pressed.
- **Fix:** give "Free" a filled, very light background without a border, the same style as the other pills. Only buttons get a border.

### G4. Inline links are smaller than 44px (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** "go to the archive" on the login page and the links in the buy-in error summary are about 24px tall.
- **Why it matters:** CLAUDE.md asks for 44px targets. WCAG 2.5.8 makes an exception for links inside a sentence, but the error-summary links are a list of targets.
- **Fix:** give the error-summary links `display: inline-block; padding-block: 10px`. The sentence link can stay.

---

## Archive (`/`), mockup `1-browse.png`

### A1. The filters push the first item far down on a phone (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** at 375px, the title, the intro, the 2×2 filters and "Clear filters" take about 470px. The first card starts below the middle of the screen.
- **Why it matters:** the archive is the main customer screen. The mockup puts filter chips in one compact row.
- **Fix:** on small screens, show the filters as one horizontally scrolling row of chips (as in the mockup), or behind a "Filters (2)" button. Shorter: drop the intro sentence below 600px.

### A2. Every "Try this on" button has the same accessible name (**should fix**)
**Status:** ✅ Fixed 2026-11-22: `aria-label="Try this on: {title}"`, kept in step with the visible text by tryon.js.

- **Problem:** a screen reader hears 24 times "Try this on, toggle button, not pressed", with nothing saying which item.
- **Why it matters:** WCAG 2.4.6 asks for descriptive labels, and the cards are a long list.
- **Fix:** add `aria-label="Try this on: {title}"` (it still starts with the visible text, WCAG 2.5.3). The same for the button on the item page.

### A3. The archive refresh can move cards under the customer's finger (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** every 10 seconds the archive redraws when the list of items changed, for example after another tablet's request. Cards shift without warning.
- **Why it matters:** the customer can tap the wrong card. Keyboard focus is lost if it was on a card.
- **Fix:** only redraw while the customer isn't touching the screen (no tap or scroll in the last 3 seconds). Or remove the gone cards with a short fade and the line "1 item was just taken". Keep the focus where it was.

### A4. Nothing shows that a card opens an item page (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the photo and title are links but look like plain content. There's only an underline on hover, and touch screens have no hover.
- **Fix:** a subtle hint, such as a "›" after the title or a slight lift on press (`:active`).

### A5. Differences from the mockup that aren't documented yet (**should fix, docs only**)
**Status:** ✅ Fixed 2026-11-22: the M1 deviations are listed in `UI_BACKLOG.md`.

- **Problem:** `UI_BACKLOG.md` lists M1 ideas but not the M1 deviations:
  - dropdowns instead of filter chips
  - no search field ("Search denim jacket")
  - no "New in today" filter
  - a "Try this on" button on every card
  - "24 items" instead of "38 items match"
  - prices as "€45.00" instead of "M · €32".
- **Why it matters:** CLAUDE.md asks for every deviation to be recorded, with its reason.
- **Fix:** add a "Deviations" list to the M1 section. Search and "New in today" aren't in SPEC, so they're backlog ideas.

### A6. Open items already in the backlog (M1)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- Multi-select filters (**nice to have**).
- Price steps up to the highest price (**nice to have**).
- "Clear filters" only when a filter is active (**should fix**): an always-visible button suggests filters are set when they aren't. It's also part of A1.
- Bigger cards on wide screens (**nice to have**).

---

## Item page (`/item.php?id=`), mockup `2-tryon.png`

### I1. The details line is long and quiet (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** "Trousers · Size M · Navy · Condition: Good" is one line of muted 16px text. Size, the most important fact for the customer, doesn't stand out.
- **Fix:** put the size first and bold ("**Size M** · Trousers · Navy · Good condition"), the same as in the mockup.

### I2. The "taken" page is plain (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** "This item has just been taken" has no image or suggestion, and on a laptop it leaves a large empty page.
- **Fix:** show 3–4 other items in the same size and category below the button. That needs a SPEC line first.

### I3. Open deviations (already recorded)
**Status:** No action needed: already documented in `UI_BACKLOG.md`.

No thumbnails, no "Your request" panel, the category added, the price as €28.00, and "Back to the archive". All are documented in `UI_BACKLOG.md`; nothing to do.

---

## Try-on bar and list (archive and item page)

### T1. A taken item stays on the list until you send it (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** if another tablet requests an item that's on my list, its card disappears from the archive within 10 seconds, but it stays on my list. I only find out when I press "Send request".
- **Why it matters:** this is the confusing moment SPEC M2 tries to avoid ("the customer sees which one").
- **Fix:** after each archive refresh (or every 10 seconds on the item page), mark list items that are no longer in the archive as "Just taken", with a Remove button. Don't count them toward the 5.

### T2. No visible way to close the panel (**should fix**)
**Status:** ✅ Fixed 2026-11-22: a "✕ Close" button in the panel, plus a ▲/▼ on the bar.

- **Problem:** the open list closes with Escape, by tapping the bar text "Your try-on list · 5 items", or by tapping a card button. There's no visible close button, and the bar text doesn't look tappable.
- **Why it matters:** on a tablet there's no Escape key. A customer can get stuck with the panel covering 70% of the screen.
- **Fix:** add a "Close" button (or ✕ with `aria-label="Close the try-on list"`) at the top right of the panel. Add a ▲/▼ chevron to the bar text.

### T3. Adding an item isn't announced (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the item count in the bar changes, but it isn't a live region. A screen reader only hears the button change to "On your list ✓".
- **Fix:** an `aria-live="polite"` region announcing "Wide-leg sailor trousers added. 2 items on your list."

### T4. The bar text wraps on a phone (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** at 375px, "Your try-on list · 5 items" wraps onto two lines next to "Send to fitting room".
- **Fix:** below 400px, shorten it to "Try-on list · 5" or "5 items".

### T5. The send button differs from the mockup (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the mockup has "Send to fitting room →" in light terracotta with dark text (7.4:1); the build uses the solid terracotta button.
- **Fix:** either is fine for contrast. Record the choice in the backlog, or follow the mockup, which separates it more clearly from the dark bar.

---

## Request status (`/request.php?id=`)

### R1. Mostly good
**Status:** No action needed: already fine.

The 44px step circles, the large status line in a live region, the room number, the item thumbnails, the large "Back to the archive" button and the countdown all work at 375px.

### R2. The countdown can surprise a customer who is still reading (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** after "Ready", the page returns to the archive after 60 seconds (SPEC). The countdown line isn't next to the button that would stop it, and nothing can stop it.
- **Fix:** add "Stay on this page" next to the countdown. This would need a SPEC change, so it's only an idea.

### R3. The steps and the status line say the same thing (**nice to have**)
**Status:** No action needed: the repetition helps on a shared tablet.

- **Problem:** "Sent / Being fetched / Ready" plus "Request sent. Staff will start fetching your items soon." repeat each other.
- **Fix:** none needed; the repetition helps on a shared tablet. Possibly shorten the status line.

---

## Login (`/staff/login.php`)

### L1. "Staff" in the header looks like a button (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

See G2.

### L2. The error isn't linked to the password field (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** on a failed login, `aria-describedby` points from the email field to the error, but not from the password field.
- **Fix:** add the same `aria-describedby` to the password field.

### L3. Good otherwise
**Status:** No action needed: already fine.

Labels, `autocomplete="username"` and `"current-password"`, a full-width "Log in" button, a plain error message, and the link back for customers.

---

## Staff queue & fitting rooms (`/staff/`), mockup `3-staff.png`

### S1. The result of a tap is out of view (**must fix**)
**Status:** ✅ Fixed 2026-11-22: a toast fixed to the bottom of the screen, announced to screen readers, with the shelf code in bold and "Nothing changed" in its own style; the end-to-end test checks it is on screen.

- **Problem:** after "Start fetching", "Ready", "Sold" or "Back to archive", `action.php` redirects to `/staff/#queue` or `/staff/#room-N`. The browser scrolls to that anchor, but the message ("Sold: Wool coat (€40.00).", "…Put it on shelf B-09.", or "Nothing changed: …") sits at the top of the page, above the queue. On a phone, a tablet and a laptop it's usually scrolled out of view.
- **Why it matters:**
  - Staff don't see the confirmation, the shelf code for putting an item back, or that a double tap was refused.
  - It's the main staff screen, and the shelf code is information they need.
  - The message is a `role="status"` that appears on page load, so screen readers may not announce it either.
- **Fix:** show the message right where the tap happened: inside the room or queue card that was changed, or as a toast fixed to the bottom of the screen for about 6 seconds. Move the focus to it (`tabindex="-1"`). Keep "Put it on shelf B-09" in bold.

### S2. On a phone or tablet in portrait the rooms are far below the queue (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** below 900px the board is one column. With 3 requests in the queue, "Fitting rooms" starts about 1,100px down.
- **Why it matters:** staff switch between the queue and the rooms all the time. Tablets in portrait are below 900px.
- **Fix:** add a sticky jump bar under the header on small screens: "Queue (3) · Rooms (1 ready)". Or two tabs, "Queue" and "Rooms". Or the two columns already from 700px.

### S3. "Waiting 143 min" is hard to read (**should fix**)
**Status:** ✅ Fixed 2026-11-22: `format_waiting()`: "waiting 2 h 23 min" from 60 minutes on (tested).

- **Problem:** waiting times over an hour show only as minutes.
- **Fix:** "waiting 2 h 23 min" from 60 minutes. Also consider highlighting requests waiting longer than 10 minutes (the pill or the line in terracotta).

### S4. The 10-second refresh can take away keyboard focus (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** `staff.js` replaces the whole board when something changed. If focus was on a button inside the board (keyboard or switch user), it goes back to the top of the page.
- **Fix:** before replacing, remember the focused element's `name` and value (for example `sell`, request and item id); afterwards, focus the same button again. Or skip the refresh while focus is inside the board.

### S5. Queue buttons don't say which request (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** "Start fetching" and "Ready in fitting room 2" repeat for each request. The card has `aria-label="Request 7"`, but not every screen reader reads it when you jump to a button.
- **Fix:** `aria-label="Start fetching request 7"`. The Sold and Back to archive buttons already say which item.

### S6. Open deviations (already recorded)
**Status:** No action needed: already documented in `UI_BACKLOG.md`.

Fitting rooms in place of buy-in, one card per request, shelf codes as `A-08`, and the action buttons. All are documented; nothing to do.

---

## Buy-in (`/staff/intake.php`), mockup `3-staff.png` (right column)

### B1. The file field is in the computer's language (**should fix**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the native file field shows "Bestand kiezen / Geen bestand gekozen" (from the Dutch Windows settings) in an English interface. It's also the only control that doesn't look like the others.
- **Fix:**
  - Visually hide the native input (still keyboard-reachable).
  - Show a styled label button, "Choose photo", plus the chosen file name.
  - On phones, also offer `capture="environment"` so staff can take the photo straight away.

### B2. The payout amounts are hidden in a sentence (**should fix**)
**Status:** ✅ Fixed 2026-11-22: "Cash · €12.50" and "Store credit · €15.00" on the choices, updated while typing.

- **Problem:** the mockup shows the choice as two buttons with the amounts on them ("Cash €8.00 | Credit €9.60"). The build shows "Cash" and "Store credit (cash × 1.2)", with the amount in a separate sentence.
- **Why it matters:** the seller chooses between two amounts. Seeing both side by side is exactly the decision the mockup designed for.
- **Fix:** update the radio labels live, "Cash · €8.00" and "Store credit · €9.60". Keep the sentence as the summary.

### B3. Deviations from the mockup aren't documented (**should fix, docs only**)
**Status:** ✅ Fixed 2026-11-22: a "Buy-in" section in `UI_BACKLOG.md`.

- **Problem:** `UI_BACKLOG.md` has no buy-in section. The differences: a full form instead of the compact card, several items per buy-in, "Save buy-in" instead of "Add to archive", "Shelf code" with a hint instead of "Store at: C-07-03 (suggested)", and the photo upload.
- **Fix:** add a "Buy-in (mockup 3, right column)" section to the backlog.

### B4. The main button is placed differently (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** "Save buy-in" is full width on a phone but small and left-aligned on a laptop, while "Add item" above it is full width at every size. The secondary action looks bigger than the main one.
- **Fix:** make "Add item" auto width (left-aligned, secondary), and "Save buy-in" at least as wide, or right-aligned at the end of the form.

### B5. Good otherwise
**Status:** No action needed: already fine.

The error summary with links, the errors at each field, typed values and photos kept after an error, the suggested shelf code, the live payout total, and the fields stacked at 375px.

---

## Dashboard (`/manager/`), mockup `4-dashboard.png`

### D1. On a phone the three tiles fill the first screen (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** at 375px the three large tiles are stacked and take about 520px. The funnel and slow movers start below the first screen.
- **Why it matters:** it's mainly a laptop screen (the "manager" role), so this is minor.
- **Fix:** below 600px, show the tiles in a compact 3-column row of numbers, or one tile per row with the number next to the label.

### D2. The funnel bar colours are faint (**nice to have**)
**Status:** ➡ Moved to [UI_BACKLOG.md → Next version](UI_BACKLOG.md#next-version-from-the-ui-review-2026-11-22).

- **Problem:** the "on their way" (#D9C7A7) and "in a fitting room" (#8FAE88) parts of the bar are 1.45:1 and 2.1:1 against the page.
- **Why it matters:** the table under it gives every number, so no information depends on the colours (WCAG 1.4.11 is met through the table). The bar is still hard to read.
- **Fix:** darker tints (for example #B39A72 and #5F8A58), or a 1px dark outline around the bar.

### D3. The UI backlog text is out of date (**should fix, docs only**)
**Status:** ✅ Fixed 2026-11-22: "Decided" in M7 item 2, and the missing blank line, in `UI_BACKLOG.md`.

- **Problem:** M7 item 2 in `UI_BACKLOG.md` says the funnel goes "Requested", "Tried", "Bought". It now reads "Requested → Decided → Bought". There's also a missing blank line before the "## M4/M6" heading, so it may not render as a heading.
- **Fix:** update both.

### D4. Good otherwise
**Status:** No action needed: already fine.

A clear hierarchy (the three numbers first), a quiet line for the extra facts, the toggle with `aria-current`, the table with right-aligned numbers, and "–" for empty figures.

---

## Order of work

1. **Must fix:** G1 (field borders), S1 (staff tap feedback).
2. **Should fix:**
   - customer side: A2 (try-on button names), T1 (taken items on the list), T2 (visible close), A1 (filters on a phone), A3 (refresh shifting cards)
   - staff side: S2 (queue and rooms on a phone or tablet), S3 (waiting time), S4 (refresh and focus), B1 (photo field), B2 (payout amounts)
   - docs: A5, B3 and D3
   - G2/L1 (the "Staff" label), T3 (announcing an added item), A6 ("Clear filters" only when active).
3. **Nice to have:** the rest.
