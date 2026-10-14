
-- ============================================================================
-- OfficeFlow 1.0.0 — the complete database
-- ============================================================================
--
-- This file does two things. It creates the sixteen tables, and it loads five
-- example records into each content table so the workspace is not empty on the
-- first day.
--
-- HOW TO USE IT
--   Import this file into your empty database. That is all. The sixteen tables
--   are created and every content table is filled with five example records.
--   Then open your site and create your administrator account.
--
-- The examples are held by an inactive placeholder account called "Sample data",
-- so they can exist before you do. It cannot be signed into, and it does not stop
-- the setup screen from appearing. Once your own account exists you can edit,
-- reassign or delete any example from inside the app.
--
-- If you prefer the examples to belong to you from the start, create your
-- administrator account first and import this file afterwards. Examples are
-- attached to the lowest active account.
--
-- The file is safe to run as many times as you like. It never drops a table and
-- it never touches data you have entered: examples are only added when a table
-- is still empty.
--
-- Want an empty workspace instead? Import officeflow-schema.sql.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- OfficeFlow 1.0.0 — the complete database
-- ============================================================================
--
-- This file does two things. It creates the sixteen tables, and it loads five
-- example records into each content table so the workspace is not empty on the
-- first day.
--
-- HOW TO USE IT
--   Import this file into your empty database. That is all. The sixteen tables
--   are created and every content table is filled with five example records.
--   Then open your site and create your administrator account.
--
-- The examples are held by an inactive placeholder account called "Sample data",
-- so they can exist before you do. It cannot be signed into, and it does not stop
-- the setup screen from appearing. Once your own account exists you can edit,
-- reassign or delete any example from inside the app.
--
-- If you prefer the examples to belong to you from the start, create your
-- administrator account first and import this file afterwards. Examples are
-- attached to the lowest active account.
--
-- The file is safe to run as many times as you like. It never drops a table and
-- it never touches data you have entered: examples are only added when a table
-- is still empty.
--
-- Want an empty workspace instead? Use the Create the tables only button in
-- the setup screen, or delete the examples from inside the app afterwards.
-- ============================================================================


-- OfficeFlow — complete database schema (fresh installs)
-- Creates all 15 tables in one import.
--
-- Use this file when setting up a NEW installation.
-- If an installation already exists, do not run this; apply only the newer
-- files in database/migrations/ instead, so existing data is preserved.

-- OfficeFlow initial schema
-- One installation represents one office. This schema intentionally has no tenant/workspace_id.
-- Safe to re-run for an initial install: creates missing tables and never drops existing data.



CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active_role` (`is_active`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `office_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_office_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_code` VARCHAR(24) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `details` TEXT NULL,
  `category` VARCHAR(80) NOT NULL,
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `status` ENUM('new', 'in_progress', 'waiting', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
  `requester_id` BIGINT UNSIGNED NOT NULL,
  `assignee_id` BIGINT UNSIGNED NULL,
  `due_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_requests_code` (`request_code`),
  KEY `idx_requests_status_created` (`status`, `created_at`),
  KEY `idx_requests_assignee_status` (`assignee_id`, `status`),
  KEY `idx_requests_requester_created` (`requester_id`, `created_at`),
  CONSTRAINT `fk_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_requests_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `author_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_comments_request_created` (`request_id`, `created_at`),
  CONSTRAINT `fk_request_comments_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_comments_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `details` TEXT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'General',
  `status` ENUM('open', 'in_progress', 'completed', 'skipped') NOT NULL DEFAULT 'open',
  `recurrence` ENUM('none', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'none',
  `recurrence_parent_id` BIGINT UNSIGNED NULL,
  `due_at` DATETIME NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `completed_by` BIGINT UNSIGNED NULL,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_status_due` (`status`, `due_at`),
  KEY `idx_tasks_assignee_status` (`assigned_to`, `status`),
  KEY `idx_tasks_recurrence_parent` (`recurrence_parent_id`),
  CONSTRAINT `fk_tasks_recurrence_parent` FOREIGN KEY (`recurrence_parent_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tasks_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_tag` VARCHAR(60) NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `location` VARCHAR(120) NULL,
  `status` ENUM('active', 'maintenance', 'retired') NOT NULL DEFAULT 'active',
  `purchased_on` DATE NULL,
  `warranty_until` DATE NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assets_asset_tag` (`asset_tag`),
  KEY `idx_assets_status_location` (`status`, `location`),
  CONSTRAINT `fk_assets_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assets_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(80) NULL,
  `name` VARCHAR(180) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `location` VARCHAR(120) NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_items_sku` (`sku`),
  KEY `idx_inventory_items_name` (`name`),
  CONSTRAINT `fk_inventory_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `transaction_type` ENUM('received', 'issued', 'adjustment') NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  `note` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_transactions_item_created` (`item_id`, `created_at`),
  CONSTRAINT `fk_inventory_transactions_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inventory_transactions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `body` TEXT NOT NULL,
  `published_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_published` (`published_at`, `expires_at`),
  CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `notification_type` VARCHAR(60) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `body` VARCHAR(500) NULL,
  `target_url` VARCHAR(500) NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read_created` (`user_id`, `read_at`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `summary` VARCHAR(500) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_created` (`created_at`),
  KEY `idx_activity_logs_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_activity_logs_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vendors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `service_type` VARCHAR(80) NOT NULL DEFAULT 'General',
  `contact_name` VARCHAR(120) NULL,
  `phone` VARCHAR(40) NULL,
  `email` VARCHAR(190) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vendors_status_name` (`status`, `name`),
  CONSTRAINT `fk_vendors_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maintenance_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `asset_id` BIGINT UNSIGNED NULL,
  `vendor_id` BIGINT UNSIGNED NULL,
  `kind` ENUM('repair', 'inspection', 'service') NOT NULL DEFAULT 'repair',
  `status` ENUM('open', 'scheduled', 'in_progress', 'done', 'cancelled') NOT NULL DEFAULT 'open',
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `scheduled_for` DATE NULL,
  `completed_at` DATETIME NULL,
  `cost` DECIMAL(12,2) NULL,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maintenance_status_scheduled` (`status`, `scheduled_for`),
  CONSTRAINT `fk_maintenance_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maintenance_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maintenance_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(140) NOT NULL,
  `kind` ENUM('meeting_room', 'facility', 'shared_area') NOT NULL DEFAULT 'meeting_room',
  `location` VARCHAR(120) NULL,
  `capacity` SMALLINT UNSIGNED NULL,
  `status` ENUM('available', 'in_use', 'maintenance', 'closed') NOT NULL DEFAULT 'available',
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rooms_status_kind` (`status`, `kind`),
  CONSTRAINT `fk_rooms_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'SOP',
  `version` VARCHAR(40) NULL,
  `url` VARCHAR(500) NULL,
  `body` TEXT NULL,
  `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documents_status_category` (`status`, `category`),
  CONSTRAINT `fk_documents_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email_hash` CHAR(64) NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email_time` (`email_hash`, `attempted_at`),
  KEY `idx_login_attempts_ip_time` (`ip_hash`, `attempted_at`),
  KEY `idx_login_attempts_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Example content
-- ============================================================================

-- A placeholder account holds the examples so they can be loaded in a single
-- import, before you have created your own account. It is inactive and cannot
-- be signed into. Once your own administrator exists, the examples keep working
-- and you can reassign or delete them from inside the app.
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `is_active`)
SELECT 'Sample data', 'sample@example.invalid', '*', 'staff', 0
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'sample@example.invalid');

-- Examples are attached to the lowest active account, so if you create your own
-- account first and then import this file, they belong to you instead.
SET @uid := (SELECT COALESCE(MIN(CASE WHEN is_active = 1 THEN id END), MIN(id)) FROM `users`);

-- assets: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `assets`) = 0, 1, 0));
INSERT INTO `assets` (`asset_tag`, `name`, `category`, `location`, `status`, `purchased_on`, `warranty_until`, `notes`, `created_by`)
SELECT * FROM (
  SELECT 'AST-1001' AS `asset_tag`, 'Meeting room projector' AS `name`, 'Equipment' AS `category`, 'Meeting room 2' AS `location`, 'active' AS `status`, '2024-03-14' AS `purchased_on`, '2027-03-14' AS `warranty_until`, 'Ceiling mounted. Lamp hours are logged on the maintenance record.' AS `notes`, @uid AS `created_by`
  UNION ALL SELECT 'AST-1002', 'Standing desk', 'Furniture', 'Design desk', 'active', '2025-01-08', '2027-01-08', 'Height motor sticks above 110cm. Maintenance ticket open.', @uid
  UNION ALL SELECT 'AST-1003', 'Espresso machine', 'Appliance', 'Pantry', 'maintenance', '2023-06-02', '2026-06-02', 'Group head leaking. Service booked with the vendor.', @uid
  UNION ALL SELECT 'AST-1004', 'Laser printer', 'Equipment', 'Copy room', 'active', '2024-11-19', '2026-11-19', 'Second tray configured for A3.', @uid
  UNION ALL SELECT 'AST-1005', 'Whiteboard, 180cm', 'Furniture', 'Meeting room 1', 'active', '2022-09-30', NULL, 'Wall mounted. Replacement pens are in the supply cupboard.', @uid
) AS samples WHERE @seed = 1;

-- vendors: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `vendors`) = 0, 1, 0));
INSERT INTO `vendors` (`name`, `service_type`, `contact_name`, `phone`, `email`, `status`, `notes`, `created_by`)
SELECT * FROM (
  SELECT 'Sinar Teknik AC' AS `name`, 'Air conditioning' AS `service_type`, 'Budi Santoso' AS `contact_name`, '+62 811 2233 4455' AS `phone`, 'service@sinarteknik.example' AS `email`, 'active' AS `status`, 'Two-hour response on weekdays. Invoice monthly.' AS `notes`, @uid AS `created_by`
  UNION ALL SELECT 'Kopi Nusantara', 'Pantry supply', 'Dewi Lestari', '+62 812 9988 7766', 'orders@kopinuss.example', 'active', 'Delivers Tuesday and Friday mornings.', @uid
  UNION ALL SELECT 'Bersih Jaya', 'Cleaning services', 'Agus Wibowo', '+62 813 5566 7788', 'ops@bersihjaya.example', 'active', 'Twice weekly, evenings after office hours.', @uid
  UNION ALL SELECT 'Cetak Cepat', 'Printing', 'Rina Marlina', '+62 815 4433 2211', 'hello@cetakcepat.example', 'active', 'Same-day for orders placed before 11am.', @uid
  UNION ALL SELECT 'Listrik Mandiri', 'Electrical', 'Hendra Gunawan', '+62 817 6677 8899', 'service@listrikmandiri.example', 'inactive', 'Used for the 2025 office fit-out.', @uid
) AS samples WHERE @seed = 1;

-- rooms: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `rooms`) = 0, 1, 0));
INSERT INTO `rooms` (`name`, `kind`, `location`, `capacity`, `status`, `notes`, `created_by`)
SELECT * FROM (
  SELECT 'Meeting room 1' AS `name`, 'meeting_room' AS `kind`, 'Third floor' AS `location`, 10 AS `capacity`, 'available' AS `status`, 'Whiteboard and video conference kit.' AS `notes`, @uid AS `created_by`
  UNION ALL SELECT 'Meeting room 2', 'meeting_room', 'Third floor', 6, 'maintenance', 'Air conditioning under repair.', @uid
  UNION ALL SELECT 'Pantry', 'shared_area', 'Second floor', NULL, 'available', 'Coffee machine is out of service.', @uid
  UNION ALL SELECT 'Training room', 'facility', 'Ground floor', 24, 'available', 'Booking required. Chairs stored in the back.', @uid
  UNION ALL SELECT 'Focus booth', 'shared_area', 'Third floor', 2, 'in_use', 'Single occupant. Booked in 30 minute blocks.', @uid
) AS samples WHERE @seed = 1;

-- inventory_items: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `inventory_items`) = 0, 1, 0));
INSERT INTO `inventory_items` (`sku`, `name`, `category`, `unit`, `quantity`, `reorder_level`, `location`, `created_by`)
SELECT * FROM (
  SELECT 'SUP-001' AS `sku`, 'A4 copy paper' AS `name`, 'Office supplies' AS `category`, 'box' AS `unit`, 3.0 AS `quantity`, 5.0 AS `reorder_level`, 'Supply cupboard' AS `location`, @uid AS `created_by`
  UNION ALL SELECT 'SUP-014', 'Whiteboard marker, black', 'Office supplies', 'pack', 2.0, 4.0, 'Supply cupboard', @uid
  UNION ALL SELECT 'PAN-003', 'Ground coffee', 'Pantry', 'kg', 1.5, 3.0, 'Pantry', @uid
  UNION ALL SELECT 'CLN-002', 'Hand soap', 'Cleaning', 'bottle', 6.0, 4.0, 'Store room', @uid
  UNION ALL SELECT 'STA-007', 'Ballpoint pen, blue', 'Office supplies', 'box', 8.0, 3.0, 'Supply cupboard', @uid
) AS samples WHERE @seed = 1;

-- requests: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `requests`) = 0, 1, 0));
INSERT INTO `requests` (`request_code`, `title`, `details`, `category`, `priority`, `status`, `requester_id`, `assignee_id`, `due_at`, `created_at`)
SELECT * FROM (
  SELECT 'OF-241010-A1B2C3' AS `request_code`, 'Air conditioning in meeting room 2' AS `title`, 'The unit blows warm air after about twenty minutes. The finance team has a client call on Thursday.' AS `details`, 'Facilities' AS `category`, 'high' AS `priority`, 'in_progress' AS `status`, @uid AS `requester_id`, @uid AS `assignee_id`, '2026-10-15 09:00:00' AS `due_at`, '2026-10-10 08:24:00' AS `created_at`
  UNION ALL SELECT 'OF-241011-D4E5F6', 'Printer paper, A4, five boxes', 'We are down to the last box in the supply cupboard. The usual supplier delivers in two days.', 'Office supplies', 'normal', 'new', @uid, @uid, '2026-10-16 17:00:00', '2026-10-11 10:05:00'
  UNION ALL SELECT 'OF-241012-G7H8I9', 'Replace desk lamp at workstation 08', 'The lamp flickers and then cuts out. Nisa has moved to the spare desk until it is replaced.', 'Equipment', 'normal', 'waiting', @uid, NULL, '2026-10-14 17:00:00', '2026-10-12 14:31:00'
  UNION ALL SELECT 'OF-241008-J1K2L3', 'Guest access badges for Thursday meeting', 'Six visitors expected. Reception needs the names by Wednesday afternoon.', 'Other', 'high', 'completed', @uid, @uid, '2026-10-09 12:00:00', '2026-10-08 09:12:00'
  UNION ALL SELECT 'OF-241007-M4N5O6', 'Repaint the reception wall', 'Scuff marks near the seating area. Low priority, happy to schedule it over a weekend.', 'Facilities', 'low', 'cancelled', @uid, NULL, NULL, '2026-10-07 11:48:00'
) AS samples WHERE @seed = 1;

-- request_comments: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `request_comments`) = 0, 1, 0));
INSERT INTO `request_comments` (`request_id`, `author_id`, `body`, `created_at`)
SELECT * FROM (
  SELECT (SELECT id FROM requests WHERE request_code = 'OF-241010-A1B2C3') AS `request_id`, @uid AS `author_id`, 'Technician booked for Thursday morning, between 9 and 11.' AS `body`, '2026-10-10 09:40:00' AS `created_at`
  UNION ALL SELECT (SELECT id FROM requests WHERE request_code = 'OF-241010-A1B2C3'), @uid, 'Noted, I will let the finance team know so they can plan the call.', '2026-10-10 09:52:00'
  UNION ALL SELECT (SELECT id FROM requests WHERE request_code = 'OF-241010-A1B2C3'), @uid, 'Parts ordered. The compressor is the likely cause.', '2026-10-12 16:10:00'
  UNION ALL SELECT (SELECT id FROM requests WHERE request_code = 'OF-241012-G7H8I9'), @uid, 'Spare lamp found in the store room. Swapping it this afternoon.', '2026-10-13 09:05:00'
  UNION ALL SELECT (SELECT id FROM requests WHERE request_code = 'OF-241011-D4E5F6'), @uid, 'Order placed with the usual supplier, delivery on Thursday.', '2026-10-11 11:30:00'
) AS samples WHERE @seed = 1;

-- tasks: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `tasks`) = 0, 1, 0));
INSERT INTO `tasks` (`title`, `details`, `category`, `status`, `recurrence`, `due_at`, `assigned_to`, `created_by`)
SELECT * FROM (
  SELECT 'Weekly safety walk-through' AS `title`, 'Check exits, extinguishers and the first aid kit on every floor.' AS `details`, 'Facilities' AS `category`, 'open' AS `status`, 'weekly' AS `recurrence`, '2026-10-16 15:00:00' AS `due_at`, @uid AS `assigned_to`, @uid AS `created_by`
  UNION ALL SELECT 'Prepare meeting room 2', 'Fresh water, clean whiteboard, check the projector cable.', 'Facilities', 'completed', 'weekly', '2026-10-13 09:00:00', @uid, @uid
  UNION ALL SELECT 'Check pantry supplies', 'Coffee, tea, milk and sugar. Log anything running low in Inventory.', 'Inventory', 'completed', 'weekly', '2026-10-13 10:00:00', @uid, @uid
  UNION ALL SELECT 'Receive printer paper delivery', 'Five boxes of A4. Confirm the count against the delivery note.', 'Supplies', 'open', 'none', '2026-10-16 13:00:00', @uid, @uid
  UNION ALL SELECT 'Quarterly fire extinguisher inspection', 'Outside contractor attends. Meet them at reception.', 'Facilities', 'open', 'monthly', '2026-10-30 11:00:00', @uid, @uid
) AS samples WHERE @seed = 1;

-- maintenance_records: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `maintenance_records`) = 0, 1, 0));
INSERT INTO `maintenance_records` (`title`, `asset_id`, `vendor_id`, `kind`, `status`, `priority`, `scheduled_for`, `cost`, `notes`, `created_by`)
SELECT * FROM (
  SELECT 'Air conditioning service, meeting room 2' AS `title`, (SELECT id FROM assets WHERE asset_tag = 'AST-1001') AS `asset_id`, (SELECT id FROM vendors WHERE name = 'Sinar Teknik AC') AS `vendor_id`, 'repair' AS `kind`, 'in_progress' AS `status`, 'high' AS `priority`, '2026-10-15' AS `scheduled_for`, 1450000.0 AS `cost`, 'Compressor replaced. Parts arrived on 12 October.' AS `notes`, @uid AS `created_by`
  UNION ALL SELECT 'Espresso machine group head service', (SELECT id FROM assets WHERE asset_tag = 'AST-1003'), (SELECT id FROM vendors WHERE name = 'Kopi Nusantara'), 'service', 'scheduled', 'normal', '2026-10-17', 650000.0, 'Vendor collects the machine on Friday.', @uid
  UNION ALL SELECT 'Annual fire extinguisher inspection', NULL, (SELECT id FROM vendors WHERE name = 'Bersih Jaya'), 'inspection', 'open', 'normal', '2026-10-30', NULL, 'Contractor attends with the compliance paperwork.', @uid
  UNION ALL SELECT 'Projector lamp replacement', (SELECT id FROM assets WHERE asset_tag = 'AST-1001'), NULL, 'repair', 'done', 'normal', '2026-09-20', 320000.0, 'Completed during the September maintenance window.', @uid
  UNION ALL SELECT 'Printer roller cleaning', (SELECT id FROM assets WHERE asset_tag = 'AST-1004'), NULL, 'service', 'open', 'low', NULL, NULL, 'Paper jams reported on the second tray.', @uid
) AS samples WHERE @seed = 1;

-- inventory_transactions: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `inventory_transactions`) = 0, 1, 0));
INSERT INTO `inventory_transactions` (`item_id`, `transaction_type`, `quantity`, `note`, `created_by`, `created_at`)
SELECT * FROM (
  SELECT (SELECT id FROM inventory_items WHERE sku = 'SUP-001') AS `item_id`, 'received' AS `transaction_type`, 10.0 AS `quantity`, 'Opening quantity' AS `note`, @uid AS `created_by`, '2026-09-28 09:00:00' AS `created_at`
  UNION ALL SELECT (SELECT id FROM inventory_items WHERE sku = 'SUP-001'), 'issued', 7.0, 'Monthly distribution to each floor', @uid, '2026-10-06 11:20:00'
  UNION ALL SELECT (SELECT id FROM inventory_items WHERE sku = 'SUP-014'), 'received', 6.0, 'Opening quantity', @uid, '2026-09-28 09:00:00'
  UNION ALL SELECT (SELECT id FROM inventory_items WHERE sku = 'PAN-003'), 'issued', 2.5, 'Weekly pantry top-up', @uid, '2026-10-12 08:30:00'
  UNION ALL SELECT (SELECT id FROM inventory_items WHERE sku = 'CLN-002'), 'received', 6.0, 'Opening quantity', @uid, '2026-09-28 09:00:00'
) AS samples WHERE @seed = 1;

-- documents: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `documents`) = 0, 1, 0));
INSERT INTO `documents` (`title`, `category`, `version`, `url`, `body`, `status`, `created_by`)
SELECT * FROM (
  SELECT 'Visitor and guest procedure' AS `title`, 'SOP' AS `category`, '2.1' AS `version`, NULL AS `url`, 'All visitors report to reception. Reception issues a badge and contacts the host. Visitors are escorted at all times and badges are returned at the end of the visit.' AS `body`, 'published' AS `status`, @uid AS `created_by`
  UNION ALL SELECT 'Meeting room booking rules', 'SOP', '1.4', NULL, 'Rooms can be booked up to two weeks ahead. Cancel at least two hours before if plans change. Leave the room as you found it and report any damage in Maintenance.', 'published', @uid
  UNION ALL SELECT 'Supplier onboarding checklist', 'Reference', '1.0', 'https://example.com/supplier-onboarding', 'Collect the company registration, the tax number, a bank letter and one trade reference before the first order.', 'draft', @uid
  UNION ALL SELECT 'New starter first week', 'SOP', '1.2', NULL, 'Day one: desk, laptop, accounts. Day two: walk-through of the building. Day three: meet each team. Day five: review with the line manager.', 'published', @uid
  UNION ALL SELECT 'Emergency evacuation plan', 'Reference', '3.0', NULL, 'Assembly point is the north car park. Floor wardens sweep each level before leaving. Nobody returns inside until the warden confirms it is safe.', 'published', @uid
) AS samples WHERE @seed = 1;

-- announcements: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `announcements`) = 0, 1, 0));
INSERT INTO `announcements` (`title`, `body`, `published_at`, `expires_at`, `created_by`)
SELECT * FROM (
  SELECT 'Air conditioning in meeting room 2' AS `title`, 'The unit is being repaired. Meeting room 2 is unavailable until Friday. Please use the training room for any meetings this week.' AS `body`, '2026-10-13 08:00:00' AS `published_at`, '2026-10-18 00:00:00' AS `expires_at`, @uid AS `created_by`
  UNION ALL SELECT 'Office closed for the public holiday', 'The office is closed on Monday. Remote access stays available and the support line is covered by the usual on-call rota.', '2026-10-12 16:30:00', '2026-10-20 00:00:00', @uid
  UNION ALL SELECT 'New supplier for pantry supplies', 'Kopi Nusantara now delivers on Tuesday and Friday mornings. Orders go through the office administrator.', '2026-10-06 09:15:00', '2026-11-06 00:00:00', @uid
  UNION ALL SELECT 'Q4 fire drill', 'The drill takes place on 30 October at 10am and should take about fifteen minutes. Floor wardens will brief their teams the day before.', '2026-10-20 11:00:00', '2026-10-31 00:00:00', @uid
  UNION ALL SELECT 'Printer in the copy room', 'The second tray is out of service while the rollers are cleaned. Use the main tray in the meantime.', '2026-10-13 14:00:00', '2026-10-16 00:00:00', @uid
) AS samples WHERE @seed = 1;

-- activity_logs: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `activity_logs`) = 0, 1, 0));
INSERT INTO `activity_logs` (`actor_id`, `action`, `entity_type`, `entity_id`, `summary`, `created_at`)
SELECT * FROM (
  SELECT @uid AS `actor_id`, 'created' AS `action`, 'requests' AS `entity_type`, 1 AS `entity_id`, 'Created request #1' AS `summary`, '2026-10-10 08:24:00' AS `created_at`
  UNION ALL SELECT @uid, 'created', 'assets', 5, 'Created asset #5', '2026-10-11 13:20:00'
  UNION ALL SELECT @uid, 'updated', 'settings', NULL, 'Updated office settings', '2026-10-12 15:12:00'
  UNION ALL SELECT @uid, 'created', 'documents', 2, 'Created document #2', '2026-10-12 15:40:00'
  UNION ALL SELECT @uid, 'created', 'tasks', 4, 'Created task #4', '2026-10-13 09:02:00'
) AS samples WHERE @seed = 1;

-- notifications: 5 examples
SET @seed := IF(@uid IS NULL, 0, IF((SELECT COUNT(*) FROM `notifications`) = 0, 1, 0));
INSERT INTO `notifications` (`user_id`, `actor_id`, `notification_type`, `title`, `body`, `target_url`, `created_at`)
SELECT * FROM (
  SELECT @uid AS `user_id`, @uid AS `actor_id`, 'request_created' AS `notification_type`, 'Example: a new request arrived' AS `title`, 'Second monitor for the design desk' AS `body`, '/requests.php' AS `target_url`, '2026-10-13 08:55:00' AS `created_at`
  UNION ALL SELECT @uid, @uid, 'request_comment', 'Example: a comment on a request', 'Technician booked for Thursday morning.', '/requests.php', '2026-10-10 09:40:00'
  UNION ALL SELECT @uid, @uid, 'task_assigned', 'Example: a task was assigned', 'Weekly safety walk-through', '/tasks.php', '2026-10-13 09:02:00'
  UNION ALL SELECT @uid, @uid, 'stock_low', 'Example: stock is running low', 'A4 copy paper is at 3 boxes, reorder level is 5.', '/inventory.php', '2026-10-13 10:15:00'
  UNION ALL SELECT @uid, @uid, 'maintenance_due', 'Example: maintenance is scheduled', 'Air conditioning service, meeting room 2', '/module.php?m=maintenance', '2026-10-13 11:00:00'
) AS samples WHERE @seed = 1;

-- ============================================================================
-- Done. Every example is attached to the first account in the database.
-- Sign in and delete anything that does not fit your office.
-- ============================================================================
