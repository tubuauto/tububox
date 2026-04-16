CREATE TABLE IF NOT EXISTS tenants (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'merchant',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tenants_status ON tenants(status);

CREATE TABLE IF NOT EXISTS organizations (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    address TEXT,
    lat NUMERIC(10,7),
    lng NUMERIC(10,7),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_organizations_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_organizations_tenant_id ON organizations(tenant_id);

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT,
    organization_id BIGINT,
    role VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(150),
    password_hash TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_users_tenant_id ON users(tenant_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_email ON users(email) WHERE email IS NOT NULL;

CREATE TABLE IF NOT EXISTS drivers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    tenant_id BIGINT,
    vehicle_type VARCHAR(50),
    license_plate VARCHAR(30),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    online_status BOOLEAN NOT NULL DEFAULT FALSE,
    rating NUMERIC(3,2) NOT NULL DEFAULT 5.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_drivers_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_drivers_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_drivers_tenant_id ON drivers(tenant_id);
CREATE INDEX IF NOT EXISTS idx_drivers_online_status ON drivers(online_status);

CREATE TABLE IF NOT EXISTS api_keys (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    api_key VARCHAR(100) NOT NULL,
    api_secret VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_keys_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_api_keys_key ON api_keys(api_key);
CREATE INDEX IF NOT EXISTS idx_api_keys_tenant_id ON api_keys(tenant_id);

CREATE TABLE IF NOT EXISTS deliveries (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_platform VARCHAR(50),
    source_order_no VARCHAR(100),
    external_ref VARCHAR(100),
    idempotency_key VARCHAR(100),
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(30),
    pickup_address TEXT NOT NULL,
    pickup_lat NUMERIC(10,7),
    pickup_lng NUMERIC(10,7),
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(30),
    dropoff_address TEXT NOT NULL,
    dropoff_lat NUMERIC(10,7),
    dropoff_lng NUMERIC(10,7),
    goods_type VARCHAR(50),
    goods_weight NUMERIC(10,2),
    goods_note TEXT,
    delivery_fee_cents INTEGER NOT NULL DEFAULT 0,
    cod_required BOOLEAN NOT NULL DEFAULT FALSE,
    cod_amount_cents INTEGER NOT NULL DEFAULT 0,
    cod_currency VARCHAR(10) NOT NULL DEFAULT 'CAD',
    cod_status VARCHAR(20) NOT NULL DEFAULT 'none',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    assigned_driver_id BIGINT,
    scheduled_at TIMESTAMP,
    picked_up_at TIMESTAMP,
    delivered_at TIMESTAMP,
    completed_at TIMESTAMP,
    failed_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_deliveries_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_driver
        FOREIGN KEY (assigned_driver_id) REFERENCES drivers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_deliveries_tenant_id ON deliveries(tenant_id);
CREATE INDEX IF NOT EXISTS idx_deliveries_status ON deliveries(status);
CREATE INDEX IF NOT EXISTS idx_deliveries_assigned_driver_id ON deliveries(assigned_driver_id);
CREATE INDEX IF NOT EXISTS idx_deliveries_source_order_no ON deliveries(source_order_no);
CREATE INDEX IF NOT EXISTS idx_deliveries_created_at ON deliveries(created_at);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_deliveries_tenant_idempotency
    ON deliveries(tenant_id, idempotency_key)
    WHERE idempotency_key IS NOT NULL;

CREATE TABLE IF NOT EXISTS delivery_logs (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL,
    note TEXT,
    actor_type VARCHAR(50),
    actor_id BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_logs_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_delivery_logs_delivery_id ON delivery_logs(delivery_id);

CREATE TABLE IF NOT EXISTS delivery_tracking (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT NOT NULL,
    driver_id BIGINT,
    lat NUMERIC(10,7) NOT NULL,
    lng NUMERIC(10,7) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_tracking_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_tracking_driver
        FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_delivery_tracking_delivery_id ON delivery_tracking(delivery_id);

CREATE TABLE IF NOT EXISTS delivery_assignments (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT NOT NULL,
    driver_id BIGINT NOT NULL,
    assigned_by BIGINT,
    note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_assignments_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_assignments_driver
        FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_assignments_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_delivery_assignments_delivery_id ON delivery_assignments(delivery_id);
CREATE INDEX IF NOT EXISTS idx_delivery_assignments_driver_id ON delivery_assignments(driver_id);

CREATE TABLE IF NOT EXISTS proof_of_delivery (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT NOT NULL,
    receiver_name VARCHAR(100),
    proof_type VARCHAR(50),
    proof_image TEXT,
    note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proof_of_delivery_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_proof_of_delivery_delivery_id ON proof_of_delivery(delivery_id);

CREATE TABLE IF NOT EXISTS cod_collections (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT NOT NULL,
    expected_amount_cents INTEGER NOT NULL DEFAULT 0,
    collected_amount_cents INTEGER NOT NULL DEFAULT 0,
    method VARCHAR(50),
    proof_image TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    collected_by_driver_id BIGINT,
    collected_at TIMESTAMP,
    note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cod_collections_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    CONSTRAINT fk_cod_collections_driver
        FOREIGN KEY (collected_by_driver_id) REFERENCES drivers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_cod_collections_delivery_id ON cod_collections(delivery_id);

CREATE TABLE IF NOT EXISTS webhook_endpoints (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    url TEXT NOT NULL,
    event VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    secret VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webhook_endpoints_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_webhook_endpoints_tenant_id ON webhook_endpoints(tenant_id);

CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGSERIAL PRIMARY KEY,
    delivery_id BIGINT,
    webhook_endpoint_id BIGINT,
    payload JSONB NOT NULL,
    response TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webhook_logs_delivery
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE SET NULL,
    CONSTRAINT fk_webhook_logs_endpoint
        FOREIGN KEY (webhook_endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_webhook_logs_delivery_id ON webhook_logs(delivery_id);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_endpoint_id ON webhook_logs(webhook_endpoint_id);

