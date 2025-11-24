-- Store Integration Consolidation
-- This migration consolidates store_integration_locations into shopping_locations
-- By merging the functionality, we create a unified store model where shopping_locations
-- can optionally have API integration capabilities.

-- =============================================================================
-- STEP 1: Drop unreleased tables
-- =============================================================================
-- These tables were never released in production, so we can safely drop them
-- and incorporate their functionality directly into shopping_locations

DROP TABLE IF EXISTS store_integration_locations;

-- =============================================================================
-- STEP 2: Enhance shopping_locations with integration fields
-- =============================================================================

-- Add reference to store integration (NULL for simple stores)
ALTER TABLE shopping_locations
ADD COLUMN store_integration_id INTEGER REFERENCES store_integrations(id) ON DELETE SET NULL;

-- Add external location identifier (e.g., Kroger Store ID from their API)
ALTER TABLE shopping_locations
ADD COLUMN external_location_id TEXT;

-- Add primary location flag (one primary per integration)
ALTER TABLE shopping_locations
ADD COLUMN is_primary INTEGER NOT NULL DEFAULT 0;

-- Add physical location fields
ALTER TABLE shopping_locations
ADD COLUMN address TEXT;

ALTER TABLE shopping_locations
ADD COLUMN city TEXT;

ALTER TABLE shopping_locations
ADD COLUMN state TEXT;

ALTER TABLE shopping_locations
ADD COLUMN zip_code TEXT;

ALTER TABLE shopping_locations
ADD COLUMN phone TEXT;

-- Add geolocation fields
ALTER TABLE shopping_locations
ADD COLUMN latitude REAL;

ALTER TABLE shopping_locations
ADD COLUMN longitude REAL;

-- Add metadata storage for full API response
ALTER TABLE shopping_locations
ADD COLUMN metadata_json TEXT;

-- =============================================================================
-- STEP 3: Create indexes for performance
-- =============================================================================

CREATE INDEX idx_shopping_locations_integration
ON shopping_locations(store_integration_id);

CREATE INDEX idx_shopping_locations_external_id
ON shopping_locations(store_integration_id, external_location_id);

CREATE INDEX idx_shopping_locations_primary
ON shopping_locations(store_integration_id, is_primary);

-- =============================================================================
-- STEP 4: Update product_store_metadata to reference shopping_locations
-- =============================================================================

-- The product_store_metadata table stores product information per store location.
-- We need to change it from referencing store_integration_id to shopping_location_id

-- First, rename the old column (backup in case needed)
ALTER TABLE product_store_metadata
RENAME COLUMN store_integration_id TO store_integration_id_old;

-- Add new column referencing shopping_locations
ALTER TABLE product_store_metadata
ADD COLUMN shopping_location_id INTEGER REFERENCES shopping_locations(id) ON DELETE CASCADE;

-- Update indexes
DROP INDEX IF EXISTS idx_product_store_metadata_store_integration_id;

CREATE INDEX idx_product_store_metadata_shopping_location_id
ON product_store_metadata(shopping_location_id);

-- =============================================================================
-- STEP 5: Create enhanced view
-- =============================================================================

CREATE VIEW shopping_locations_enhanced AS
SELECT
    sl.id,
    sl.name,
    sl.description,
    sl.store_integration_id,
    sl.external_location_id,
    sl.address,
    sl.city,
    sl.state,
    sl.zip_code,
    sl.phone,
    sl.latitude,
    sl.longitude,
    sl.is_primary,
    sl.metadata_json,
    sl.row_created_timestamp,

    -- Integration data (if linked)
    si.name AS integration_name,
    si.store_type,
    si.active AS integration_active,

    -- Computed fields
    CASE
        WHEN sl.store_integration_id IS NOT NULL THEN 1
        ELSE 0
    END AS is_integrated,

    CASE
        WHEN sl.store_integration_id IS NOT NULL
        THEN sl.name || ' (' || si.store_type || ')'
        ELSE sl.name
    END AS display_name_with_type,

    -- Product count using this shopping location
    (SELECT COUNT(*)
     FROM products p
     WHERE p.shopping_location_id = sl.id) AS product_count

FROM shopping_locations sl
LEFT JOIN store_integrations si
    ON sl.store_integration_id = si.id;
