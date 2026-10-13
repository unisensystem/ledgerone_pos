-- Biometric device registration: one row per physical fingerprint/face machine
CREATE TABLE IF NOT EXISTS `biometric_devices` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT(11) NOT NULL,
  `device_name`     VARCHAR(100) NOT NULL,
  `device_location` VARCHAR(100) DEFAULT NULL,
  `api_token`       VARCHAR(64) NOT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync`       DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_token` (`api_token`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maps a machine's internal employee number (e.g. "1", "2") to the ERP employee_id (e.g. "ALUM-0001")
-- Scoped per tenant; machine IDs are consistent across all devices for the same employee.
CREATE TABLE IF NOT EXISTS `biometric_employee_mapping` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT(11) NOT NULL,
  `machine_employee_id` VARCHAR(20) NOT NULL,
  `employee_id`         VARCHAR(50) NOT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_machine_id` (`tenant_id`, `machine_employee_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores every raw punch received from a device (for debugging / re-processing)
CREATE TABLE IF NOT EXISTS `biometric_push_log` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `device_id`           INT(11) NOT NULL,
  `tenant_id`           INT(11) NOT NULL,
  `machine_employee_id` VARCHAR(20) NOT NULL,
  `punch_time`          DATETIME NOT NULL,
  `punch_type_raw`      VARCHAR(10) DEFAULT NULL,
  `status`              ENUM('processed','unmapped','error') NOT NULL DEFAULT 'processed',
  `note`                VARCHAR(255) DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_tenant_date` (`tenant_id`, `punch_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
