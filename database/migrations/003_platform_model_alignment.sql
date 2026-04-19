DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'store_id'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN store_id BIGINT;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_deliveries_store'
    ) THEN
        ALTER TABLE deliveries
            ADD CONSTRAINT fk_deliveries_store
            FOREIGN KEY (store_id) REFERENCES organizations(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_deliveries_store_id ON deliveries(store_id);

ALTER TABLE deliveries
    ALTER COLUMN source_type SET DEFAULT 'merchant_dashboard';

UPDATE users SET role = 'merchant' WHERE role = 'tenant_admin';
UPDATE users SET role = 'operator' WHERE role = 'dispatcher';
UPDATE users SET role = 'rider' WHERE role = 'driver';

UPDATE deliveries SET source_type = 'merchant_dashboard' WHERE source_type IN ('manual', 'merchant_console');
UPDATE deliveries SET source_type = 'merchant_api' WHERE source_type = 'api';
UPDATE deliveries SET source_type = 'marketplace' WHERE source_type = 'platform';

