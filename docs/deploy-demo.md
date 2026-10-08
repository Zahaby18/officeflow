# Deploy the current UI preview to the demo subdomain

## What this deploy contains

The current `public/` directory is a static HTML/CSS/JavaScript preview. It uses mock data and does not connect to PHP, MySQL, or the migration. For this preview, upload only the contents of `public/`; do not upload `config/`, `database/`, or the whole development repository to the web root.

## Preflight observed from the development environment

- `e-officeflow.azdevs.my.id` resolved to `147.93.158.212`.
- An HTTPS request failed during TLS negotiation with `tlsv1 unrecognized name`.
- A plain HTTP request returned an empty reply.

This means the subdomain’s web-server virtual host / TLS setup was not verified from here. DNS resolving to the VPS is not enough; the server needs a matching host configuration and a working certificate before the preview can be viewed reliably.

## Manual SFTP deployment once the web root is confirmed

1. In the hosting/server panel, identify or create the document root for `e-officeflow.azdevs.my.id`. Do not guess the path; it depends on the existing Nginx/Apache or hosting-panel setup.
2. Configure the web server to serve that hostname from the chosen directory and enable HTTPS with a certificate for `e-officeflow.azdevs.my.id`.
3. From this repository, upload the **contents of `public/`** to that document root, preserving `assets/css/` and `assets/js/` paths. `index.html` should be directly in the document root.
4. Open `https://e-officeflow.azdevs.my.id/` and verify that the dashboard and CSS/JS assets load. Check one desktop and one mobile viewport.

Use a dedicated deployment account/key restricted to this site directory. Do not share server passwords, private keys, or tokens in Discord. A GitHub Actions deployment can replace manual SFTP later, after the server path and least-privilege SSH account are confirmed.

## Later, when the PHP/MySQL app exists

The deployment model changes: point the subdomain’s document root at the app’s `public/` directory, keep the app/config/database files outside the public web root, create a separate demo database, import the demo installation migration, and use synthetic demo accounts/data. Never deploy buyer credentials or real office records to the public demo.

This document is guidance only. No files have been deployed to the VPS, and no server configuration has been changed.
