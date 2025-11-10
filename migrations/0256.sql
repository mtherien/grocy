-- Add store location metadata fields to products table

ALTER TABLE products
ADD COLUMN store_location_aisle TEXT;

ALTER TABLE products
ADD COLUMN store_location_shelf TEXT;

ALTER TABLE products
ADD COLUMN store_location_department TEXT;

ALTER TABLE products
ADD COLUMN store_location_updated DATETIME;

-- Table to store product metadata from different store integrations
CREATE TABLE product_store_metadata (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
	product_id INTEGER NOT NULL,
	store_integration_id INTEGER NOT NULL,
	external_product_id TEXT,
	aisle TEXT,
	shelf TEXT,
	department TEXT,
	category TEXT,
	price REAL,
	price_unit TEXT,
	availability TEXT,
	metadata_json TEXT,
	last_updated DATETIME DEFAULT (datetime('now', 'localtime')),
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX idx_product_store_metadata_product_id ON product_store_metadata(product_id);
CREATE INDEX idx_product_store_metadata_store_integration_id ON product_store_metadata(store_integration_id);
