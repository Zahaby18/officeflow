# OfficeFlow — user and install guide

Two audiences: the **office team** who use the app every day, and the **person installing it**. Read the part you need.

---

## Part 1 — Install it (no SSH, no vhost changes)

OfficeFlow is drop-in. Upload the folder contents to the web root and open the site. There is no separate `public` folder to point at, so it works on cPanel (`public_html`), CloudPanel, Plesk, or any panel with a PHP host.

### What you need

- PHP 8.2 or newer with the `pdo_mysql` and `mbstring` extensions
- One empty MySQL or MariaDB database
- The folder uploaded over SFTP

### Steps

1. **Upload the files.** Put everything in the web root: `index.php`, `login.php`, `install.php`, `requests.php`, `tasks.php`, `inventory.php`, `module.php`, `team.php`, `reports.php`, `activity.php`, `settings.php`, `notifications.php`, `logout.php`, `preview.html`, plus the `app/`, `assets/`, `config/`, and `database/` folders.

2. **Create the database.** In your panel, add a MySQL database and note the **name**, **user**, and **password**.

3. **Import the schema.** Open phpMyAdmin for that database, choose **Import**, upload `database/migrations/001_initial_schema.sql`, then `002_office_resources.sql`, then run each. Together they create 15 tables.

   Tiny databases like this are safe in phpMyAdmin. The general warning against importing with phpMyAdmin is about large or already-populated databases, which does not apply here.

4. **Create the config.** Copy `config/database.example.php` to `config/database.php` and fill in the values from step 2. Upload it. Do not put it in Git and do not share it.

5. **Open your domain.** Because no account exists yet, the site sends you to the setup page. Enter the office name, your name, an email, and a password of at least 12 characters. That creates the first administrator and signs you in.

6. **Turn on HTTPS.** Use your panel's free certificate option. Nobody should type office data over plain HTTP.

### Notes

- The `.htaccess` files block direct access to `app/`, `config/`, `database/`, and `tests/` on Apache hosts. Nginx ignores `.htaccess`; the PHP guard inside those files still refuses direct requests, so nothing sensitive is exposed.
- Delete `README.md`, `docs/`, and `tests/` from the server if you like. They are not needed at runtime.
- Keep `config/database.php` out of any backup you share.

---

## Part 2 — Use it

### Signing in

Everyone signs in at the same URL with the email and password their administrator gave them. Any device works: open the same address and the same office data appears. That is what makes it different from a spreadsheet.

### Roles

- **Staff** — create requests, add tasks, add and update office records.
- **Admin** — everything staff can do, plus edit and delete records, manage team accounts, and change office settings.

### Workspace

**Home** — the office overview. Open requests, tasks due today, assets needing a look, and low stock, each pulled live from the database.

**Requests** — the day-to-day ask-and-track flow. Anyone can create one; an admin moves it through New, In progress, Waiting, Completed, or Cancelled. Every request gets a reference code like `OF-241012-A1B2C3`.

**Tasks & Checklists** — recurring routines and one-off jobs. Set a due date, assign it to a teammate, and say whether it repeats daily, weekly, or monthly. The status dropdown updates it; the person assigned gets a notification.

**Announcements** — short internal notices, optional publish and expiry dates.

### Office resources

**Assets** — what the office owns: an asset tag, a name, a category, a location, and a status of active, maintenance, or retired.

**Inventory** — supplies and their quantities. Two things to understand:
- The **starting quantity** is only asked when you create an item.
- After that, quantity changes only through **stock movements** (Received, Issued, Adjustment). Each movement is written to the history, so the number and its trail always agree. Nothing silently edits a quantity.

**Maintenance** — repairs, inspections, and services. Link a record to an asset and a vendor so the history stays connected.

**Rooms & Facilities** — meeting rooms and shared areas, with capacity and current status.

**Vendors** — supplier contact details and the kind of service they provide.

**Documents & SOPs** — procedures and references, stored as text or as a link, with a version label and a draft/published/archived state.

### Management

**Team** — admins add accounts here. Pick Staff or Admin, set a temporary password, and share it privately. You can change a role, disable access instead of deleting someone, or reset a password. OfficeFlow refuses to remove the last active administrator or let you lock yourself out.

**Reports** — headline counts, requests by status and by category, a weekly trend, and a resource summary across every module.

**Activity log** — who changed what, and when. Summaries never contain sensitive field values.

**Settings** — office name, location, timezone, and support email. The rest of the page is read-only information about this installation.

**Notifications** — the bell in the top bar shows a count of unread items. Open it to see assignments and new records, then mark them read.

### Good to know

- Records can be searched from each module's search box.
- **Delete** asks for confirmation and is admin-only. On inventory it is not offered at all; stock is corrected with an adjustment movement so the history stays honest.
- Requests, tasks, and stock movements are not reversible from the interface. Correct a mistake by recording the opposite action, not by editing history.

---

## Status of this build

Working and verified: install, sign in and out, roles, Requests, Tasks, Announcements, Assets, Inventory with its stock ledger, Maintenance, Rooms & Facilities, Vendors, Documents & SOPs, Team, Reports, Activity log, Settings, and in-app notifications.

Not built yet: email notifications, backup and restore tooling, and automatic updates. Those are needed before this is sold as a finished product.
