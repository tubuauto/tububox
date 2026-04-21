DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'quote_fee_cents'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN quote_fee_cents INTEGER NOT NULL DEFAULT 0;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'quote_currency'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN quote_currency VARCHAR(10) NOT NULL DEFAULT 'CAD';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'quote_distance_km'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN quote_distance_km NUMERIC(10,2);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'quote_status'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN quote_status VARCHAR(20) NOT NULL DEFAULT 'none';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'payment_status'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid';
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'payment_amount_cents'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN payment_amount_cents INTEGER NOT NULL DEFAULT 0;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'payment_method'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN payment_method VARCHAR(30);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'payment_reference'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN payment_reference VARCHAR(100);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'deliveries' AND column_name = 'paid_at'
    ) THEN
        ALTER TABLE deliveries ADD COLUMN paid_at TIMESTAMP;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_deliveries_payment_status ON deliveries(payment_status);
CREATE INDEX IF NOT EXISTS idx_deliveries_grab_pool ON deliveries(tenant_id, source_type, status, payment_status, assigned_driver_id);

UPDATE deliveries
SET quote_fee_cents = CASE WHEN quote_fee_cents <= 0 THEN delivery_fee_cents ELSE quote_fee_cents END,
    quote_currency = CASE WHEN COALESCE(quote_currency, '') = '' THEN cod_currency ELSE quote_currency END
WHERE quote_fee_cents <= 0 OR COALESCE(quote_currency, '') = '';

UPDATE deliveries
SET quote_status = 'accepted',
    payment_status = 'paid',
    payment_amount_cents = CASE WHEN payment_amount_cents <= 0 THEN delivery_fee_cents ELSE payment_amount_cents END,
    paid_at = COALESCE(paid_at, created_at)
WHERE status <> 'awaiting_payment';
