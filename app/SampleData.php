<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

/**
 * The example content, embedded so the setup screen can load it without any
 * .sql file being reachable over the web.
 *
 * Generated from the example section of officeflow-schema-with-samples.sql.
 * Regenerate after changing that file.
 */
final class SampleData
{
    /** @return list<string> */
    public static function statements(): array
    {
        $lines = [];
        foreach (preg_split('/\R/', self::raw()) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '--')) {
                continue;
            }
            $lines[] = $line;
        }

        $statements = [];
        foreach (explode(';', implode("\n", $lines)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private static function raw(): string
    {
        return <<<'SQL'
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
SQL;
    }
}
