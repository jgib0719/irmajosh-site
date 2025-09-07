# irmajosh.com – Production Runbook

## Overview
- Root (`/`) hosts public landing + Google Sign-In button.
- Secure app lives under `/secure/` and **never** renders a Google button. It redirects to `/` if not authenticated.
- All sensitive config is in `/var/www/irmajosh.com/private/cal.ini`.

## File Layout
/var/www/irmajosh.com
├── private/
│ └── cal.ini
├── public_html/
│ ├── .htaccess
│ ├── index.html
│ ├── js/login-exchange.js
│ └── secure/
│ ├── .htaccess
│ ├── api/
│ │ ├── _util.php
│ │ ├── auth.php
│ │ ├── config.php
│ │ ├── config.local.php (optional)
│ │ ├── config.js.php (exports GSI_CLIENT_ID to JS)
│ │ ├── events.php
│ │ ├── jobs.php
│ │ └── requests.php
│ ├── css/styles.css
│ ├── favicon.ico
│ ├── health_db.php
│ ├── index.html
│ └── js/{api.js,auth.js,bridge.js,calendar.js,calendar-view.js,constants.js,main.js,ui.js}
└── /var/backups/irmajosh/ops_drop/
├── schema.sql
└── cal.sql

makefile
Copy code

## Config (`/var/www/irmajosh.com/private/cal.ini`)
```ini
[db]
host    = localhost
name    = irmajosh
user    = <redacted>
pass    = <redacted>
charset = utf8mb4

[auth]
# 32-byte hex secret; used by health_db.php (X-App-Secret)
shared_secret = <hex>

[google]
client_id = 665405798855-...apps.googleusercontent.com
