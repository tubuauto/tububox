DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'pickup_verify_code'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN pickup_verify_code VARCHAR(10);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'dropoff_verify_code'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN dropoff_verify_code VARCHAR(10);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'pickup_verified_at'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN pickup_verified_at TIMESTAMP;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'dropoff_verified_at'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN dropoff_verified_at TIMESTAMP;
    END IF;
END $$;
