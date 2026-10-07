-- Mobile app auth layer (LedgerOne Mobile, Phase 1). Purely additive — does
-- not touch the existing web session-auth path (server/api/auth/login.php)
-- or any existing table. A device registers once and gets a long-lived
-- opaque bearer token (stored only as its sha256 hash, never raw) instead of
-- the 1-hour PHP session cookie the web app uses, so the mobile app can stay
-- logged in and sync in the background for weeks at a time.

CREATE TABLE IF NOT EXISTS mobile_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    device_uuid VARCHAR(64) NOT NULL,
    device_name VARCHAR(255) NULL,
    platform ENUM('ios', 'android') NOT NULL,
    push_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    UNIQUE KEY uk_tenant_user_device (tenant_id, user_id, device_uuid),
    KEY idx_tenant_user (tenant_id, user_id)
);

CREATE TABLE IF NOT EXISTS mobile_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    device_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    UNIQUE KEY uk_token_hash (token_hash),
    KEY idx_device (device_id),
    KEY idx_tenant_user (tenant_id, user_id),
    CONSTRAINT fk_mobile_auth_tokens_device FOREIGN KEY (device_id) REFERENCES mobile_devices(id)
);
