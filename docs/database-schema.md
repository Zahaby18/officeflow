# Initial database schema

The schema is for one office per installation. There is intentionally no `workspace_id` or tenant table. A buyer’s installation and database are separate from every other buyer’s.

| Table | Purpose |
|---|---|
| `users` | Admin/Staff identities and password hashes |
| `office_settings` | Office-wide configurable values |
| `requests` | Staff requests and their status, priority, requester, and assignee |
| `request_comments` | Discussion/history attached to a request |
| `tasks` | One-off and recurring task records |
| `assets` | Office equipment and its location/status |
| `inventory_items` | Current quantities and reorder thresholds |
| `inventory_transactions` | Stock movement history |
| `announcements` | Internal office announcements |
| `notifications` | Per-user in-app notifications and read state |
| `activity_logs` | High-level record of important changes |

The migration uses InnoDB, utf8mb4, foreign keys, and indexes for common status/owner/date lookups. It does not insert demo users or sample records. Seed data must be opt-in and must never include default production credentials.

`CREATE TABLE IF NOT EXISTS` makes the initial migration safe to rerun during setup, but it does not upgrade an already-created table. Schema changes after release belong in a new numbered migration, with a verified backup and upgrade test.
