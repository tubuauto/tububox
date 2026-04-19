DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'requester_user_id'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN requester_user_id BIGINT;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_deliveries_requester_user'
    ) THEN
        ALTER TABLE deliveries
            ADD CONSTRAINT fk_deliveries_requester_user
            FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_deliveries_requester_user_id ON deliveries(requester_user_id);
CREATE INDEX IF NOT EXISTS idx_deliveries_source_requester ON deliveries(source_type, requester_user_id);

