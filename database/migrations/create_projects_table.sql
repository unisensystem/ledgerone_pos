-- Projects Table
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `company_id` INT NOT NULL,
    `project_code` VARCHAR(50) NOT NULL,
    `project_name` VARCHAR(255) NOT NULL,
    `city_id` INT DEFAULT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `project_manager_id` INT DEFAULT NULL,
    `team_leader_id` INT DEFAULT NULL,
    `status` ENUM('active', 'completed', 'on_hold', 'cancelled') DEFAULT 'active',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_project_code_tenant` (`project_code`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project Team Members Table
CREATE TABLE IF NOT EXISTS `project_team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_project_employee` (`project_id`, `employee_id`),
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
