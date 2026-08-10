-- Run this SQL to create the user_branches table
CREATE TABLE IF NOT EXISTS `user_branches` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT          NOT NULL,
    `user_id`    INT          NOT NULL,
    `branch_id`  INT          NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_branch` (`tenant_id`, `user_id`, `branch_id`),
    KEY `idx_user_id`   (`user_id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
