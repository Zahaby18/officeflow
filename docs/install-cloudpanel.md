# Install OfficeFlow on CloudPanel

The app is PHP + MySQL. Three things must exist before it opens: the app files, an empty database with the schema imported, and a `config/database.php` with the connection values. The static `public/preview.html` does not need any of this, but `public/index.php` does.

## 1. Create the site

In CloudPanel: **Sites → Add Site → Create a PHP Site** (not Static HTML).

- Domain: `e-officeflow.azdevs.my.id`
- PHP version: 8.2 or newer
- Keep the site user CloudPanel creates

CloudPanel sets the document root to a path like `/home/<site-user>/htdocs/e-officeflow.azdevs.my.id`. Note the exact path; the panel shows it on the site page.

## 2. Upload the files

Upload the **whole project** into that document-root directory, so you end up with:

```text
/home/<site-user>/htdocs/e-officeflow.azdevs.my.id/
├── app/
├── config/
├── database/
├── docs/
├── tests/
└── public/          <- this becomes the real web root
```

Do **not** upload `config/database.php`; you will create it on the server in step 4.

SFTP uses port 22. Create an SSH/SFTP user under the site's **SSH/FTP** tab and use that account, not root.

## 3. Create the database and import the schema

1. CloudPanel: **Databases → Add Database**. Set a database name, user, and a strong password.
2. On the same page, click **Manage** to open phpMyAdmin, log in as that database user, select the database, then **Import → Choose file → `001_initial_schema.sql` → Go**.

That is enough for this project: the database is empty and the schema file is about 9 KB. CloudPanel warns against phpMyAdmin for export/import in general, because it can corrupt large or populated databases; that does not apply to importing a small schema into a brand-new empty database.

The CLI route works too, if you prefer it:

```bash
ssh <site-user>@<server-ip>
cd ~/tmp/
clpctl db:import --databaseName=<database-name> --file=001_initial_schema.sql
```

Upload `database/migrations/001_initial_schema.sql` to `~/tmp/` first if it is not there.

## 4. Create the config

Copy `config/database.example.php` to `config/database.php` and enter the database name, user, and password from step 3. Do this on your own machine, then upload `config/database.php` with SFTP into the project's `config/` folder. Keep this file out of Git.

You can also create it directly on the server if you prefer:

```bash
cd /home/<site-user>/htdocs/e-officeflow.azdevs.my.id
cp config/database.example.php config/database.php
nano config/database.php
```

## 5. Point the web root at public/

CloudPanel: open the site → **Vhost** tab. Change the `root` line so it points at the `public` subfolder instead of the site directory, for example:

```nginx
root /home/<site-user>/htdocs/e-officeflow.azdevs.my.id/public;
```

Save. CloudPanel checks the syntax and reverts automatically if the change breaks the vhost. This keeps `app/`, `config/`, `database/`, and `tests/` unreachable from the browser.

## 6. Enable HTTPS

CloudPanel: site → **SSL/TLS → Actions → New Let's Encrypt Certificate**. Add `e-officeflow.azdevs.my.id`, then create and install. DNS for the domain must resolve before this works.

## 7. First run

Open `https://e-officeflow.azdevs.my.id`. Because there is no account yet, the app redirects to `install.php`. Fill in the office name, your name, email, and a password of at least 12 characters. That creates the first administrator, signs you in, and opens the dashboard. After that, `install.php` is closed automatically.

## Demo hygiene

This is a public demo, so use synthetic data. Do not import real office records, do not reuse a password you use elsewhere, and do not put database credentials in chat or in Git.

## What is not ready yet

Notifications, Tasks, Assets, Inventory, Team, and Settings are not implemented. Backups and an upgrade path still need to be built before this is sold as a product.

## Alternative: local test first

Before touching the server, the whole flow runs locally:

```bash
mariadb -uroot -e "CREATE DATABASE officeflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb -uroot officeflow_test < database/migrations/001_initial_schema.sql
cp config/database.example.php config/database.php   # edit values
php -S 127.0.0.1:8000 -t public
```

Then open `http://127.0.0.1:8000`.
