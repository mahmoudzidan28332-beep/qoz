-- Migration: Change entities.vendor_type from ENUM to VARCHAR(50)
-- This allows any entity_types.code value to be stored (not just the original 3)
-- Run AFTER entity_types table is seeded with the desired rows.

ALTER TABLE entities
    MODIFY COLUMN vendor_type VARCHAR(50) NULL DEFAULT 'store'
    COMMENT 'References entity_types.code';
