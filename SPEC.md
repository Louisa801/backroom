# Backroom — MVP Specification

Backroom is a second-hand clothing shop with a coffee bar up front and the stock stored densely in a back-room archive. Customers browse the whole archive on in-store tablets and ask staff to bring items to a fitting room. Walk-in sellers can sell their old clothes to the shop for cash or store credit.

This document defines what the MVP (Assignment 2) must do. It is based on the user stories and mockups from Assignment 1. Anything not listed here is out of scope.

## Users

| Role | Device | Login |
|---|---|---|
| Customer | Tablet at the coffee bar | No |
| Staff | Staff screen (laptop/tablet behind the counter) | Yes |
| Manager | Dashboard (laptop) | Yes, same staff login in the MVP |
| Seller | No screen: staff enter the buy-in for them | No |

## Item lifecycle

Every item has exactly one status. All screens read the same status from the database.

```
in_archive ──(customer requests it)──> requested ──(staff bring it)──> in_fitting_room
     ^                                                                    │
     └──────────────(not bought: back to archive)────────────────────────┤
                                                                          └──(bought)──> sold
```

- Only `in_archive` items are visible on the tablet.
- `sold` is final: a sold item never appears on the tablet again.

A try-on request has its own status: `new` → `fetching` → `ready` → `closed`.

## Core user stories (Must have)

### M1 — Browse and filter the archive
*As a customer, I want to filter the archive by size, category, colour and price, so I only see items I can wear.*
- Filters can be combined.
- Only items with status `in_archive` are shown.
- Results update without reloading the page.
- Each card shows photo, title, size and price.
- Empty result shows a friendly "nothing found" message.

### M2 — Request a try-on
*As a customer, I want to send items to a fitting room, so I can try them on without searching the shop.*
- The customer adds 1 to 5 items to a try-on list and picks a fitting room (1–3).
- Sending the request sets every item to `requested`, so it disappears from other tablets at once.
- If an item was taken by someone else in the meantime, the customer sees which one and the rest of the request still goes through.

### M3 — Follow the request status
*As a customer, I want to see the status of my request, so I know when to go to the fitting room.*
- Shows `new` → `fetching` → `ready` and the fitting-room number.
- Updates automatically (polling every few seconds), no manual refresh.

### M4 — Fetch queue
*As staff, I want a fetch queue with shelf locations, so I can bring items to the fitting room quickly.*
- Open requests are listed oldest first.
- Each request shows its items with shelf code, and the fitting room.
- One tap moves a request to the next status. When a request becomes `ready`, its items become `in_fitting_room`.

### M5 — Buy-in from a walk-in seller
*As staff, I want to register clothes bought from a walk-in seller, so they are in the archive the same day.*
- One buy-in can contain several items.
- Required per item: photo, title, category, size, colour (from the fixed list in Business rules), condition, purchase price (what the shop pays), sale price.
- Payout type: cash or store credit. Store credit = cash amount × 1.2, calculated by the system.
- A shelf code is suggested (next free spot) and can be changed.
- New items get status `in_archive`.
- Invalid input (missing field, negative price, sale price lower than purchase price, file not an image) shows a clear error and keeps what was typed.

### M6 — Mark items sold or back in the archive
*As staff, I want to mark an item as sold or back in the archive, so stock is always correct.*
- Items in a fitting room each have two buttons: "Sold" and "Back to archive".
- "Sold" creates a sale record with date and price and sets status `sold`.
- "Back to archive" sets status `in_archive` again, visible on the tablet straight away.
- When all items of a request are handled, the request is `closed`.

### M7 — Manager dashboard
*As a manager, I want to see stock, sales, margin and try-on conversion, so I can decide what to buy and mark down.*
- Numbers for today and this week: items in archive, items sold, revenue, average margin, try-on → purchase conversion.
- Item funnel: requested → tried → bought.
- Slow movers: items `in_archive` for more than 60 days, counted from the buy-in date.
- Store credit given out this week.

### Staff login
- Staff and manager screens require a login (email + password).
- The customer tablet requires no login.

## Business rules

| Rule | Value |
|---|---|
| Store-credit bonus | × 1.2 of the cash amount |
| Max items per try-on request | 5 |
| Fitting rooms | 3 |
| Slow mover | `in_archive` for more than 60 days, counted from the buy-in date (the clock does not restart when an item returns from a fitting room) |
| Colours | black, white, grey, navy, blue, green, red, pink, beige, brown |
| Margin | (sale price − purchase price) / sale price |
| Conversion | items sold / items with an outcome (sold or returned); items still undecided in a fitting room are left out |

## Screens

| Screen | URL | Role |
|---|---|---|
| Archive (browse + filters + try-on list) | `/` | Customer |
| Item detail | `/item.php?id=` | Customer |
| Request status | `/request.php?id=` | Customer |
| Login | `/staff/login.php` | Staff |
| Fetch queue + fitting rooms | `/staff/` | Staff |
| Buy-in | `/staff/intake.php` | Staff |
| Dashboard | `/manager/` | Manager |

## Out of scope for the MVP
- Browsing or reserving from home, customer accounts, online payment.
- Consignment and seller accounts or payouts over time.
- Separate manager role and permissions.
- Printable shelf labels, sound alerts, suggested markdown prices.
- Coffee-bar sales.

## Definition of done (per user story)
1. All acceptance criteria above work on the real database.
2. Automated tests for the logic pass.
3. Checked by hand on desktop and on a phone-sized screen.
4. Committed to GitHub with a clear message.
