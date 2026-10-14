# OfficeFlow

OfficeFlow is planned as a self-hosted office operations app. Each customer installs a separate copy on their own PHP/MySQL hosting, so their team can sign in to the same URL and see the same data from different devices.

## Current status

This repository contains the initial database migration, schema checks, architecture notes, a **static UI preview** at `preview.html`, and a working **PHP/MySQL app foundation**: first-time admin setup, login/logout with hardened sessions, CSRF-protected forms, a Home dashboard, and a Requests module with create + admin status transitions. Notifications, Tasks, Assets, Inventory, Team, and Settings are not implemented yet, so the repo is **not a sale-ready product**. Backups, an upgrade path, and the buyer ZIP still need to be built and tested.

## First release scope

The first usable release is intended to focus on Home, Requests, Tasks & Checklists, Assets, Inventory, Team, in-app Notifications, and Settings. Admin and Staff accounts share one office database. Email/realtime notifications, multi-office tenancy, and third-party integrations are out of scope for the first release.

## Repository layout

```text
index.php                    authenticated Home dashboard
requests.php                 Requests module (create + admin status update)
install.php                  first-time admin setup
login.php                    sign-in
logout.php                   POST-only sign-out
preview.html                 static UI preview (no database)
assets/                      UI styles and preview interactions
app/                         PHP services, bootstrap, and view partials
config/database.example.php  safe configuration template
config/database.php          credentials created during setup; ignored by Git
officeflow-schema.sql        complete schema for a new installation
docs/                        architecture and deployment notes
tests/test_*.py              schema and static UI checks
tests/php/*_test.php         service-level integration checks (SQLite)
```

The app is **drop-in**: upload the project contents straight into the site's web root (`public_html` on cPanel, the site directory on CloudPanel). No document-root or vhost change is required, because there is no separate `public/` folder. `app/` and `config/` carry a PHP guard and ship with `.htaccess` deny rules so they stay unreachable from the browser.

## Database

For a new installation, import `officeflow-schema.sql`. It creates all 16 tables and loads five example records into every module, and it is safe to run twice.

`officeflow-schema.sql` is the single source of the database. Change it there, and regenerate the two embedded copies the setup screen uses (`app/Schema.php` for the tables, `app/SampleData.php` for the examples) in the same commit so they never drift. Back up the database before applying structural changes to a live installation.

## Requirements planned for the app

- PHP 8.2 or newer with PDO MySQL and mbstring
- MySQL 8.0 or MariaDB 10.6 or newer
- HTTPS enabled on the customer’s domain

These are initial targets, not a compatibility guarantee until the app is tested against those hosting environments.

## Running the app locally

1. Create an empty MySQL/MariaDB database and import `officeflow-schema.sql`.
2. Copy `config/database.example.php` to `config/database.php` and enter the connection values.
3. Serve the project directory (for example `php -S 127.0.0.1:8000`).
4. Open the site: the first visit redirects to `install.php` until the first administrator exists.

## Tests

```bash
python3 -m unittest discover -s tests -v
for test in tests/php/*_test.php; do php "$test"; done
```

The Python suite validates the schema definition and the static preview. The PHP tests exercise the auth, CSRF, installer, and request services against SQLite. They do not cover a full browser session or MySQL-specific behaviour; run the app locally for that.

## Security and data ownership

- Customer data stays in that customer’s hosting/database; this project does not provide a shared cloud service.
- Never commit database passwords, customer data, or production backups.
- Use a separate database account with access only to the OfficeFlow database.
- Deactivate staff accounts rather than deleting users referenced by office records.
- Passwords are stored with `password_hash()`; forms are CSRF-protected; all output is escaped; sessions use HttpOnly, SameSite=Lax cookies and regenerate on login.
- Sign-in is rate limited: 5 failures for one email or 20 from one address inside 15 minutes locks further attempts for the rest of the window. Email and address are stored only as salted hashes.
- A production release still needs backups and a tested update path.
