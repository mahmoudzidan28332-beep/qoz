-- Migration: 2026_05_04_delivery_orders_nullable_address
--
-- 1. Makes pickup_address_id and dropoff_address_id nullable in delivery_orders
--    so an order can be created before the exact addresses are confirmed.
-- 2. Drops the FK constraints on those columns so that address IDs from the
--    form (which may not yet exist in the addresses table) do not cause a
--    foreign-key violation.  Data integrity for address references is
--    enforced at the application layer instead.
--
-- Run once on the live database:
--   mysql -u USER -p DB_NAME < 2026_05_04_delivery_orders_nullable_address.sql

-- Step 1: drop FK constraints on the address columns
ALTER TABLE `delivery_orders`
    DROP FOREIGN KEY `fk_do_pickup`,
    DROP FOREIGN KEY `fk_do_dropoff`;

-- Step 2: make the columns nullable (keeps the index for query performance)
ALTER TABLE `delivery_orders`
    MODIFY `pickup_address_id`  BIGINT(20) UNSIGNED DEFAULT NULL,
    MODIFY `dropoff_address_id` BIGINT(20) UNSIGNED DEFAULT NULL;
