-- Backroom demo data. Run after schema.sql in the same psql call:
--   psql $env:DATABASE_URL -f sql/schema.sql -f sql/seed.sql
--
-- Demo login: staff@backroom.test / backroom-demo
--
-- All dates are relative to now(), so "today" and "this week" figures on the
-- dashboard stay realistic whenever the database is reset.
--
-- Story told by the data (40 items):
--   27 in_archive (5 slow movers from a buy-in 95 days ago, 9 returned from fitting rooms)
--    3 requested       -> request 6 (fetching) and request 7 (new)
--    3 in_fitting_room -> request 5 (ready)
--    7 sold            -> closed requests 1-4, 2 of them today
--   Closed requests this week: 16 items tried, 7 bought.

BEGIN;

-- Helpers for this session only. Clamped so "this week" never slips into last
-- week and "today" never slips into yesterday, whatever time the reset runs.
CREATE FUNCTION pg_temp.days_ago(d INTEGER) RETURNS TIMESTAMPTZ
    LANGUAGE sql AS $$ SELECT now() - make_interval(days => d) $$;
-- "This week" (from Monday) and "today" in shop time, Porto (Europe/Lisbon), like the dashboard.
CREATE FUNCTION pg_temp.this_week(hours_ago INTEGER) RETURNS TIMESTAMPTZ
    LANGUAGE sql AS $$ SELECT GREATEST(date_trunc('week', now() AT TIME ZONE 'Europe/Lisbon') AT TIME ZONE 'Europe/Lisbon',
                                       now() - make_interval(hours => hours_ago)) $$;
CREATE FUNCTION pg_temp.today(minutes_ago INTEGER) RETURNS TIMESTAMPTZ
    LANGUAGE sql AS $$ SELECT GREATEST(date_trunc('day', now() AT TIME ZONE 'Europe/Lisbon') AT TIME ZONE 'Europe/Lisbon',
                                       now() - make_interval(mins => minutes_ago)) $$;

-- ---------------------------------------------------------------------------
-- Staff (password: backroom-demo, hashed with PHP password_hash)
-- ---------------------------------------------------------------------------
INSERT INTO staff_users (id, email, password_hash, name) VALUES
(1, 'staff@backroom.test', '$2y$10$5FvRoUrmmDM1JVk7w4z9P.yXfMKKjHIA3gNoP7e6vDplUVNKvmsW2', 'Demo Staff');

-- ---------------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------------
INSERT INTO categories (id, name) VALUES
(1, 'Tops'),
(2, 'Knitwear'),
(3, 'Trousers'),
(4, 'Dresses'),
(5, 'Jackets & coats');

-- ---------------------------------------------------------------------------
-- Buy-ins: cash_amount = sum of the items' purchase prices below.
-- Store credit payout = cash_amount x 1.2.
-- ---------------------------------------------------------------------------
INSERT INTO buy_ins (id, staff_id, payout_type, cash_amount, payout_amount, created_at) VALUES
(1, 1, 'cash',         50.00, 50.00, pg_temp.days_ago(95)),   -- items 1-5 (slow movers)
(2, 1, 'store_credit', 61.00, 73.20, pg_temp.days_ago(50)),   -- items 6-9
(3, 1, 'cash',         54.00, 54.00, pg_temp.days_ago(45)),   -- items 10-14
(4, 1, 'store_credit', 62.00, 74.40, pg_temp.days_ago(30)),   -- items 15-19
(5, 1, 'cash',         59.00, 59.00, pg_temp.days_ago(21)),   -- items 20-24
(6, 1, 'cash',         58.50, 58.50, pg_temp.days_ago(14)),   -- items 25-29
(7, 1, 'store_credit', 48.00, 57.60, pg_temp.days_ago(8)),    -- items 30-34
(8, 1, 'store_credit', 62.00, 74.40, pg_temp.this_week(60));  -- items 35-40 (store credit this week)

-- ---------------------------------------------------------------------------
-- Items
-- ---------------------------------------------------------------------------
INSERT INTO items (id, buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status) VALUES
-- buy-in 1 (95 days ago, cash 50.00)
( 1, 1, 1, 'Striped Breton top',          'M',   'navy',  'good',      6.00, 18.00, 'assets/uploads/seed/item-01.svg', 'A-01', 'in_archive'),
( 2, 1, 2, 'Chunky cable-knit jumper',    'L',   'beige', 'good',      9.00, 28.00, 'assets/uploads/seed/item-02.svg', 'A-02', 'in_archive'),
( 3, 1, 3, 'Corduroy wide-leg trousers',  'S',   'brown', 'fair',      7.00, 22.00, 'assets/uploads/seed/item-03.svg', 'A-03', 'in_archive'),
( 4, 1, 4, 'Floral midi tea dress',       'M',   'red',   'good',     10.00, 32.00, 'assets/uploads/seed/item-04.svg', 'A-04', 'in_archive'),
( 5, 1, 5, 'Waxed cotton field jacket',   'XL',  'green', 'fair',     18.00, 55.00, 'assets/uploads/seed/item-05.svg', 'A-05', 'in_archive'),
-- buy-in 2 (50 days ago, store credit 61.00 -> 73.20)
( 6, 2, 1, 'Silk blouse with bow',        'S',   'white', 'like_new', 12.00, 35.00, 'assets/uploads/seed/item-06.svg', 'A-06', 'in_archive'),
( 7, 2, 2, 'Merino roll-neck',            'M',   'grey',  'good',     10.00, 30.00, 'assets/uploads/seed/item-07.svg', 'A-07', 'in_archive'),
( 8, 2, 3, 'Pleated wool trousers',       'L',   'grey',  'good',     11.00, 34.00, 'assets/uploads/seed/item-08.svg', 'A-08', 'sold'),
( 9, 2, 5, 'Double-breasted wool coat',   'M',   'black', 'good',     28.00, 85.00, 'assets/uploads/seed/item-09.svg', 'A-09', 'in_archive'),
-- buy-in 3 (45 days ago, cash 54.00)
(10, 3, 1, 'Linen button-down shirt',     'L',   'white', 'good',      7.00, 22.00, 'assets/uploads/seed/item-10.svg', 'A-10', 'in_archive'),
(11, 3, 4, 'Black slip dress',            'S',   'black', 'like_new', 11.00, 34.00, 'assets/uploads/seed/item-11.svg', 'B-01', 'in_archive'),
(12, 3, 3, 'Straight-leg jeans',          'M',   'blue',  'good',      9.00, 28.00, 'assets/uploads/seed/item-12.svg', 'B-02', 'requested'),
(13, 3, 2, 'Mohair cardigan',             'S',   'pink',  'good',     13.00, 38.00, 'assets/uploads/seed/item-13.svg', 'B-03', 'sold'),
(14, 3, 5, 'Denim trucker jacket',        'L',   'blue',  'good',     14.00, 42.00, 'assets/uploads/seed/item-14.svg', 'B-04', 'in_fitting_room'),
-- buy-in 4 (30 days ago, store credit 62.00 -> 74.40)
(15, 4, 4, 'Jersey wrap dress',           'M',   'green', 'good',      9.00, 29.00, 'assets/uploads/seed/item-15.svg', 'B-05', 'sold'),
(16, 4, 1, 'Graphic band T-shirt',        'XL',  'black', 'fair',      3.00, 12.00, 'assets/uploads/seed/item-16.svg', 'B-06', 'in_archive'),
(17, 4, 2, 'Fair Isle sweater',           'L',   'navy',  'good',     12.00, 36.00, 'assets/uploads/seed/item-17.svg', 'B-07', 'in_archive'),
(18, 4, 3, 'Cargo trousers',              'M',   'beige', 'good',      8.00, 26.00, 'assets/uploads/seed/item-18.svg', 'B-08', 'in_archive'),
(19, 4, 5, 'Leather biker jacket',        'S',   'black', 'good',     30.00, 95.00, 'assets/uploads/seed/item-19.svg', 'B-09', 'in_fitting_room'),
-- buy-in 5 (21 days ago, cash 59.00)
(20, 5, 1, 'Oversized flannel shirt',     'L',   'red',   'good',      6.00, 20.00, 'assets/uploads/seed/item-20.svg', 'B-10', 'sold'),
(21, 5, 4, 'Linen shirt dress',           'L',   'beige', 'like_new', 12.00, 38.00, 'assets/uploads/seed/item-21.svg', 'C-01', 'requested'),
(22, 5, 2, 'Cashmere crew-neck',          'M',   'beige', 'like_new', 22.00, 65.00, 'assets/uploads/seed/item-22.svg', 'C-02', 'in_archive'),
(23, 5, 3, 'High-waisted mom jeans',      'XS',  'blue',  'good',      9.00, 27.00, 'assets/uploads/seed/item-23.svg', 'C-03', 'sold'),
(24, 5, 5, 'Quilted gilet',               'M',   'navy',  'good',     10.00, 30.00, 'assets/uploads/seed/item-24.svg', 'C-04', 'in_archive'),
-- buy-in 6 (14 days ago, cash 58.50)
(25, 6, 1, 'Ribbed tank top',             'XS',  'white', 'good',      2.50,  8.00, 'assets/uploads/seed/item-25.svg', 'C-05', 'in_archive'),
(26, 6, 4, 'Velvet party dress',          'S',   'red',   'good',     14.00, 45.00, 'assets/uploads/seed/item-26.svg', 'C-06', 'requested'),
(27, 6, 2, 'Striped cotton jumper',       'XL',  'grey',  'good',      7.00, 22.00, 'assets/uploads/seed/item-27.svg', 'C-07', 'in_archive'),
(28, 6, 3, 'Black tailored trousers',     'M',   'black', 'like_new', 10.00, 32.00, 'assets/uploads/seed/item-28.svg', 'C-08', 'sold'),
(29, 6, 5, 'Classic trench coat',         'L',   'beige', 'good',     25.00, 75.00, 'assets/uploads/seed/item-29.svg', 'C-09', 'in_fitting_room'),
-- buy-in 7 (8 days ago, store credit 48.00 -> 57.60)
(30, 7, 1, 'Polka-dot blouse',            'M',   'navy',  'like_new',  8.00, 24.00, 'assets/uploads/seed/item-30.svg', 'C-10', 'sold'),
(31, 7, 4, 'Knitted sweater dress',       'M',   'grey',  'good',     12.00, 36.00, 'assets/uploads/seed/item-31.svg', 'D-01', 'in_archive'),
(32, 7, 2, 'Oversized hoodie',            'XXL', 'green', 'fair',      5.00, 16.00, 'assets/uploads/seed/item-32.svg', 'D-02', 'in_archive'),
(33, 7, 3, 'Linen drawstring trousers',   'S',   'white', 'good',      7.00, 24.00, 'assets/uploads/seed/item-33.svg', 'D-03', 'in_archive'),
(34, 7, 5, 'Corduroy blazer',             'M',   'brown', 'good',     16.00, 48.00, 'assets/uploads/seed/item-34.svg', 'D-04', 'in_archive'),
-- buy-in 8 (this week, store credit 62.00 -> 74.40)
(35, 8, 1, 'Embroidered peasant top',     'S',   'white', 'good',      6.00, 19.00, 'assets/uploads/seed/item-35.svg', 'D-05', 'in_archive'),
(36, 8, 4, 'Pleated maxi dress',          'L',   'blue',  'good',     10.00, 30.00, 'assets/uploads/seed/item-36.svg', 'D-06', 'in_archive'),
(37, 8, 2, 'Alpaca blend cardigan',       'L',   'brown', 'like_new', 15.00, 45.00, 'assets/uploads/seed/item-37.svg', 'D-07', 'in_archive'),
(38, 8, 3, 'Wide-leg sailor trousers',    'M',   'navy',  'good',      9.00, 28.00, 'assets/uploads/seed/item-38.svg', 'D-08', 'in_archive'),
(39, 8, 5, 'Puffer jacket',               'XL',  'black', 'good',     15.00, 45.00, 'assets/uploads/seed/item-39.svg', 'D-09', 'in_archive'),
(40, 8, 1, 'Vintage rugby shirt',         'L',   'green', 'good',      7.00, 22.00, 'assets/uploads/seed/item-40.svg', 'D-10', 'in_archive');

-- ---------------------------------------------------------------------------
-- Try-on requests (ids in time order)
-- ---------------------------------------------------------------------------
INSERT INTO tryon_requests (id, fitting_room, status, created_at, ready_at, closed_at) VALUES
-- closed this week
(1, 1, 'closed',   pg_temp.this_week(52), pg_temp.this_week(51), pg_temp.this_week(50)),
(2, 2, 'closed',   pg_temp.this_week(30), pg_temp.this_week(29), pg_temp.this_week(28)),
(3, 3, 'closed',   pg_temp.this_week(20), pg_temp.this_week(19), pg_temp.this_week(18)),
-- closed today
(4, 1, 'closed',   pg_temp.today(200),    pg_temp.today(185),    pg_temp.today(150)),
-- open: the fetch queue and the fitting rooms
(5, 3, 'ready',    now() - interval '35 minutes', now() - interval '25 minutes', NULL),
(6, 2, 'fetching', now() - interval '12 minutes', NULL, NULL),
(7, 1, 'new',      now() - interval '4 minutes',  NULL, NULL);

-- outcome_at: closed requests got their outcomes when they closed.
INSERT INTO request_items (request_id, item_id, outcome, outcome_at)
SELECT v.request_id, v.item_id, v.outcome, CASE WHEN v.outcome IS NULL THEN NULL ELSE r.closed_at END
FROM (VALUES
-- request 1: 2 of 4 bought
(1,  8, 'sold'), (1,  9, 'returned'), (1, 15, 'sold'), (1, 22, 'returned'),
-- request 2: 2 of 5 bought
(2,  2, 'returned'), (2, 13, 'sold'), (2, 17, 'returned'), (2, 23, 'sold'), (2, 31, 'returned'),
-- request 3: 1 of 3 bought
(3,  6, 'returned'), (3, 28, 'sold'), (3, 33, 'returned'),
-- request 4 (today): 2 of 4 bought
(4, 20, 'sold'), (4, 24, 'returned'), (4, 30, 'sold'), (4, 34, 'returned'),
-- request 5 (ready): in the fitting room, not handled yet
(5, 14, NULL), (5, 19, NULL), (5, 29, NULL),
-- request 6 (fetching) and 7 (new)
(6, 26, NULL),
(7, 12, NULL), (7, 21, NULL)
) AS v (request_id, item_id, outcome)
JOIN tryon_requests r ON r.id = v.request_id;

-- Sales: one per sold request item, at the moment the request closed, at the sale price.
INSERT INTO sales (item_id, request_id, sold_at, price)
SELECT ri.item_id, ri.request_id, r.closed_at, i.sale_price
FROM request_items ri
JOIN tryon_requests r ON r.id = ri.request_id
JOIN items i ON i.id = ri.item_id
WHERE ri.outcome = 'sold'
ORDER BY ri.request_id, ri.item_id;

-- Explicit ids were used above: move the id counters past them.
DO $$
BEGIN
    PERFORM setval(pg_get_serial_sequence('staff_users',    'id'), (SELECT max(id) FROM staff_users));
    PERFORM setval(pg_get_serial_sequence('categories',     'id'), (SELECT max(id) FROM categories));
    PERFORM setval(pg_get_serial_sequence('buy_ins',        'id'), (SELECT max(id) FROM buy_ins));
    PERFORM setval(pg_get_serial_sequence('items',          'id'), (SELECT max(id) FROM items));
    PERFORM setval(pg_get_serial_sequence('tryon_requests', 'id'), (SELECT max(id) FROM tryon_requests));
    PERFORM setval(pg_get_serial_sequence('sales',          'id'), (SELECT max(id) FROM sales));
END $$;

-- ---------------------------------------------------------------------------
-- Self-check: the seed fails (and rolls back) if the data is inconsistent.
-- ---------------------------------------------------------------------------
DO $$
DECLARE n INTEGER;
BEGIN
    -- cash_amount equals the sum of the buy-in's purchase prices
    SELECT count(*) INTO n FROM buy_ins b
    WHERE b.cash_amount <> (SELECT coalesce(sum(purchase_price), 0) FROM items WHERE buy_in_id = b.id);
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % buy-in(s) with wrong cash_amount', n; END IF;

    -- requested items belong to exactly one open new/fetching request
    SELECT count(*) INTO n FROM items i WHERE i.status = 'requested' AND 1 <> (
        SELECT count(*) FROM request_items ri JOIN tryon_requests r ON r.id = ri.request_id
        WHERE ri.item_id = i.id AND r.status IN ('new', 'fetching'));
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % requested item(s) not in a new/fetching request', n; END IF;

    -- in_fitting_room items belong to exactly one ready request, not yet handled
    SELECT count(*) INTO n FROM items i WHERE i.status = 'in_fitting_room' AND 1 <> (
        SELECT count(*) FROM request_items ri JOIN tryon_requests r ON r.id = ri.request_id
        WHERE ri.item_id = i.id AND r.status = 'ready' AND ri.outcome IS NULL);
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % fitting-room item(s) not in a ready request', n; END IF;

    -- sold items have a sale row and a request item with outcome sold, and vice versa
    SELECT count(*) INTO n FROM items i
    WHERE (i.status = 'sold') <> EXISTS (SELECT 1 FROM sales s WHERE s.item_id = i.id)
       OR (i.status = 'sold') <> EXISTS (SELECT 1 FROM request_items ri WHERE ri.item_id = i.id AND ri.outcome = 'sold');
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % item(s) with sold status, sale and outcome out of step', n; END IF;

    -- open requests have no outcomes yet; closed requests have every item handled
    SELECT count(*) INTO n FROM request_items ri JOIN tryon_requests r ON r.id = ri.request_id
    WHERE (r.status <> 'closed' AND ri.outcome IS NOT NULL) OR (r.status = 'closed' AND ri.outcome IS NULL);
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % request item(s) with outcome not matching request status', n; END IF;

    -- returned items are back in the archive (unless requested again later)
    SELECT count(*) INTO n FROM request_items ri JOIN items i ON i.id = ri.item_id
    WHERE ri.outcome = 'returned' AND i.status <> 'in_archive'
      AND NOT EXISTS (SELECT 1 FROM request_items r2 WHERE r2.item_id = ri.item_id AND r2.request_id > ri.request_id);
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % returned item(s) not back in the archive', n; END IF;

    -- every request has 1 to 5 items
    SELECT count(*) INTO n FROM tryon_requests r
    WHERE (SELECT count(*) FROM request_items ri WHERE ri.request_id = r.id) NOT BETWEEN 1 AND 5;
    IF n > 0 THEN RAISE EXCEPTION 'seed check: % request(s) without 1-5 items', n; END IF;
END $$;

COMMIT;
