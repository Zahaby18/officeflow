# OfficeFlow architecture (initial decision)

## Deployment model

Each buyer gets an independent installation on their own hosting and database. A single installation serves one office; it is not a multi-tenant SaaS. All team devices use the same HTTPS URL and authenticate against the same database.

The app is drop-in and does not need a document-root change. Keep the code and data folders unreachable by leaving the shipped `.htaccess` deny rules in place on Apache, and by adding an equivalent `location` deny block on Nginx if the panel exposes vhost editing.

## Initial application shape

- PHP 8.2+ with PDO MySQL for server-side routes and database access.
- MySQL 8.0 or MariaDB 10.6+ for persistence.
- HTML, CSS, JavaScript, Bootstrap, and Chart.js for the browser UI.
- Admin and Staff roles, enforced server-side on every protected action.
- Database-backed in-app notifications; no polling/realtime or email promise in the first release.

## Initial data model

`001_initial_schema.sql` establishes the first useful vertical slice: users, office settings, requests and comments, tasks, assets, inventory and stock movements, announcements, notifications, and activity logs. Vendors, room bookings, document/SOP management, and reports are later modules and should get new migrations only after their workflows are agreed.

A recurring task row records its recurrence setting, but recurrence generation belongs to application logic and is not implemented by the schema alone. Inventory quantity and its movement ledger must be updated in one database transaction once those actions are implemented.

## Security decisions

- Hash passwords with PHP `password_hash()` and verify with `password_verify()`; never store or log plain passwords.
- Use prepared PDO statements, CSRF tokens on state-changing requests, output escaping, and secure session-cookie settings.
- Restrict all requests by the signed-in user’s role and active status on the server, not only in the UI.
- Use HTTPS in deployment. Keep secrets and private configuration outside the public web root.
- Record operational events without putting sensitive request details into the activity log.
- Provide database backup/restore and a non-destructive, versioned update process before the app is sold.

## Release sequence

1. Review the current static UI preview and agree on the core user workflows.
2. Implement authentication and the shared shell.
3. Build Requests end-to-end, then Tasks, Assets, and Inventory in tested vertical slices.
4. Add notifications, roles/settings, reports, and secondary modules as scoped work.
5. Test the installer and upgrade path on a clean local MySQL/MariaDB database before deploying a demo.
