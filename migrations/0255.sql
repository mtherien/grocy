-- Store integrations for sending shopping lists to external stores (Kroger, etc.)

CREATE TABLE store_integrations (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
	name TEXT NOT NULL,
	store_type TEXT NOT NULL,
	access_token TEXT,
	refresh_token TEXT,
	token_expires_at DATETIME,
	configuration TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
