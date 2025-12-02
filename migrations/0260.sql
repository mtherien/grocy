-- Shop mode state tracking

-- Add shop mode state columns to shopping_lists table
ALTER TABLE shopping_lists ADD COLUMN shop_mode_active INTEGER DEFAULT 0;
ALTER TABLE shopping_lists ADD COLUMN shop_mode_location_id INTEGER REFERENCES shopping_locations(id);
