-- Add store_integration_id to shopping_lists table to remember the last used store
ALTER TABLE shopping_lists
ADD store_integration_id INT DEFAULT NULL;
