# PHASE 4: DEPLOYMENT

**Estimated Time:** 2-4 hours

**Purpose:** Deploy application to production server with Apache, PHP-FPM, MySQL, and SSL

---

## Overview

Deploy the application to production:
- Server setup and configuration
- Apache VirtualHost with security headers
- PHP-FPM pool configuration
- Database setup and migrations
- SSL certificate installation
- Email deliverability configuration
- Deployment automation scripts

**CRITICAL:** All configuration in Apache VirtualHost (AllowOverride None). CSP header set in PHP (bootstrap.php), NOT Apache.

---

## Implementation Order

### 1. Server Prerequisites (30 minutes)
- [ ] Ubuntu/Debian server provisioned
- [ ] SSH access configured
- [ ] **Firewall configured (UFW: allow 22, 80, 443):**
  ```bash
  # Install UFW
  apt install ufw
  
  # Default policies (deny all incoming, allow outgoing)
  ufw default deny incoming
  ufw default allow outgoing
  
  # Allow SSH (CRITICAL: do this BEFORE enabling UFW to avoid lockout)
  ufw limit 22/tcp comment 'Rate-limit SSH'
  
  # Allow HTTP and HTTPS
  ufw allow 80/tcp comment 'HTTP'
  ufw allow 443/tcp comment 'HTTPS'
  
  # Enable UFW
  ufw enable
  
  # Verify rules
  ufw status verbose
  ```
- [ ] **Time synchronization installed and configured (CRITICAL for OAuth):**
  - [ ] Install chrony: `apt install chrony`
  - [ ] Verify chrony running: `systemctl status chrony`
  - [ ] Check time sync status: `timedatectl status`
    - Verify "System clock synchronized: yes"
    - Verify "NTP service: active"
  - [ ] Check time accuracy: `chronyc tracking`
    - "Last offset" should be < 1.0 seconds
  - [ ] **WHY CRITICAL:** OAuth token validation requires accurate timestamps
    - Google ID tokens include `iat` (issued at) and `exp` (expiry) claims
    - Clock skew > 30 seconds can cause authentication failures

### 2. System Installation (30 minutes)
- [ ] Install Apache 2.4+
- [ ] Install PHP 8.2+ with extensions
  - php8.2-fpm
  - php8.2-mysql (provides pdo_mysql)
  - php8.2-curl
  - php8.2-mbstring
  - php8.2-xml
  - php8.2-zip
  - php8.2-intl
- [ ] Verify PHP extensions: `php -m | grep -E 'pdo_mysql|sodium|intl'`
- [ ] Install MySQL 8.0+
- [ ] Install Composer
- [ ] Install Git

### 3. PHP-FPM Configuration (15 minutes)
- [ ] Create PHP-FPM pool config: `/etc/php/8.2/fpm/pool.d/irmajosh.conf`
```ini
[irmajosh]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-irmajosh.sock
listen.owner = www-data
listen.group = www-data

; Process Manager: ondemand (optimal for 2-user low-traffic app)
; - Spawns workers only when needed
; - Idle workers terminated after pm.process_idle_timeout
; - Low memory footprint
; Alternative: pm = dynamic (for higher traffic)
pm = ondemand

; Maximum workers: 5 (sufficient for 2 concurrent users)
; - Each worker handles 1 request at a time
; - 5 allows bursts (e.g., AJAX requests, asset loading)
; Monitor: cat /var/log/php8.2-fpm.log | grep "max_children"
; If "pm.max_children" warnings appear, increase to 10
pm.max_children = 5

; Idle timeout: 10s (terminate idle workers after 10 seconds)
pm.process_idle_timeout = 10s

; Maximum requests per worker (prevents memory leaks)
pm.max_requests = 500
```
- [ ] Enable PHP OPcache in `/etc/php/8.2/fpm/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=0
opcache.validate_timestamps=0   ; PRODUCTION: requires service reload after code changes
opcache.max_wasted_percentage=10
```
  - **Note:** With `validate_timestamps=0`, you MUST reload PHP-FPM after code changes. The deploy.sh script handles this automatically.
- [ ] Restart PHP-FPM: `systemctl restart php8.2-fpm`
- [ ] Verify OPcache working: `php -i | grep opcache.enable` (should show: opcache.enable => On => On)

### 4. Apache Configuration (45 minutes)
- [ ] Enable required Apache modules:
```bash
a2enmod proxy_fcgi setenvif rewrite headers ssl http2 deflate expires brotli
```
- [ ] Create VirtualHost for HTTPS (port 443) - see detailed config below
- [ ] Create VirtualHost for HTTP redirect (port 80)
- [ ] Test Apache config: `apachectl configtest`
- [ ] Enable site: `a2ensite irmajosh.conf`
- [ ] Reload Apache: `systemctl reload apache2`

### 5. Database Setup (15 minutes)
- [ ] **Verify MySQL server character set defaults:**
  ```sql
  -- Check MySQL server defaults
  SHOW VARIABLES LIKE 'character_set_%';
  SHOW VARIABLES LIKE 'collation%';
  
  -- If not utf8mb4, add to /etc/mysql/my.cnf:
  -- [mysqld]
  -- character-set-server=utf8mb4
  -- collation-server=utf8mb4_unicode_ci
  ```
- [ ] Create MySQL database:
```sql
CREATE DATABASE irmajosh_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
- [ ] Create database user:
```sql
CREATE USER 'irmajosh_app'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,DROP,INDEX ON irmajosh_db.* TO 'irmajosh_app'@'localhost';
FLUSH PRIVILEGES;
```
- [ ] **Verify database charset:**
  ```sql
  SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
  FROM information_schema.SCHEMATA 
  WHERE SCHEMA_NAME = 'irmajosh_db';
  -- Should return: utf8mb4, utf8mb4_unicode_ci
  ```

### 6. Application Deployment (45 minutes)
- [ ] Clone repository: `git clone https://github.com/username/irmajosh.com.git /var/www/irmajosh.com`
- [ ] **Verify composer.lock exists:** `ls -la /var/www/irmajosh.com/composer.lock`
  - **CRITICAL:** composer.lock MUST be committed to git
  - Without composer.lock, production may install different versions than development
- [ ] Install dependencies: `composer install --no-dev --optimize-autoloader --classmap-authoritative`
  - **--no-dev:** Excludes development dependencies (phpunit, etc.)
  - **--optimize-autoloader:** Faster class loading
  - **--classmap-authoritative:** Security - disables file scanning for autoload
- [ ] Validate installation: `composer validate --strict`
- [ ] Verify no git diff in composer.lock: `git diff composer.lock` (should be empty)
- [ ] Copy .env.production.example to .env
- [ ] Configure .env with production values
- [ ] **Verify critical .env security settings:**
  ```bash
  # Verify SESSION_SECURE=true (REQUIRED for __Host- prefix over HTTPS)
  grep "SESSION_SECURE=true" .env || echo "ERROR: SESSION_SECURE must be true in production"
  
  # Verify SESSION_COOKIE_NAME starts with __Host-
  grep "SESSION_COOKIE_NAME=__Host-irmajosh_session" .env || echo "ERROR: Cookie name must use __Host- prefix"
  
  # Verify APP_ENV=production
  grep "APP_ENV=production" .env || echo "ERROR: APP_ENV must be production"
  
  # Verify APP_DEBUG=false
  grep "APP_DEBUG=false" .env || echo "ERROR: APP_DEBUG must be false in production"
  ```
- [ ] Generate APP_SECRET: `php scripts/generate_key.php`
- [ ] **CRITICAL: Backup APP_SECRET to password manager IMMEDIATELY**
  - Label: "irmajosh.com APP_SECRET - [Date]"
  - **WARNING:** APP_SECRET is NOT included in automated backups (backup.sh excludes .env)
  - **WARNING:** Loss of APP_SECRET makes all encrypted tokens unrecoverable
  - **WARNING:** Users will need to re-authenticate with Google if APP_SECRET is lost
  - **DO THIS NOW:** Open your password manager and store the key before proceeding
- [ ] Verify APP_SECRET backed up in password manager (manually check)
- [ ] Set .env permissions: `chmod 640 .env && chown www-data:www-data .env`
- [ ] Set file ownership: `chown -R www-data:www-data /var/www/irmajosh.com`
- [ ] Set directory permissions: `chmod -R 755 storage/`
- [ ] Create storage directories: `mkdir -p storage/logs storage/cache`
- [ ] Create robots.txt:
```
User-agent: *
Disallow: /
```
- [ ] Run initial allowlist setup: `php scripts/bootstrap_whitelist.php your@email.com`
- [ ] **Run pre-flight verification: `php scripts/preflight.php`**
- [ ] **Resolve all pre-flight errors before proceeding**
- [ ] Create pre-migration backup: `bash scripts/backup.sh`
- [ ] Run migrations: `php scripts/migrate.php`
- [ ] Verify migrations: `mysql -u user -p -e "SELECT * FROM irmajosh_db._migrations;"`

### 7. SSL Configuration (15 minutes)
- [ ] Install certbot: `apt install certbot python3-certbot-apache`
- [ ] Obtain certificate: `certbot --apache -d irmajosh.com -d www.irmajosh.com`
- [ ] Verify auto-renewal: `certbot renew --dry-run`
- [ ] Check certificate paths in Apache VirtualHost config

### 8. DNS & Email Configuration (30 minutes)

#### A. Install and Configure Postfix + OpenDKIM

```bash
apt-get update
apt-get install -y postfix opendkim opendkim-tools
```

#### B. OpenDKIM Configuration

**/etc/opendkim.conf:**

```
Syslog                  yes
UMask                   002
Mode                    sv
Canonicalization        relaxed/simple
KeyTable                /etc/opendkim/key.table
SigningTable            /etc/opendkim/signing.table
ExternalIgnoreList      refile:/etc/opendkim/trusted.hosts
InternalHosts           refile:/etc/opendkim/trusted.hosts
Socket                  inet:8891@localhost
```

#### C. Generate DKIM Keys

```bash
mkdir -p /etc/opendkim/keys/irmajosh.com
opendkim-genkey -D /etc/opendkim/keys/irmajosh.com/ -d irmajosh.com -s mail
chown -R opendkim:opendkim /etc/opendkim/keys/irmajosh.com
```

**/etc/opendkim/key.table:**

```
mail._domainkey.irmajosh.com irmajosh.com:mail:/etc/opendkim/keys/irmajosh.com/mail.private
```

**/etc/opendkim/signing.table:**

```
*@irmajosh.com mail._domainkey.irmajosh.com
```

**/etc/opendkim/trusted.hosts:**

```
127.0.0.1
localhost
irmajosh.com
```

#### D. Configure Postfix

**/etc/postfix/main.cf additions:**

```
milter_default_action = accept
milter_protocol = 2
smtpd_milters = inet:localhost:8891
non_smtpd_milters = inet:localhost:8891
```

#### E. Start Services

```bash
systemctl enable --now opendkim
systemctl restart postfix
```

#### F. Configure DNS Records

- [ ] Configure SPF record:
```dns
@ IN TXT "v=spf1 a mx ~all"
```
- [ ] Configure DKIM record (get public key from /etc/opendkim/keys/irmajosh.com/mail.txt):
```dns
mail._domainkey IN TXT "v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY_HERE"
```
- [ ] Configure DMARC record:
```dns
_dmarc IN TXT "v=DMARC1; p=quarantine; rua=mailto:admin@irmajosh.com; pct=100"
```

#### G. Test Email Deliverability

- [ ] Create email test script at `scripts/test_email.php` (see Deployment Scripts section)
- [ ] Test email: `php scripts/test_email.php admin@irmajosh.com`
- [ ] Verify email received (check inbox and spam folder)
- [ ] Test at mail-tester.com for deliverability score (aim for 8+/10)

### 9. Cron Jobs (15 minutes)
- [ ] Configure automated maintenance (see Cron Jobs section below)
- [ ] Verify cron jobs scheduled: `crontab -l`
- [ ] Test backup script manually: `sudo -u www-data bash /var/www/irmajosh.com/scripts/backup.sh`
- [ ] Verify backup created: `ls -lh /var/backups/irmajosh/`

### 10. Log Rotation (10 minutes)
- [ ] Create Apache log rotation config at `/etc/logrotate.d/irmajosh-apache`
- [ ] Create application log rotation config at `/etc/logrotate.d/irmajosh-app`
- [ ] Test log rotation: `sudo logrotate -d /etc/logrotate.d/irmajosh-app`

---

## Apache VirtualHost Configuration

### HTTPS VirtualHost (port 443)

```apache
<VirtualHost *:443>
    ServerName irmajosh.com
    ServerAlias www.irmajosh.com
    DocumentRoot /var/www/irmajosh.com/public_html
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/irmajosh.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/irmajosh.com/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf
    
    # HTTP/2
    Protocols h2 http/1.1
    
    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm-irmajosh.sock|fcgi://localhost"
    </FilesMatch>
    
    # Front Controller (AllowOverride None - no .htaccess)
    <Directory /var/www/irmajosh.com/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,PT,L]
    </Directory>
    
    # Deny Access to Sensitive Directories
    <DirectoryMatch "^/var/www/irmajosh.com/(storage|config|src|vendor|\.git|backups)">
        Require all denied
    </DirectoryMatch>
    
    # STATIC Security Headers (NO CSP - set in PHP)
    <IfModule mod_headers.c>
        # HSTS
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
        
        # X-Frame-Options
        Header always set X-Frame-Options "SAMEORIGIN"
        
        # X-Content-Type-Options
        Header always set X-Content-Type-Options "nosniff"
        
        # X-Robots-Tag
        Header always set X-Robots-Tag "noindex, nofollow"
        
        # Referrer-Policy
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        
        # Permissions-Policy
        Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
        
        # DO NOT SET CSP HERE - it's set in bootstrap.php with per-request nonces
    </IfModule>
    
    # Compression
    <IfModule mod_brotli.c>
        AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json
    </IfModule>
    
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json
    </IfModule>
    
    # Caching for Static Assets
    <IfModule mod_headers.c>
        Header append Vary Accept-Encoding
        
        # Cache-Control for static assets (using cache busting via query string)
        <FilesMatch "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2)$">
            Header set Cache-Control "public, max-age=31536000, immutable"
        </FilesMatch>
    </IfModule>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/irmajosh-error.log
    CustomLog ${APACHE_LOG_DIR}/irmajosh-access.log combined
    
    # Security
    ServerTokens Prod
    ServerSignature Off
</VirtualHost>
```

### HTTP Redirect VirtualHost (port 80)

```apache
<VirtualHost *:80>
    ServerName irmajosh.com
    ServerAlias www.irmajosh.com
    
    # Redirect all HTTP to HTTPS
    RewriteEngine On
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
    
    # Security
    ServerTokens Prod
    ServerSignature Off
</VirtualHost>
```

---

## Deployment Scripts

### deploy.sh

```bash
#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/irmajosh.com"

echo "Starting deployment..."
cd "$APP_DIR"

# Verify we're on main branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "ERROR: Not on main branch (currently on: $CURRENT_BRANCH)"
    echo "Switch to main before deploying: git checkout main"
    exit 1
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "ERROR: Uncommitted changes detected"
    echo "Commit or stash changes before deploying"
    git status
    exit 1
fi

echo "Creating backup..."
bash scripts/backup.sh

echo "Pulling from GitHub..."
git pull --ff-only origin main

echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader --classmap-authoritative
composer validate --no-interaction

echo "Running migrations..."
php scripts/migrate.php

echo "Setting permissions..."
mkdir -p storage/logs storage/cache
chown -R www-data:www-data storage/
chmod -R 755 storage/
chmod 640 .env || true

echo "Clearing app cache..."
rm -f storage/cache/* || true

echo "Reloading services..."
systemctl reload php8.2-fpm
systemctl reload apache2

echo "Deployment complete!"
```

### migrate.php

```php
<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

try {
    $db = db();
    
    // Create _migrations table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Get applied migrations
    $stmt = $db->query("SELECT migration FROM _migrations");
    $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all migration files
    $files = glob(__DIR__ . '/../migrations/*.sql');
    sort($files); // Deterministic order
    
    $newMigrations = 0;
    
    foreach ($files as $file) {
        $migration = basename($file);
        
        // Skip if already applied
        if (in_array($migration, $applied)) {
            echo "Skipping: {$migration} (already applied)\n";
            continue;
        }
        
        echo "Applying: {$migration}...\n";
        
        // Read SQL file
        $sql = file_get_contents($file);
        
        // Execute in transaction
        $db->beginTransaction();
        try {
            $db->exec($sql);
            
            // Record migration
            $stmt = $db->prepare("INSERT INTO _migrations (migration) VALUES (?)");
            $stmt->execute([$migration]);
            
            $db->commit();
            echo "Applied: {$migration}\n";
            $newMigrations++;
        } catch (Exception $e) {
            $db->rollBack();
            echo "ERROR applying {$migration}: " . $e->getMessage() . "\n";
            echo "Migration rolled back. Restore pre-migration backup if needed.\n";
            exit(1);
        }
    }
    
    echo "\nMigrations complete! Applied {$newMigrations} new migration(s).\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
```

### backup.sh

```bash
#!/bin/bash
set -euo pipefail

# Configuration
BACKUP_DIR="/var/backups/irmajosh"
APP_DIR="/var/www/irmajosh.com"
DATE=$(date +%Y%m%d_%H%M%S)
LOCKFILE="/var/lock/irmajosh-backup.lock"
PASSPHRASE_FILE="/root/.config/irmajosh_backup.pass"  # chmod 600, root:root

# Prevent overlapping backups
if [ -e "$LOCKFILE" ]; then
    echo "Backup already running"
    exit 0
fi
touch "$LOCKFILE"

# Cleanup function
cleanup() {
    rm -f "$LOCKFILE"
}
trap cleanup EXIT

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Load database credentials from .env
DB_USER=$(grep DB_USER "$APP_DIR/.env" | cut -d '=' -f2)
DB_PASS=$(grep DB_PASS "$APP_DIR/.env" | cut -d '=' -f2)
DB_NAME=$(grep DB_NAME "$APP_DIR/.env" | cut -d '=' -f2)

echo "Starting backup at $(date)"

# Backup database
echo "Backing up database..."
mysqldump --single-transaction --routines --events --triggers \
    -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip -9 > "$BACKUP_DIR/db-${DATE}.sql.gz"

# Backup storage (exclude .env - stored in password manager)
echo "Backing up storage..."
tar -czf "$BACKUP_DIR/storage-${DATE}.tar.gz" -C "$APP_DIR" storage/ --exclude=.env

# GPG encryption (non-interactive for cron compatibility)
echo "Encrypting backups..."
gpg --batch --yes --passphrase-file "$PASSPHRASE_FILE" --symmetric --cipher-algo AES256 "$BACKUP_DIR/db-${DATE}.sql.gz"
gpg --batch --yes --passphrase-file "$PASSPHRASE_FILE" --symmetric --cipher-algo AES256 "$BACKUP_DIR/storage-${DATE}.tar.gz"

# Remove unencrypted files
rm -f "$BACKUP_DIR/db-${DATE}.sql.gz"
rm -f "$BACKUP_DIR/storage-${DATE}.tar.gz"

# Retention: Keep last 30 days
echo "Pruning old backups (keeping last 30 days)..."
find "$BACKUP_DIR" -name "db-*.gpg" -type f -mtime +30 -delete
find "$BACKUP_DIR" -name "storage-*.gpg" -type f -mtime +30 -delete

echo "Backup complete at $(date)"
echo "Backup location: $BACKUP_DIR"
ls -lh "$BACKUP_DIR" | tail -5
```

**Setup backup encryption passphrase:**

```bash
# Generate strong passphrase
mkdir -p /root/.config
openssl rand -base64 32 > /root/.config/irmajosh_backup.pass
chmod 600 /root/.config/irmajosh_backup.pass
chown root:root /root/.config/irmajosh_backup.pass

# CRITICAL: Store passphrase in password manager
echo "CRITICAL: Copy passphrase to password manager"
cat /root/.config/irmajosh_backup.pass
# Label: "irmajosh.com Backup Encryption Passphrase"
```

### restore_backup.sh

```bash
#!/bin/bash
set -e

if [ $# -ne 2 ]; then
    echo "Usage: $0 <db_backup.sql.gz.gpg> <storage_backup.tar.gz.gpg>"
    exit 1
fi

DB_BACKUP=$1
STORAGE_BACKUP=$2
APP_DIR="/var/www/irmajosh.com"

# Stop Apache
echo "Stopping Apache..."
systemctl stop apache2

# Load database credentials from .env
DB_USER=$(grep DB_USER "$APP_DIR/.env" | cut -d '=' -f2)
DB_PASS=$(grep DB_PASS "$APP_DIR/.env" | cut -d '=' -f2)
DB_NAME=$(grep DB_NAME "$APP_DIR/.env" | cut -d '=' -f2)

# Restore database
echo "Restoring database..."
gpg --decrypt "$DB_BACKUP" | gunzip | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

# Restore storage
echo "Restoring storage..."
gpg --decrypt "$STORAGE_BACKUP" | tar -xzf - -C "$APP_DIR"

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data "$APP_DIR/storage/"
chmod -R 755 "$APP_DIR/storage/"

# Start Apache
echo "Starting Apache..."
systemctl start apache2

echo "Restore complete!"
echo ""
echo "CRITICAL: Manually retrieve APP_SECRET from password manager and update .env"
echo "APP_SECRET is NOT in backups - you MUST have it from password manager"
echo "Test token decryption before going live: php scripts/verify_encryption.php"
```

### preflight.php

```php
<?php
/**
 * Pre-deployment verification script
 * Run before migrations to catch configuration issues early
 */

$errors = [];
$warnings = [];

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = "PHP 8.2+ required, found " . PHP_VERSION;
}

// Check required PHP extensions
$requiredExtensions = ['pdo_mysql', 'sodium', 'intl', 'curl', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

// Check .env exists and is readable
if (!file_exists(__DIR__ . '/../.env')) {
    $errors[] = ".env file not found";
} elseif (!is_readable(__DIR__ . '/../.env')) {
    $errors[] = ".env file not readable";
}

// Check vendor directory exists (composer install ran)
if (!is_dir(__DIR__ . '/../vendor')) {
    $errors[] = "vendor/ directory not found - run 'composer install'";
}

// Check storage directory exists and is writable
if (!is_dir(__DIR__ . '/../storage')) {
    $errors[] = "storage/ directory not found";
} elseif (!is_writable(__DIR__ . '/../storage')) {
    $errors[] = "storage/ directory not writable";
}

// Check database connection (if .env exists)
if (file_exists(__DIR__ . '/../.env')) {
    require __DIR__ . '/../vendor/autoload.php';
    
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        
        // Check required .env variables
        $requiredEnv = ['APP_SECRET', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($requiredEnv as $var) {
            if (empty($_ENV[$var])) {
                $errors[] = "Missing .env variable: {$var}";
            }
        }
        
        // Test database connection
        if (empty($errors)) {
            try {
                $pdo = new PDO(
                    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASS'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                echo "✓ Database connection successful\n";
            } catch (PDOException $e) {
                $errors[] = "Database connection failed: " . $e->getMessage();
            }
        }
        
        // Verify APP_SECRET length (should be 64 hex characters)
        if (!empty($_ENV['APP_SECRET']) && strlen($_ENV['APP_SECRET']) !== 64) {
            $warnings[] = "APP_SECRET should be 64 characters (current: " . strlen($_ENV['APP_SECRET']) . ")";
        }
        
    } catch (Exception $e) {
        $errors[] = "Failed to load .env: " . $e->getMessage();
    }
}

// Output results
echo "\n=== PRE-FLIGHT VERIFICATION ===\n\n";

if (!empty($errors)) {
    echo "ERRORS (must fix before deployment):\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (should review):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✓ All checks passed! Ready for deployment.\n\n";
    exit(0);
} elseif (!empty($errors)) {
    echo "❌ Pre-flight failed. Fix errors above before deploying.\n\n";
    exit(1);
} else {
    echo "⚠ Pre-flight passed with warnings. Review before deploying.\n\n";
    exit(0);
}
```

### test_email.php

```php
<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

if ($argc !== 2) {
    echo "Usage: php test_email.php <recipient@email.com>\n";
    exit(1);
}

$to = $argv[1];

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth = false; // No auth for localhost
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['MAIL_PORT'];
    
    $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($to);
    $mail->Subject = 'IrmaJosh Email Test - ' . date('Y-m-d H:i:s');
    $mail->Body = "This is a test email from IrmaJosh deployment.\n\nIf you received this, SMTP is configured correctly.";
    
    $mail->send();
    echo "✓ Email sent successfully to {$to}\n";
    echo "Check inbox (and spam folder) to verify delivery.\n";
} catch (Exception $e) {
    echo "✗ Email failed: {$mail->ErrorInfo}\n";
    exit(1);
}
```

---

## Log Rotation Configuration

### /etc/logrotate.d/irmajosh-apache

```
/var/log/apache2/irmajosh-access.log /var/log/apache2/irmajosh-error.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    create 640 root adm
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

### /etc/logrotate.d/irmajosh-app

```
/var/www/irmajosh.com/storage/logs/*.log {
    weekly
    rotate 12
    compress
    missingok
    notifempty
    su www-data www-data
    create 640 www-data www-data
}
```

---

## Cron Jobs

```bash
# Edit crontab
crontab -e
```

Add these entries:

```bash
# Daily backup at 2 AM
0 2 * * * /var/www/irmajosh.com/scripts/backup.sh >> /var/www/irmajosh.com/storage/logs/backup.log 2>&1

# Daily session cleanup at 3 AM
0 3 * * * find /var/lib/php/sessions -type f -mtime +1 -delete

# Weekly cache cleanup (Sundays at 4 AM)
0 4 * * 0 find /var/www/irmajosh.com/storage/cache -type f -mtime +7 -delete

# Weekly composer audit (Sundays at 2 AM)
0 2 * * 0 cd /var/www/irmajosh.com && composer audit >> storage/logs/composer-audit.log 2>&1
```

---

## Phase 4 Completion Checklist

### Server Setup
- [ ] Server provisioned with SSH access
- [ ] Firewall configured (UFW: 22, 80, 443)
- [ ] Time synchronization active (chrony)
- [ ] All required packages installed (Apache, PHP, MySQL, Composer, Git)

### PHP-FPM
- [ ] PHP-FPM pool created and configured
- [ ] OPcache enabled and configured
- [ ] PHP extensions verified
- [ ] PHP-FPM service running

### Apache
- [ ] Required modules enabled
- [ ] HTTPS VirtualHost configured with security headers
- [ ] HTTP redirect VirtualHost configured
- [ ] Static asset caching configured
- [ ] Compression enabled (Brotli/gzip)
- [ ] HTTP/2 enabled
- [ ] Log rotation configured
- [ ] Apache config test passes
- [ ] Site enabled and Apache reloaded

### Database
- [ ] Database created with utf8mb4
- [ ] Database user(s) created
- [ ] Permissions granted
- [ ] Database accessible from app

### Application
- [ ] Repository cloned
- [ ] Dependencies installed (composer install)
- [ ] .env configured with production values
- [ ] APP_SECRET generated and backed up to password manager
- [ ] File permissions set correctly
- [ ] robots.txt created
- [ ] Initial allowlist setup completed
- [ ] Migrations run successfully

### SSL
- [ ] Certificate obtained via certbot
- [ ] Auto-renewal verified
- [ ] Certificate paths correct in Apache config

### DNS & Email
- [ ] SPF record configured
- [ ] DKIM key generated and record configured
- [ ] DMARC record configured
- [ ] Email deliverability tested (mail-tester.com)

### Automation
- [ ] Deployment script created and tested
- [ ] Migration script created and tested
- [ ] Backup script created and tested
- [ ] **Backup encryption passphrase generated and stored in password manager**
  - Label: "irmajosh.com Backup Encryption Passphrase"
  - Location: `/root/.config/irmajosh_backup.pass`
  - Minimum 20 characters
  - Document in operations runbook
- [ ] Test backup encryption/decryption cycle
- [ ] Restore script created and tested
- [ ] Pre-flight verification script created
- [ ] Email test script created
- [ ] Cron jobs configured
- [ ] Cron jobs verified

### Log Rotation
- [ ] Apache log rotation configured
- [ ] Application log rotation configured
- [ ] Log rotation tested

### Verification
- [ ] Homepage loads over HTTPS
- [ ] HTTP redirects to HTTPS (301/302)
- [ ] **CSP nonce verification:**
  - [ ] Verify bootstrap.php generates nonce: `$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');`
  - [ ] Verify nonce stored in $GLOBALS (NOT $_SESSION): `$GLOBALS['csp_nonce'] = $nonce;`
  - [ ] Verify CSP header set in bootstrap.php with nonce: `header("Content-Security-Policy: script-src 'self' 'nonce-{$GLOBALS['csp_nonce']}'; ...");`
  - [ ] Test CSP header present: `curl -I https://irmajosh.com | grep Content-Security-Policy`
  - [ ] Verify inline scripts have nonce attribute: `<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce']) ?>">`
  - [ ] Check browser console for CSP violations (should be zero)
- [ ] Security headers present (HSTS, X-Frame-Options, X-Content-Type-Options)
- [ ] No Apache config errors
- [ ] No PHP-FPM errors
- [ ] Database connection works
- [ ] All migrations applied
- [ ] Login with Google works
- [ ] Session persists across requests
- [ ] Calendar API access works
- [ ] APP_SECRET backed up in password manager
- [ ] Backup encryption passphrase backed up in password manager
- [ ] Test email sent and received
- [ ] Email not in spam folder
- [ ] SPF, DKIM, DMARC DNS records configured
- [ ] mail-tester.com score 8+/10

---

## Additional Configuration Files

### .env.production.example

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://irmajosh.com
APP_SECRET=   # php scripts/generate_key.php (store in password manager)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=irmajosh_db
DB_USER=irmajosh_app
DB_PASS=

# Security / Sessions
SESSION_NAME=__Host-ij_sid
SESSION_SECURE=true
SESSION_SAMESITE=Strict
RATE_LIMIT_WINDOW=900
RATE_LIMIT_MAX=100

# Google OAuth (with openid scope)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://irmajosh.com/auth/callback
GOOGLE_SCOPES=openid https://www.googleapis.com/auth/calendar

# Mail (local Postfix)
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=admin@irmajosh.com
MAIL_FROM_NAME="IrmaJosh"

# Allowlist
ALLOWED_EMAILS=irma@example.com,josh@example.com
```

### MySQL Hardening

```sql
-- After install, secure MySQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'STRONG_ROOT_PASSWORD';
DELETE FROM mysql.user WHERE user='';
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
```

### SSH Hardening

Edit `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
```

Then reload SSH:

```bash
systemctl reload sshd
```

---

## Emergency Rollback Procedure

If deployment fails or causes issues, follow these steps:

```bash
# 1. Find latest backup
ls -lt /var/backups/irmajosh/ | head -5

# 2. Restore backup
bash scripts/restore_backup.sh \
  /var/backups/irmajosh/db-YYYYMMDD_HHMMSS.sql.gz.gpg \
  /var/backups/irmajosh/storage-YYYYMMDD_HHMMSS.tar.gz.gpg

# 3. Revert code to previous commit
cd /var/www/irmajosh.com
git reset --hard HEAD~1

# 4. Reinstall dependencies
composer install --no-dev --optimize-autoloader --classmap-authoritative

# 5. Reload services
systemctl reload apache2
systemctl reload php8.2-fpm

# 6. Verify
curl -I https://irmajosh.com
```

---

## Deployment Runbook

Complete step-by-step execution guide for production deployment:

```bash
# === 1. SERVER PREP ===
apt-get update && apt-get install -y \
  chrony apache2 php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring \
  php8.2-xml php8.2-zip php8.2-intl mysql-server composer git \
  certbot python3-certbot-apache postfix opendkim opendkim-tools ufw

# Configure time sync
timedatectl status

# Configure firewall (see Section 1)
ufw default deny incoming
ufw default allow outgoing
ufw limit 22/tcp comment 'Rate-limit SSH'
ufw allow 80,443/tcp
ufw enable

# Configure Apache modules
a2dismod mpm_prefork
a2enmod mpm_event proxy_fcgi setenvif rewrite headers ssl http2 deflate expires
a2enmod brotli 2>/dev/null || echo "mod_brotli not available, using deflate only"

# === 2. PHP-FPM POOL ===
# Create /etc/php/8.2/fpm/pool.d/irmajosh.conf (see Section 3)
systemctl restart php8.2-fpm

# === 3. DATABASE ===
mysql -u root -p
# Run CREATE DATABASE and CREATE USER commands (see Section 5)

# === 4. EMAIL (Postfix + OpenDKIM) ===
# Follow Section 8 setup steps

# === 5. APPLICATION ===
git clone https://github.com/username/irmajosh.com.git /var/www/irmajosh.com
cd /var/www/irmajosh.com

# Verify composer.lock exists
ls -la composer.lock

# Configure .env
cp .env.production.example .env
nano .env  # Fill in secrets

# Generate APP_SECRET
php scripts/generate_key.php
# CRITICAL: Copy to password manager NOW

# Verify .env security settings
grep "SESSION_SECURE=true" .env
grep "APP_ENV=production" .env
grep "APP_DEBUG=false" .env

# Install dependencies
composer install --no-dev --optimize-autoloader --classmap-authoritative
composer validate --strict

# Create storage directories
mkdir -p storage/logs storage/cache

# Set permissions
chmod 640 .env
chown -R www-data:www-data /var/www/irmajosh.com
chmod -R 755 storage/

# Pre-flight check
php scripts/preflight.php

# Run migrations
bash scripts/backup.sh  # Pre-migration backup
php scripts/migrate.php

# === 6. APACHE VHOST ===
# Copy vhost config to /etc/apache2/sites-available/irmajosh.conf
apachectl configtest
a2ensite irmajosh.conf
systemctl reload apache2

# === 7. SSL ===
certbot --apache -d irmajosh.com -d www.irmajosh.com
certbot renew --dry-run

# === 8. LOG ROTATION ===
# Create /etc/logrotate.d/irmajosh-apache (see Log Rotation Configuration)
# Create /etc/logrotate.d/irmajosh-app (see Log Rotation Configuration)
sudo logrotate -d /etc/logrotate.d/irmajosh-app

# === 9. BACKUP ENCRYPTION ===
mkdir -p /root/.config
openssl rand -base64 32 > /root/.config/irmajosh_backup.pass
chmod 600 /root/.config/irmajosh_backup.pass
chown root:root /root/.config/irmajosh_backup.pass
# CRITICAL: Copy passphrase to password manager
cat /root/.config/irmajosh_backup.pass

# === 10. CRON JOBS ===
crontab -e
# Add cron entries (see Cron Jobs section)

# === 11. TEST EMAIL ===
php scripts/test_email.php admin@irmajosh.com

# === 12. VERIFICATION ===
curl -I https://irmajosh.com | grep -E 'HTTP|Content-Security-Policy|Strict-Transport-Security'
# Check browser console for CSP violations (should be zero)
```

---

## Next Steps

✅ **Phase 4 Complete!**

Proceed to **Phase 5: Testing** to:
- Perform comprehensive security testing
- Test all application functionality
- Verify PWA features
- Test backup and recovery procedures

---

## Review Information

**Phase 4 - Combined Review Applied:** October 22, 2025  
**Reviewers:** Claude (GitHub Copilot) + GPT-4  
**Critical Issues Addressed:** 4  
**High-Priority Issues Addressed:** 6  
**Medium-Priority Issues Addressed:** 4  

**Key Improvements:**
- ✅ CSP nonce verification steps with $GLOBALS usage
- ✅ Enhanced APP_SECRET backup warnings
- ✅ Pre-flight verification script (preflight.php)
- ✅ Fixed deploy.sh with proper working directory and error handling
- ✅ Non-interactive GPG encryption for cron compatibility
- ✅ Complete Postfix + OpenDKIM email setup
- ✅ Session security verification steps
- ✅ Database charset verification
- ✅ Composer.lock verification
- ✅ Log rotation configuration
- ✅ Production-optimized OPcache settings
- ✅ Detailed UFW firewall setup
- ✅ Time synchronization configuration for OAuth
- ✅ Emergency rollback procedure
- ✅ Complete deployment runbook

**Remaining Considerations:**
- ⚠️ Off-site backup storage (recommended for production)
- ⚠️ Health check endpoint (nice to have)
- ⚠️ Smoke tests after deployment (nice to have)

---

**PHASE 4 - DEPLOYMENT v2.0 - October 22, 2025**  
**Status:** Production-ready with all Critical and High-priority improvements integrated
