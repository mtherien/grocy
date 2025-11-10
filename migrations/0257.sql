-- Store locations for each integration

CREATE TABLE store_integration_locations (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
	store_integration_id INTEGER NOT NULL,
	external_location_id TEXT NOT NULL,
	name TEXT NOT NULL,
	address TEXT,
	city TEXT,
	state TEXT,
	zip_code TEXT,
	phone TEXT,
	is_primary INTEGER NOT NULL DEFAULT 0,
	metadata_json TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX idx_store_integration_locations_integration_id ON store_integration_locations(store_integration_id);
CREATE INDEX idx_store_integration_locations_primary ON store_integration_locations(store_integration_id, is_primary);
