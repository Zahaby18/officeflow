# OfficeFlow

OfficeFlow is planned as a self-hosted office operations app. Each customer installs a separate copy on their own PHP/MySQL hosting, so their team can sign in to the same URL and see the same data from different devices.

## Current status

This repository currently contains the initial database migration, schema checks, and architecture notes. It is **not an installable or production-ready app yet**. Authentication, installer, UI, backups, and the app modules still need to be implemented and tested before deployment or sale.

## First release scope

The first usable release is intended to focus on Home, Requests, Tasks & Checklists, Assets, Inventory, Team, in-app Notifications, and Settings. Admin and Staff accounts share one office database. Email/realtime notifications, multi-office tenancy, and third-party integrations are out of scope for the first release.

## Repository layout

```text
app/                         PHP application code (to be added)
config/database.example.php  safe configuration template
config/database.php          local credentials; ignored by Git
database/migrations/         forward-only database migrations
public/                      web document root (to be added)
docs/                        architecture and installation notes
tests/                       automated schema checks
```

The live document root should point to `public/`; keep `config/`, `database/`, and application code outside the public web root wherever the hosting allows it.

## Database

The first migration is `database/migrations/001_initial_schema.sql`. It creates the initial tables for users, settings, requests/comments, tasks, assets, inventory/movements, announcements, notifications, and activity history. It is repeatable for an initial install and does not drop or truncate existing data.

For future releases, add a new numbered migration instead of editing a migration already shipped to customers. Back up the database before applying updates.

## Requirements planned for the app

- PHP 8.2 or newer with PDO MySQL
- MySQL 8.0 or MariaDB 10.6 or newer
- HTTPS enabled on the customer’s domain

These are initial targets, not a compatibility guarantee until the app is tested against those hosting environments.

## Local schema tests

```bash
python3 -m unittest discover -s tests -v
```

The current tests validate the schema definition. They do not yet test a running PHP app or connect to a database.

## Security and data ownership

- Customer data stays in that customer’s hosting/database; this project does not provide a shared cloud service.
- Never commit database passwords, customer data, or production backups.
- Use a separate database account with access only to the OfficeFlow database.
- Deactivate staff accounts rather than deleting users referenced by office records.
- A production release needs tested authentication, role checks, CSRF protection, input validation, output escaping, backups, and an update path.
