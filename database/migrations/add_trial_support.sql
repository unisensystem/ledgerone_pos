-- Add trial_used flag to tenants table
ALTER TABLE `tenants`
  ADD COLUMN `trial_used` TINYINT(1) NOT NULL DEFAULT 0 AFTER `trial_ends_at`;

-- Insert the trial plan (used as plan_id FK for trial subscriptions)
INSERT IGNORE INTO `plans`
  (`name`, `description`, `plan_type`, `monthly_price`, `trial_days`, `is_active`, `is_public`, `max_users`, `max_companies`, `max_storage_mb`, `max_monthly_transactions`)
VALUES
  ('Trial', '14-day free trial with full access to all features', 'subscription', 0.00, 14, 1, 0, 100, 5, 5000, 10000);
