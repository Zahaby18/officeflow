# Install OfficeFlow on CloudPanel

The app is PHP + MySQL. Three things must exist before it opens: the app files, an empty database with the schema imported, and a `config/database.php` with the connection values.

OfficeFlow is **drop-in**. There is no `public/` folder to point at, so you do not touch the vhost or the document root. The same steps work on cPanel (`public_html`), Plesk, or any panel with a PHP host.

## 1. Create the site

CloudPanel: **Sites → Add Site → Create a PHP Site**.

- Domain: `e-officeflow.azdevs.my.id`
- PHP version: 8.2 or newer
- Keep the document root exactly as CloudPanel sets it
- Keep the site user CloudPanel creates

## 2. Upload the files

Upload the project into that document root so it looks like this:

```text
/home/<site-user>/htdocs/e-officeflow.azdevs.my.id/
├── index.php
├── login.php
├── install.php
├── logout.php
├── requests.php
├── tasks.php
├── inventory.php
├── module.php
├── team.php
├── reports.php
├── activity.php
├── settings.php
├── notifications.php
├── preview.html
├── officeflow-schema.sql
├── .htaccess
├── app/
├── assets/
└── config/
```

Do **not** upload `config/database.php`; create it on the server in step 4.

SFTP uses port 22. Create an SSH/SFTP user under the site's **SSH/FTP** tab and use that account, not root.

## 3. Create the database and import the schema

1. CloudPanel: **Databases → Add Database**. Set a database name, user, and a strong password.
2. On the same page, click **Manage** to open phpMyAdmin, log in as that database user, select the database, then **Import → Choose file → `officeflow-schema.sql` → Go**.

That one file creates all 15 tables. It is about 13 KB and safe to run twice.

CloudPanel warns against phpMyAdmin for export/import in general, because it can corrupt large or already-populated databases. That warning does not apply to importing a small schema into a brand-new empty database.

The CLI route works too, if you prefer it:

```bash
ssh <site-user>@<server-ip>
cd ~/tmp/
clpctl db:import --databaseName=<database-name> --file=officeflow-schema.sql
```

Upload `officeflow-schema.sql` to `~/tmp/` first if it is not there.

## 4. Create the config

Copy `config/database.example.php` to `config/database.php` and fill in the database name, user, and password from step 3. Do it on your own machine and upload the result into `config/`, or do it on the server:

```bash
cd /home/<site-user>/htdocs/e-officeflow.azdevs.my.id
cp config/database.example.php config/database.php
nano config/database.php
```

Keep this file out of Git and out of any backup you share.

## 5. Enable HTTPS

CloudPanel: site → **SSL/TLS → Actions → New Let's Encrypt Certificate**. Add `e-officeflow.azdevs.my.id`, then create and install. DNS for the domain must resolve before this works.

## 6. First run

Open `https://e-officeflow.azdevs.my.id`. With no account yet, the app redirects to `install.php`. Fill in the office name, your name, an email, and a password of at least 12 characters. That creates the first administrator, signs you in, and opens the dashboard. `install.php` then closes itself.

## Demo hygiene

This is a public demo, so use synthetic data. Do not import real office records, do not reuse a password you use elsewhere, and never put database credentials in chat or in Git.

## Alternative: test locally first

The whole flow runs locally before you touch the server:

```bash
mariadb -uroot -e "CREATE DATABASE officeflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb -uroot officeflow_test < officeflow-schema.sql
cp config/database.example.php config/database.php   # edit the values
php -S 127.0.0.1:8000
```

Then open `http://127.0.0.1:8000`.
