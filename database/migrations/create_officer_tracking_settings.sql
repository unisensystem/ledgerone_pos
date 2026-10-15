-- One row per (tenant, user) — current tracking state, not a history log.
-- admin_enabled: office-wide switch, set from the web Admin Panel. Defaults
--   to 1 so today's existing "everyone is tracked" behavior doesn't change
--   for anyone until an admin explicitly excludes someone.
-- officer_enabled: the officer's own switch from the mobile app (e.g. "this
--   is personal time"). Also defaults to 1.
-- Effective tracking = admin_enabled AND officer_enabled — either side can
-- turn it off, matching the "admin's decision wins" rule (if admin turns it
-- off, the officer's own switch becomes moot — see ping.php).
-- last_status/last_status_at: the most recent device-reported problem (GPS
-- off, location permission denied) and when — cleared back to NULL the next
-- time a real location ping succeeds. Lets the web show "this isn't a real
-- outage, their GPS is off" instead of just a stale dot.
CREATE TABLE IF NOT EXISTS officer_tracking_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    admin_enabled TINYINT(1) NOT NULL DEFAULT 1,
    officer_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_status VARCHAR(30) NULL,
    last_status_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
