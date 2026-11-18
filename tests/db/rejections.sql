-- Constraint tests: the database must refuse bad data.
-- Run on a freshly seeded database (the tests use seed ids and shelf codes):
--   PowerShell: psql $env:DATABASE_URL -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql
--   bash:       psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f tests/db/rejections.sql
-- Each test runs in its own transaction and is rolled back, so the data is unchanged.
-- Every test prints PASS; the first FAIL stops the run with a non-zero exit code.

\pset tuples_only on
\pset format unaligned
\set VERBOSITY terse

-- PASS only if the statement is refused by the expected constraint.
CREATE FUNCTION pg_temp.expect_rejected(label TEXT, stmt TEXT, expected TEXT) RETURNS TEXT
LANGUAGE plpgsql AS $$
DECLARE got TEXT;
BEGIN
    BEGIN
        EXECUTE stmt;
    EXCEPTION WHEN OTHERS THEN
        GET STACKED DIAGNOSTICS got = CONSTRAINT_NAME;
        IF got = expected THEN
            RETURN 'PASS  ' || label || '  (' || got || ')';
        END IF;
        RAISE EXCEPTION 'FAIL  %: refused by "%" instead of %', label, coalesce(nullif(got, ''), SQLERRM), expected;
    END;
    RAISE EXCEPTION 'FAIL  %: was accepted, expected % to refuse it', label, expected;
END $$;

-- PASS only if the statement is accepted and changes at least one row.
CREATE FUNCTION pg_temp.expect_accepted(label TEXT, stmt TEXT) RETURNS TEXT
LANGUAGE plpgsql AS $$
DECLARE n BIGINT;
BEGIN
    BEGIN
        EXECUTE stmt;
        GET DIAGNOSTICS n = ROW_COUNT;
    EXCEPTION WHEN OTHERS THEN
        RAISE EXCEPTION 'FAIL  %: refused (%)', label, SQLERRM;
    END;
    IF n = 0 THEN
        RAISE EXCEPTION 'FAIL  %: accepted, but no row was changed', label;
    END IF;
    RETURN 'PASS  ' || label;
END $$;

BEGIN;
SELECT pg_temp.expect_rejected('1. negative purchase price', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'black', 'good', -5.00, 10.00, 'x.jpg', 'E-01') $q$, 'items_purchase_price_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('2. sale price lower than purchase price', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'black', 'good', 20.00, 15.00, 'x.jpg', 'E-01') $q$, 'items_sale_not_below_purchase');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('3. unknown item status', $q$
    UPDATE items SET status = 'lost' WHERE id = 1 $q$, 'items_status_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('4. fitting room 4', $q$
    INSERT INTO tryon_requests (fitting_room) VALUES (4) $q$, 'tryon_requests_fitting_room_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('5. colour not on the list', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'purple', 'good', 5.00, 10.00, 'x.jpg', 'E-01') $q$, 'items_colour_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('6. store credit payout not cash x 1.2', $q$
    INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount)
    VALUES (1, 'store_credit', 10.00, 10.00) $q$, 'buy_ins_payout_matches_type');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('7. shelf code taken by an unsold item', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'black', 'good', 5.00, 10.00, 'x.jpg', 'A-01') $q$, 'items_shelf_code_unsold');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('8. bad shelf code format', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'black', 'good', 5.00, 10.00, 'x.jpg', 'shelf 3') $q$, 'items_shelf_code_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('9. selling the same item twice', $q$
    INSERT INTO sales (item_id, request_id, price) VALUES (8, 1, 34.00) $q$, 'sales_item_id_key');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('10. sale for an item not in that request', $q$
    INSERT INTO sales (item_id, request_id, price) VALUES (1, 1, 18.00) $q$, 'sales_request_id_item_id_fkey');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('11. unknown request status', $q$
    UPDATE tryon_requests SET status = 'done' WHERE id = 7 $q$, 'tryon_requests_status_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('12. request ready without a ready time', $q$
    UPDATE tryon_requests SET status = 'ready' WHERE id = 7 $q$, 'tryon_ready_at_matches_status');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('13. unknown request-item outcome', $q$
    UPDATE request_items SET outcome = 'maybe' WHERE request_id = 5 AND item_id = 14 $q$, 'request_items_outcome_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('14. size not on the list', $q$
    UPDATE items SET size = 'XXXL' WHERE id = 1 $q$, 'items_size_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('15. buy-in that does not exist', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    VALUES (999, 1, 'Test', 'M', 'black', 'good', 5.00, 10.00, 'x.jpg', 'E-01') $q$, 'items_buy_in_id_fkey');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('16. duplicate staff email', $q$
    INSERT INTO staff_users (email, password_hash, name)
    VALUES ('staff@backroom.test', 'x', 'Copy') $q$, 'staff_users_email_key');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('17. item without a photo', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, shelf_code)
    VALUES (1, 1, 'Test', 'M', 'black', 'good', 5.00, 10.00, 'E-01') $q$, 'items_one_photo');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('18. photo wider than 1200px', $q$
    INSERT INTO photos (mime_type, data, width, height) VALUES ('image/jpeg', '\xffd8'::bytea, 1600, 900) $q$, 'photos_width_check');
ROLLBACK;

BEGIN;
SELECT pg_temp.expect_rejected('19. photo that is not a JPEG', $q$
    INSERT INTO photos (mime_type, data, width, height) VALUES ('image/png', '\x89504e47'::bytea, 100, 100) $q$, 'photos_mime_type_check');
ROLLBACK;

BEGIN;
-- Self-contained: makes its own staff user, category, buy-in and sold item on a shelf
-- code that no unsold item uses, so it passes whatever data is in the database.
WITH staff AS (
    INSERT INTO staff_users (email, password_hash, name)
    VALUES ('rejections-test@backroom.test', 'x', 'Rejections test') RETURNING id
), buy_in AS (
    INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount)
    SELECT id, 'cash', 5.00, 5.00 FROM staff RETURNING id
), category AS (
    INSERT INTO categories (name) VALUES ('Rejections test category') RETURNING id
), free_code AS (
    SELECT code
    FROM (SELECT chr(65 + rack) || '-' || lpad(spot::text, 2, '0') AS code, rack, spot
          FROM generate_series(0, 25) AS rack, generate_series(1, 99) AS spot) AS all_codes
    WHERE code NOT IN (SELECT shelf_code FROM items WHERE status <> 'sold')
    ORDER BY rack DESC, spot DESC
    LIMIT 1
)
INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
SELECT buy_in.id, category.id, 'Rejections test: sold item', 'M', 'black', 'good', 5.00, 10.00, 'x.jpg', free_code.code, 'sold'
FROM buy_in, category, free_code;

SELECT pg_temp.expect_accepted('20. reuse the shelf code of a sold item', $q$
    INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
    SELECT buy_in_id, category_id, 'Rejections test: new item', 'M', 'black', 'good', 5.00, 10.00, 'x.jpg', shelf_code
    FROM items WHERE title = 'Rejections test: sold item' $q$);
ROLLBACK;

SELECT 'All constraint tests passed.';
