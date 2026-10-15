-- Live GPS tracking for mobile app users. One row per location ping sent
-- while the officer has the mobile app open in the foreground (the app never
-- tracks in the background). Kept simple: raw pings only, no server-side
-- aggregation — "today's route" is just all of a user's pings for a day,
-- read in order and drawn as a line by the web map.
CREATE TABLE IF NOT EXISTS officer_location_pings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_meters DECIMAL(8,2) NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_user_recorded (tenant_id, user_id, recorded_at),
    INDEX idx_tenant_recorded (tenant_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
