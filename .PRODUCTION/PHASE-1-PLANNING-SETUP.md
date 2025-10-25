# PHASE 1: PLANNING & SETUP

**Estimated Time:** 2-4 hours (varies by familiarity)

**Purpose:** Establish development environment, prerequisites, and project foundation

---

## Before You Begin Phase 1

**Time Requirements:**
- Phase 1: 2-4 hours (setup, varies by familiarity)
- Total project: 20-30 hours across all phases

**Skill Requirements:**
- Intermediate PHP knowledge
- Basic SQL/MySQL
- Command line comfort
- Git version control
- Basic Apache configuration

**Hardware Requirements:**
- Development machine with 8GB+ RAM
- 5GB+ free disk space
- Stable internet connection (for Google APIs)

**Accounts Needed:**
- GitHub account
- Google account (for Cloud Console)
- SMTP provider account (Gmail/SendGrid)
- Domain registrar access (if deploying)

---

## Prerequisites Checklist

- [ ] PHP 8.2+ installed
- [ ] Apache 2.4+ with PHP-FPM (NOT mod_php)
- [ ] MySQL 8.0+ installed
- [ ] Composer installed globally
- [ ] Git installed and configured
- [ ] Local development environment ready (Apache + PHP-FPM)
- [ ] GitHub account ready for repository

**⚠️ IMPORTANT:** Use PHP-FPM locally (e.g., Apache + PHP-FPM). Avoid mod_php even in dev to match production behavior.

---

## Critical Requirements

Before starting implementation, understand these critical requirements:

- Define `__()` translation function only once in helpers.php
- Create composer.json with PSR-4 autoloading (namespace App\ → src/)
- Implement migration tracking to prevent re-run errors (use _migrations table, remove IF NOT EXISTS from DDL)
- Add session regeneration on login for security (no privilege system in this 2-user app)
- Create global exception handler for production error handling
- Use LOCK_EX flag when writing to log files
- Implement data encryption for Google tokens using libsodium with key versioning
- Add redirect URL validation to prevent open redirects
- Include all translation keys in language files with fallback to English
- Create deterministic, versioned migration files without IF NOT EXISTS
- Create 500.php error page for user-friendly server errors
- Add .gitkeep files to storage directories
- Explicitly set MySQL timezone to UTC in database connection
- Create front controller flow: public_html/index.php → bootstrap.php → router.php
- Add session cookie with __Host- prefix (requires: Secure=true, Path=/, no Domain attribute)
- Implement explicit session destruction on logout
- Add database connection error handling with retry logic
- Verify PHP extensions (sodium, intl) are enabled during setup
- Configure CSP nonce generation per-request in bootstrap.php
- Set up SMTP configuration for schedule request email notifications

### CSP Nonce Generation (CRITICAL)

**Implementation Requirements:**
- Generate nonce per-request in bootstrap.php
- Store in `$GLOBALS['csp_nonce']` (NOT `$_SESSION`)
- Use cryptographically secure random bytes
- Base64url encode (URL-safe, no padding)
- Set CSP header in `bootstrap.php`, NOT in Apache

**Code Example:**
```php
// In bootstrap.php (will be created in Phase 3)
$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
$GLOBALS['csp_nonce'] = $nonce; // CRITICAL: Use $GLOBALS, not $_SESSION

// Set CSP header with nonce
header("Content-Security-Policy: script-src 'nonce-{$nonce}' 'strict-dynamic'; ...");
```

**Why $GLOBALS and not $_SESSION:**
- Avoids race conditions with parallel requests
- Each request gets unique nonce
- No session state pollution

**Usage in Views:**
```php
<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce']) ?>">
    // Your inline JavaScript
</script>
```

### Migration Tracking System

**Why NO "IF NOT EXISTS":**
- Makes migrations deterministic (same result every time)
- Forces explicit tracking of what's been run
- Prevents hidden failures from idempotent DDL

**How Tracking Works:**
1. First migration creates `_migrations` table (migration 000)
2. Each migration checks `_migrations` before running
3. Migrations log their execution in `_migrations`
4. Re-running migration script skips already-run migrations

**Migration File Naming:**
- Format: `NNN_description.sql` (e.g., `001_create_users_table.sql`)
- Three-digit prefix for ordering
- Underscore-separated description

**Example Migration Structure:**
```sql
-- migrations/001_create_users_table.sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    ...
); -- NO "IF NOT EXISTS"
```

**First Migration (Required):**
Create `_migrations` in `000_create_migrations_table.sql` and record every migration.

Full implementation details in Phase 2.

### Session Cookie Security (Implementation in Phase 3)

**Required Session Cookie Configuration:**
```php
// In bootstrap.php (Phase 3)
session_set_cookie_params([
    'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
    'path' => '/',
    'domain' => '', // Empty = current domain only
    'secure' => true, // HTTPS only
    'httponly' => true, // No JavaScript access
    'samesite' => 'Lax' // CSRF protection
]);

// Start session with __Host- prefix
session_name('__Host-IRMAJOSH_SESSION');
session_start();
```

**__Host- Prefix Requirements:**
- Cookie name starts with `__Host-`
- Must set `Secure=true` (HTTPS only)
- Must set `Path=/`
- Must NOT set `Domain` attribute

This prevents subdomain cookie attacks.

### Logging Configuration

**Log File Locations:**
- Application logs: `storage/logs/app-{date}.log`
- Error logs: `storage/logs/error-{date}.log`
- Auth logs: `storage/logs/auth-{date}.log`

**Log File Format:**
- Date format: `Y-m-d` (e.g., `app-2025-10-22.log`)
- Rotate daily (separate file per day)
- Retain for 30 days (cleanup via cron)

**Writing to Logs (Use LOCK_EX):**
```php
// Example log writing function (implement in Phase 3)
function logMessage($level, $message, $context = []) {
    $logFile = __DIR__ . '/../storage/logs/app-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextJson = json_encode($context);
    $line = "[{$timestamp}] {$level}: {$message} {$contextJson}\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
```

### Timezone Handling Strategy

**Core Principle:** UTC everywhere, convert at edges

**Storage (Database):**
- All timestamps stored as UTC
- Column type: `DATETIME` or `TIMESTAMP`
- MySQL timezone set to UTC: `SET time_zone = '+00:00';`

**Processing (PHP):**
- All date operations in UTC
- Set default timezone: `date_default_timezone_set('UTC');`

**Display (Views & JavaScript):**
- Convert to user's timezone in views
- FullCalendar: Set `timeZone` option to user's timezone
- PHP: Use `DateTimeZone` for conversion

**Example Conversion (in views):**
```php
// Convert UTC to user timezone for display
$utcDate = new DateTime($row['event_start'], new DateTimeZone('UTC'));
$userDate = $utcDate->setTimezone(new DateTimeZone($_SESSION['user_timezone'] ?? 'UTC'));
echo $userDate->format('Y-m-d H:i:s');
```

**MySQL Connection:**
```php
// Set timezone to UTC on connection
$pdo->exec("SET time_zone = '+00:00'");
```

Full implementation in Phase 3.

### Apache Configuration (Important for Phase 4)

- Production will use `AllowOverride None` (all config in VirtualHost, no .htaccess)
- All routing handled via front controller (public_html/index.php → bootstrap.php → router.php)
- Do NOT create .htaccess files - they will be ignored in production
- Test locally without .htaccess to match production behavior
- CSP headers MUST be set in PHP (bootstrap.php), NOT in Apache VirtualHost

---

## Development Rules

**IMPORTANT:** Follow these rules throughout the entire project:

### Build & Tooling
- ❌ No build tools or preprocessors (Tailwind, Sass, Webpack, etc.)
- ❌ No third-party services except Google APIs
- ❌ No CDNs - host HTMX and FullCalendar locally
- ✅ Custom CSS only, written from scratch

### Git Workflow
- ✅ Develop locally, push to GitHub, pull to server for deployment
- ❌ Never edit code directly on production server
- ✅ Use standard `git push` from local machine
- ✅ Use `git pull` on server for deployment

### Data Handling
- ✅ Store all timestamps in UTC
- ✅ Convert timestamps at the edges (FullCalendar & views)
- ❌ Never log PII (tokens, refresh tokens, raw Google payloads)
- ✅ Add redaction for error logs

---

## Naming Conventions

**Be consistent across the entire codebase:**

| Element | Convention | Example |
|---------|-----------|---------|
| Functions & Variables | camelCase | `getUserData()`, `$userName` |
| Classes | PascalCase | `UserController`, `CalendarService` |
| Database Columns | snake_case | `user_id`, `created_at` |
| CSS Classes & Files | kebab-case | `.btn-primary`, `user-profile.php` |

---

## Technology Stack

### Server Environment
- **OS:** Ubuntu/Debian
- **Web Server:** Apache 2.4+
- **PHP:** 8.2+ with PHP-FPM (not mod_php)
- **Database:** MySQL 8.0+

### Backend
- **Architecture:** Custom MVC pattern
- **Database:** PDO with prepared statements
- **Database Connections:** Non-persistent PDO connections (`PDO::ATTR_PERSISTENT => false`) for reliability in low-traffic scenarios
- **Dependencies:** 
  - `google/apiclient` - Google Calendar API
  - `vlucas/phpdotenv` - Environment variables
  - `phpmailer/phpmailer` - Email notifications

### Frontend
- **CSS:** Custom CSS (no frameworks)
- **JavaScript:** HTMX (self-hosted), FullCalendar (self-hosted), vanilla JS
- **PWA:** Service Worker, Web App Manifest

### Security
- **HTTPS:** Required for all environments (development can use http://localhost with SESSION_SECURE=false only if APP_ENV=development)
- **Authentication:** Google OAuth with email allowlist
- **Protection:** CSRF tokens, rate limiting (100 req/15 min per IP on `/auth/login`, `/auth/callback`, `/csp-report`, and email endpoints only), CSP with nonces
- **Sessions:** Secure sessions with __Host- prefix (Secure=true, Path=/, no Domain attribute)

### Required PHP Extensions
- `pdo_mysql` (installed via php8.2-mysql package)
- `curl`
- `mbstring`
- `xml`
- `zip`
- `sodium` (for encryption)
- `intl` (for internationalization)

**Verification Command:**
```bash
php -m | grep -E 'pdo_mysql|sodium|intl|curl|mbstring|xml|zip'
```

---

## Application Features

This application provides the following functionality:

1. **Authentication**
   - Google OAuth authentication
   - Email allowlist (only approved users can login)
   - Secure session management

2. **Calendar Integration**
   - Google Calendar read/write sync
   - View and manage calendar events
   - Automatic token refresh

3. **Task Management**
   - Shared tasks (both users can view/edit)
   - Private tasks (user-specific)
   - Task CRUD operations

4. **Schedule Requests**
   - Propose meeting times
   - Accept/decline schedule requests
   - Email notifications

5. **Progressive Web App**
   - Installable on mobile devices
   - Offline support
   - Service worker caching

6. **Multi-Language**
   - English interface
   - Indonesian interface
   - Fallback to English for missing translations

---

## Directory Structure Setup

Create the following directory structure:

```
irmajosh.com/
├── config/                    # Configuration files
├── locales/                   # Translation files
│   ├── en/                   # English translations
│   └── id/                   # Indonesian translations
├── migrations/                # Database migration files
├── public_html/               # Public web root
│   ├── .well-known/          # ACME challenges, PWA files
│   ├── assets/               # Static assets
│   │   ├── css/             # Custom stylesheets
│   │   │   └── vendor/      # Self-hosted CSS libraries
│   │   └── js/              # JavaScript files
│   │       └── vendor/      # Self-hosted JS libraries
│   └── views/                # PHP view templates
│       ├── components/       # Reusable view components
│       ├── emails/           # Email templates
│       │   ├── en/          # English email templates
│       │   └── id/          # Indonesian email templates
│       └── errors/           # Error pages (403, 404, 500, 503)
├── scripts/                   # Utility scripts
├── src/                       # Application source code
│   ├── Controllers/          # MVC Controllers
│   ├── Models/               # MVC Models
│   └── Services/             # Business logic services
└── storage/                   # Runtime storage
    ├── cache/                # File-based cache (.gitkeep)
    └── logs/                 # Application logs (.gitkeep)
```

**Commands to create:**
```bash
mkdir -p config locales/{en,id} migrations public_html/{.well-known,assets/{css,js}/{vendor,},views/{components,emails/{en,id},errors}} scripts src/{Controllers,Models,Services} storage/{cache,logs}

# Create .gitkeep files
touch storage/cache/.gitkeep storage/logs/.gitkeep

# Create placeholder script files
touch scripts/backup.sh scripts/deploy.sh scripts/migrate.php

# Make scripts executable
chmod +x scripts/*.sh
```

---

## Composer Setup

### Create composer.json

```json
{
    "name": "irmajosh/irmajosh-com",
    "description": "Private shared calendar and task management application",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "google/apiclient": "^2.15",
        "vlucas/phpdotenv": "^5.5",
        "phpmailer/phpmailer": "^6.8"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "config": {
        "optimize-autoloader": true,
        "classmap-authoritative": true
    }
}
```

---

## Local Development Environment

### Step 1: Verify PHP Extensions

**Time Estimate:** 5 minutes

```bash
php -m | grep -E 'pdo_mysql|sodium|intl|curl|mbstring|xml|zip'
```

If any are missing, install them:

```bash
# Ubuntu/Debian
sudo apt install php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip php8.2-intl php8.2-fpm

# macOS (Homebrew)
brew install php@8.2
brew services start php@8.2

# Verify sodium is compiled in (usually is in PHP 8.2+)
php -i | grep sodium
# Should show: sodium support => enabled
```

### Step 2: Initialize Git Repository

**Time Estimate:** 10 minutes

**Option 1: Create GitHub repo first (Recommended)**
```bash
# Create repo on GitHub, then:
git clone https://github.com/yourusername/irmajosh.com.git
cd irmajosh.com
```

**Option 2: Initialize locally first**
```bash
# Initialize local repo
cd irmajosh.com
git init
git branch -M main

# Create initial commit (after creating .gitignore)
git add .gitignore
git commit -m "Initial commit"

# Link to GitHub (create repo on GitHub first)
git remote add origin https://github.com/yourusername/irmajosh.com.git
git push -u origin main
```

### Step 3: Install Dependencies (LOCAL ONLY)

**Time Estimate:** 15 minutes

**⚠️ IMPORTANT:** Only run `composer require` on your local machine, NEVER on the server.

```bash
# Install all dependencies from composer.json
composer install --no-dev --optimize-autoloader

# Validate composer.json
composer validate

# Optimize autoloader
composer dump-autoload -o

# Verify autoload works (optional)
php -r "require 'vendor/autoload.php'; echo 'Autoload working!\n';"

# Commit to git
git add composer.json composer.lock
git commit -m "Add dependencies"
```

### Composer Dependency Locking

**CRITICAL:** The `composer.lock` file MUST be committed to git.

**Why:**
- Locks exact dependency versions across all environments
- Ensures production uses same versions as development
- Prevents "works on my machine" issues

**Verification:**
```bash
# Verify composer.lock exists
ls -lh composer.lock

# Check it's tracked in git
git status composer.lock

# If not staged, add it
git add composer.lock
git commit -m "Lock composer dependencies"
```

**On Server (Phase 4):**
Always use `composer install --no-dev` (NOT `composer update`)

### Step 4: Download Self-Hosted Frontend Assets

**Time Estimate:** 10 minutes

**⚠️ IMPORTANT:** Never use CDNs at runtime. Download and self-host all frontend libraries. These commands are **build-time only**; never reference CDNs at runtime.

```bash
# Create vendor directories
mkdir -p public_html/assets/js/vendor
mkdir -p public_html/assets/css/vendor

# Download HTMX (version 1.9.10)
wget https://unpkg.com/htmx.org@1.9.10/dist/htmx.min.js \
  -O public_html/assets/js/vendor/htmx.min.js

# Download FullCalendar (version 5.11.5 - fully MIT licensed)
# Note: Using v5.11.5 instead of v6 to avoid licensing issues for private apps
wget https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js \
  -O public_html/assets/js/vendor/fullcalendar.min.js
wget https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css \
  -O public_html/assets/css/vendor/fullcalendar.min.css

# Verify downloads
ls -lh public_html/assets/*/vendor/

# Frontend Assets Security
# Verify file sizes match expected (check release notes)
# Optional: Generate SHA256 hashes for verification
sha256sum public_html/assets/js/vendor/*.js > ASSET_HASHES.txt
git add ASSET_HASHES.txt

# Commit to git
git add public_html/assets/
git commit -m "Add self-hosted HTMX and FullCalendar v5.11.5 assets"
```

### FullCalendar Licensing (IMPORTANT)

**FullCalendar v5 vs v6:**
- **v5.11.5:** Last fully MIT licensed version (recommended for private apps)
- **v6.x:** Dual-license model (MIT for open source, commercial for proprietary)

**For IrmaJosh.com (Private 2-User App):**
- Using FullCalendar v5.11.5 to maintain MIT license compliance
- This is a PRIVATE application (not open source)
- v5 provides all needed features for this project

**Why This Approach:**
- Git commit hashes already track all file changes
- Assets never change unless explicitly updated via git commit
- Version pinning is sufficient for this project's risk profile
- Assets only change via explicit `git commit` (never at runtime)

### Step 5: Create .gitignore

**Time Estimate:** 5 minutes

Create `.gitignore` in project root:

```gitignore
# Environment configuration
.env
.env.local
.env.*.local

# Composer dependencies
/vendor/

# Runtime storage
/storage/logs/*
!/storage/logs/.gitkeep
/storage/cache/*
!/storage/cache/.gitkeep

# Backups (NEVER commit these)
/backups/
*.sql
*.sql.gz
*.gpg

# IDE and system files
.vscode/
.idea/
.DS_Store
Thumbs.db

# Temporary files
*.tmp
*.swp
*~
```

**Commit immediately after creation:**
```bash
git add .gitignore
git commit -m "Add .gitignore"
```

### Step 6: Create robots.txt

Create `public_html/robots.txt` to prevent search engine indexing:

```txt
User-agent: *
Disallow: /
```

This blocks ALL search engine crawlers from indexing ANY pages.

**Commit to git:**
```bash
git add public_html/robots.txt
git commit -m "Add robots.txt to prevent indexing"
```

**Verification:** After deployment, test at `https://irmajosh.com/robots.txt`

### Step 7: Environment Configuration

```bash
# Copy example to actual .env (will configure in Phase 2)
cp .env.example .env
```

### Step 8: Local HTTPS Setup

**Time Estimate:** 20-30 minutes

Choose one option for local HTTPS development:

**Option 1: mkcert (Recommended)**
```bash
# Install mkcert
brew install mkcert  # macOS
# OR
sudo apt install mkcert  # Ubuntu/Debian

# Install local CA
mkcert -install

# Generate certificate for localhost
mkcert localhost 127.0.0.1 ::1

# Certificates will be created as localhost+2.pem and localhost+2-key.pem
# Configure Apache to use these certificates (see Apache Configuration below)
```

**Option 2: HTTP Localhost (Development Only)**
```bash
# Configure Google OAuth to accept http://localhost
# Note: Use SESSION_SECURE=false ONLY for APP_ENV=development
# Ensure OAuth redirect URIs are environment-specific
```

**⚠️ IMPORTANT:** ngrok option removed as it violates the "no third-party services" policy.

### Apache Configuration Requirements

**Required Apache Modules:**
```bash
# Enable required modules
sudo a2enmod rewrite ssl proxy_fcgi setenvif
sudo a2enconf php8.2-fpm
sudo systemctl restart apache2
```

**VirtualHost Configuration (Example for Local Dev):**

Create `/etc/apache2/sites-available/irmajosh-local.conf`:

```apache
<VirtualHost *:443>
    ServerName localhost
    DocumentRoot /path/to/irmajosh.com/public_html
    
    # SSL Configuration (mkcert example)
    SSLEngine on
    SSLCertificateFile /path/to/localhost+2.pem
    SSLCertificateKeyFile /path/to/localhost+2-key.pem
    
    # PHP-FPM Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Directory Configuration (AllowOverride None)
    <Directory /path/to/irmajosh.com/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        
        # Front controller routing (inline, no .htaccess)
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [L]
    </Directory>
    
    # DO NOT set CSP headers here - must be set in PHP
    
    # Error pages (will be created in Phase 3)
    ErrorDocument 403 /views/errors/403.php
    ErrorDocument 404 /views/errors/404.php
    ErrorDocument 500 /views/errors/500.php
    ErrorDocument 503 /views/errors/503.php
</VirtualHost>
```

**Enable site and restart Apache:**
```bash
sudo a2ensite irmajosh-local.conf
sudo systemctl restart apache2
```

**IMPORTANT:** 
- Use `AllowOverride None` (no .htaccess support)
- Configure PHP-FPM, NOT mod_php
- Do NOT set CSP headers in Apache (must be in PHP)

---

### Step 9: Create MySQL Database

**Time Estimate:** 10 minutes

**Create database and user:**
```sql
-- Connect to MySQL as root
mysql -u root -p

-- Create database with utf8mb4 charset
CREATE DATABASE irmajosh_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user with minimal privileges
CREATE USER 'irmajosh_user'@'localhost' IDENTIFIED BY 'your-secure-password';

-- Grant only required permissions
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON irmajosh_db.* TO 'irmajosh_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

**Verify connection:**
```bash
mysql -u irmajosh_user -p irmajosh_db
```

**Security Notes:**
- Use strong password (min 20 characters)
- Store password in password manager
- User has NO DROP privilege (safety)
- User limited to localhost only

### Step 10: Create README.md

Create `README.md` in project root:

```markdown
# IrmaJosh.com - Personal Calendar & Task Manager

Private 2-user calendar and task management application with Google Calendar integration.

## Features
- Google OAuth authentication
- Google Calendar sync
- Task management (shared & private)
- Schedule request system
- Progressive Web App
- Bilingual (English/Indonesian)

## Tech Stack
- PHP 8.2+ / MySQL 8.0+
- Custom MVC (no frameworks)
- HTMX + FullCalendar
- Self-hosted assets only

## Setup
See implementation documentation in project planning docs.

## Security
- OAuth 2.0 with PKCE
- CSRF protection
- CSP with nonces
- Rate limiting
- Email allowlist

## License
Private project - All rights reserved
```

```bash
git add README.md
git commit -m "Add README"
```

---

## Google Cloud Setup

**Time Estimate:** 30-45 minutes

### Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a project" → "New Project"
3. Project name: `irmajosh-calendar`
4. Click "Create"

### Step 2: Enable Google Calendar API

1. Navigate to "APIs & Services" → "Library"
2. Search for "Google Calendar API"
3. Click "Enable"

### Step 3: Configure OAuth Consent Screen

1. Navigate to "APIs & Services" → "OAuth consent screen"
2. Choose user type:
   - **External:** For personal Gmail accounts (choose this for personal Gmail)
   - **Internal:** If using Google Workspace (organization users only)
3. Fill in required fields:
   - App name: `IrmaJosh Calendar`
   - User support email: your email
   - Developer contact: your email
4. Click "Save and Continue"
5. Add scopes:
   - In "Scopes" step → "Add or Remove Scopes"
   - Manually add: `https://www.googleapis.com/auth/calendar.events`
   - Verify `openid`, `email`, `profile` are automatically included
6. Click "Save and Continue"
7. Add test users (for External apps):
   - Add both user email addresses who will use the app
8. Click "Save and Continue"

**Note:** "Continue" button on consent screen is normal for unverified apps. This is acceptable for personal 2-user apps.

**Testing Status:** For 2-user personal apps, leave in "Testing" status. No verification needed.

**Unverified App Warning:** Personal apps will show "unverified app" warning during OAuth. This is acceptable for 2-user personal use. To remove, you must complete Google verification (complex process, not recommended for personal apps).

### Step 4: Create OAuth 2.0 Credentials

1. Navigate to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth client ID"
3. Application type: "Web application"
4. Name: `IrmaJosh Web Client`
5. Authorized redirect URIs:
   - `https://irmajosh.com/auth/callback` (production)
   - `https://localhost/auth/callback` (local dev, if using HTTPS)
   - `http://localhost/auth/callback` (local dev HTTP, if needed)
6. Click "Create"

### Step 5: Configure OAuth Scopes

**Required Scopes:**
- `openid` - REQUIRED for ID token verification
- `email` - Get user email
- `profile` - Get user profile info
- `https://www.googleapis.com/auth/calendar.events` - Full calendar read/write

**Note:** The `openid` scope is CRITICAL. Without it, ID token verification will fail.

**Note:** `calendar.events` provides full read/write access, which is sufficient for this application.

### Step 6: Configure for Offline Access

OAuth must be configured for offline access to obtain refresh tokens.

**Required OAuth Authorization URL Parameters:**
- `access_type=offline` - Request refresh token
- `prompt=consent` - Force consent screen to get refresh token
- `code_challenge` - PKCE code challenge (SHA256 hash of code_verifier, base64url)
- `code_challenge_method=S256` - PKCE method

**Example Authorization URL (will be implemented in Phase 3):**
```
https://accounts.google.com/o/oauth2/v2/auth?
  client_id={CLIENT_ID}&
  redirect_uri={REDIRECT_URI}&
  response_type=code&
  scope=openid%20email%20profile%20https://www.googleapis.com/auth/calendar.events&
  access_type=offline&
  prompt=consent&
  code_challenge={CODE_CHALLENGE}&
  code_challenge_method=S256&
  state={STATE_TOKEN}
```

These will be implemented in the AuthController in Phase 3.

### Step 7: PKCE Implementation

Implement PKCE (Proof Key for Code Exchange) for additional security:
- Generate `code_verifier`: 43-128 random characters
- Generate `code_challenge`: SHA256 hash of code_verifier, base64url encoded
- Include in OAuth authorization request

### Step 8: ID Token Verification

**CRITICAL:** Must verify ID tokens from Google:
- Fetch Google's JWKS (JSON Web Key Set)
- Verify signature using public keys
- Validate claims: `aud`, `iss`, `exp`, `sub`

### Step 9: User Identification

**IMPORTANT:** Use the `sub` claim from ID token as stable user identifier (NOT email, which can change).

Store `sub` claim in `users.google_user_id` column.

### Step 10: Copy Credentials

1. After creating credentials, copy:
   - Client ID
   - Client Secret
2. Save to notes (will add to .env in Phase 2)

**⚠️ SECURITY:** Never commit Client Secret to git. Keep in .env file only.

---

## Create .env.example Template

Create `.env.example` with dummy values (will configure actual .env in Phase 2):

```env
# Application
APP_NAME="IrmaJosh Calendar"
APP_ENV=development
APP_URL=http://localhost
# APP_SECRET: Generate with: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
# CRITICAL: Store in password manager separately, NEVER in backups
APP_SECRET=your-64-character-hex-secret-here

# Database
DB_HOST=localhost
DB_NAME=irmajosh_db
DB_USER=irmajosh_user
DB_PASS=your-database-password

# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost/auth/callback

# SMTP Email
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your-smtp-username
SMTP_PASS=your-smtp-password
SMTP_FROM_EMAIL=noreply@irmajosh.com
SMTP_FROM_NAME="IrmaJosh Calendar"

# Session
SESSION_LIFETIME=7200
# SESSION_SECURE: Set to false ONLY for APP_ENV=development with http://localhost
SESSION_SECURE=true

# Timezone
APP_TIMEZONE=UTC

# Security
CSP_REPORT_URI=/csp-report
RATE_LIMIT_WINDOW=900
RATE_LIMIT_MAX=100
TOKEN_KEY_VERSION=1
```

### Generate APP_SECRET (REQUIRED)

**Purpose:** APP_SECRET is used for CSRF token generation, session encryption, and other cryptographic operations.

**Generation Command:**
```bash
# Generate 64-character hex secret (32 bytes = 256 bits)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

**Security Requirements:**
- Must be 64 hexadecimal characters (256-bit entropy)
- Generate new secret for each environment (dev, production)
- Store in password manager (1Password, Bitwarden, etc.)
- NEVER commit to git
- NEVER include in application backups
- Rotate annually or if compromised

**Example Output:**
```
a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

Add this value to your `.env` file under `APP_SECRET=`

### SMTP Email Configuration

**Recommended SMTP Providers (for personal use):**

1. **Gmail SMTP** (Free, 500 emails/day)
   - Host: `smtp.gmail.com`
   - Port: `587` (TLS)
   - Requires App Password (not account password)
   - Setup: Google Account → Security → 2-Step Verification → App Passwords

2. **SendGrid** (Free, 100 emails/day)
   - Host: `smtp.sendgrid.net`
   - Port: `587`
   - Use API key as password

3. **Mailgun** (Free, 5,000 emails/month for 3 months)
   - Host: `smtp.mailgun.org`
   - Port: `587`

**For IrmaJosh.com (2 users, schedule requests only):**
- Gmail SMTP is sufficient
- Expected volume: <50 emails/month
- Setup Gmail App Password in Phase 2

**Development SMTP:**
- Real SMTP preferred (use dedicated test Gmail account with app-specific password)
- Testing matches production behavior
- Email deliverability issues caught early
- Schedule request feature requires actual email testing

**Optional: Local Email Testing (Mailhog)**

If you prefer not to use real SMTP in development:
1. Install Mailhog: `brew install mailhog` (macOS) or `apt install golang-go && go install github.com/mailhog/MailHog@latest` (Linux)
2. Run Mailhog: `mailhog`
3. Configure .env for local dev:
   - SMTP_HOST=localhost
   - SMTP_PORT=1025
   - SMTP_USER= (leave empty)
   - SMTP_PASS= (leave empty)
4. View caught emails at http://localhost:8025

---

## Git Workflow Best Practices

**Commit Messages:**
- Use imperative mood ("Add feature" not "Added feature")
- Keep first line under 50 characters
- Add detailed description if needed

**Branch Strategy:**
- `main` branch: production-ready code
- `dev` branch: integration branch (create in Phase 3)
- Feature branches: `feature/oauth-integration`
- Hotfix branches: `hotfix/csrf-token-bug`

**Never Commit:**
- `.env` file (use .env.example)
- `vendor/` directory (tracked in composer.lock)
- `storage/logs/` and `storage/cache/` (runtime data)
- Database backups
- Binary files over 1MB

**Atomic Commits:**
- One logical change per commit
- All tests pass before committing
- Commit frequently, push when stable

---

## Common Issues & Solutions

### Issue: "php-sodium extension not found"
**Solution:** Sodium is compiled into PHP 8.2+ by default. Verify:
```bash
php -i | grep sodium
# Should show: sodium support => enabled
```

### Issue: "Composer command not found"
**Solution:** Install globally:
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

### Issue: "OAuth redirect URI mismatch"
**Solution:** Ensure redirect URI in Google Console EXACTLY matches your .env:
- Including protocol (https://)
- No trailing slash
- Port number if using non-standard (e.g., :8080)

### Issue: "Cannot write to storage/logs/"
**Solution:** Fix permissions:
```bash
chmod 775 storage/logs storage/cache
chown -R www-data:www-data storage/
```

---

## Phase 1 Completion Checklist

Before moving to Phase 2, verify:

- [ ] PHP 8.2+ installed with all required extensions verified
- [ ] PHP-FPM configured (NOT mod_php)
- [ ] Apache 2.4+ with required modules enabled
- [ ] MySQL 8.0+ database created (irmajosh_db)
- [ ] MySQL user created with appropriate permissions
- [ ] Local development environment set up (Apache + PHP-FPM)
- [ ] Git repository initialized with main branch
- [ ] GitHub repository created and linked as remote origin
- [ ] Directory structure created with all folders (including .well-known, emails, errors)
- [ ] .gitkeep files added to storage/cache and storage/logs
- [ ] Placeholder scripts created (backup.sh, deploy.sh, migrate.php)
- [ ] composer.json created with PSR-4 autoloading (irmajosh/irmajosh-com)
- [ ] Composer dependencies installed (google/apiclient, vlucas/phpdotenv, phpmailer/phpmailer)
- [ ] composer.json and composer.lock committed to git
- [ ] .gitignore created with all necessary patterns
- [ ] HTMX 1.9.10 downloaded to public_html/assets/js/vendor/
- [ ] FullCalendar 5.11.5 JS downloaded to public_html/assets/js/vendor/
- [ ] FullCalendar 5.11.5 CSS downloaded to public_html/assets/css/vendor/
- [ ] Frontend assets committed to git
- [ ] ASSET_HASHES.txt created with SHA256 hashes
- [ ] robots.txt created in public_html/
- [ ] README.md created in project root
- [ ] Local HTTPS configured (mkcert or http://localhost with SESSION_SECURE=false)
- [ ] Apache VirtualHost configured with AllowOverride None
- [ ] Google Cloud project created
- [ ] Google Calendar API enabled
- [ ] OAuth consent screen configured (External for Gmail)
- [ ] Test users added to OAuth consent screen
- [ ] OAuth 2.0 credentials created (Web application)
- [ ] Authorized redirect URIs configured
- [ ] Client ID and Client Secret saved to password manager
- [ ] Required OAuth scopes configured (openid, email, profile, calendar.events)
- [ ] PKCE implementation requirements understood
- [ ] ID token verification requirements understood
- [ ] CSP nonce generation requirements understood ($GLOBALS, not $_SESSION)
- [ ] Migration tracking system understood (_migrations table first)
- [ ] Session cookie security requirements understood (__Host- prefix)
- [ ] Timezone handling strategy understood (UTC everywhere)
- [ ] Logging configuration understood (LOCK_EX, daily rotation)
- [ ] .env.example file created with all parameters
- [ ] APP_SECRET generated and stored in password manager
- [ ] SMTP provider selected and configured

---

## Next Steps

✅ **Phase 1 Complete!**

Proceed to **Phase 2: Configuration** to:
- Design database schema (6 migration files)
- Configure environment variables
- Set up routing and security
- Create translation files

---

## Time Breakdown Summary

- PHP extension verification: 5 minutes
- Repository setup: 10 minutes
- Composer installation: 15 minutes
- Asset downloads: 10 minutes
- .gitignore and robots.txt: 5 minutes
- Local HTTPS setup: 20-30 minutes
- Apache configuration: 15-20 minutes
- Database creation: 10 minutes
- README creation: 5 minutes
- Google Cloud setup: 30-45 minutes
- .env.example creation: 5 minutes

**Total realistic time: 2-4 hours** (for experienced developers, first-time setup)

---

**PHASE 1 - PLANNING & SETUP v2.0 - October 22, 2025**  
**Updated:** Integrated GPT + Claude comprehensive review recommendations
