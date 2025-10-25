# PHASE 2: CONFIGURATION

**Estimated Time:** 4-6 hours

**Purpose:** Set up database schema, environment variables, routing, and security configuration

---

## Prerequisites

Before starting Phase 2, ensure Phase 1 is complete:
- ✅ Development environment set up
- ✅ Google OAuth credentials obtained
- ✅ Composer dependencies installed
- ✅ Frontend assets downloaded and committed

### Prerequisites Verification

Verify Phase 1 completion before proceeding:

```bash
# Check PHP version (need 8.2+)
php -v

# Check Composer installation
composer --version

# Check MySQL version (need 8.0+)
mysql --version

# Verify Composer dependencies installed
ls -la vendor/google/apiclient
ls -la vendor/vlucas/phpdotenv
ls -la vendor/phpmailer/phpmailer

# Verify frontend assets downloaded
ls -la public_html/assets/lib/htmx
ls -la public_html/assets/lib/fullcalendar

# Verify Google OAuth credentials obtained
# (Check Google Cloud Console for Client ID)
```

**If any checks fail, return to Phase 1 before proceeding.**

---

## Database Schema Design

### Database Creation

Before running migrations, create the database:

```sql
-- Connect as root or admin user
CREATE DATABASE irmajosh_db 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Create application user
CREATE USER 'irmajosh_user'@'localhost' 
    IDENTIFIED BY '<strong-password>';

-- Grant privileges
GRANT SELECT, INSERT, UPDATE, DELETE 
    ON irmajosh_db.* 
    TO 'irmajosh_user'@'localhost';

FLUSH PRIVILEGES;
```

**Character Set Requirements:**
- Database: `utf8mb4` with `utf8mb4_unicode_ci`
- All tables inherit these settings
- Supports full Unicode (including emojis)

### Test Database Connection

Create `scripts/test_db.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Database connection successful!\n";
    echo "Database: {$_ENV['DB_NAME']}\n";
    echo "Host: {$_ENV['DB_HOST']}\n";
    
    // Check charset
    $stmt = $pdo->query("SELECT @@character_set_database, @@collation_database");
    $result = $stmt->fetch(PDO::FETCH_NUM);
    echo "Charset: {$result[0]}\n";
    echo "Collation: {$result[1]}\n";
    
    if ($result[0] !== 'utf8mb4') {
        echo "⚠️  WARNING: Database charset should be utf8mb4\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

Run after database creation:
```bash
php scripts/test_db.php
```

### Migration Strategy

**IMPORTANT:** This application uses migration tracking WITHOUT "IF NOT EXISTS" clauses.

**Why no IF NOT EXISTS?**
- Deterministic migrations prevent re-run errors
- _migrations table tracks which migrations have been applied
- Clean rollback via restore-from-backup strategy

### Migration File Naming

Use zero-padded sequential numbering:
- `000_create_migrations_table.sql` - FIRST migration (tracking table)
- `001_create_users.sql`
- `002_create_user_tokens.sql`
- `003_create_tasks.sql`
- `004_create_schedule_requests.sql`
- `005_create_schedule_request_slots.sql`
- `006_create_audit_logs.sql`
- `010_alter_timestamps_to_datetime.sql` - Convert TIMESTAMP to DATETIME
- `011_add_fk_to_schedule_requests.sql` - Add accepted_slot_id FK constraint

---

## Migration Files

### 000_create_migrations_table.sql

**Purpose:** Track which migrations have been applied

```sql
CREATE TABLE _migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_applied(applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Self-register this migration for consistency
INSERT INTO _migrations (migration) VALUES ('000_create_migrations_table');
```

**Note:** This migration self-registers to maintain complete audit trail.

### 001_create_users.sql

**Purpose:** Store user accounts

```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_user_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'Google sub claim',
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    locale VARCHAR(10) DEFAULT 'en' COMMENT 'Default from APP_LOCALE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email(email),
    INDEX idx_google_user_id(google_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('001_create_users');
```

**Key Points:**
- `google_user_id` stores the `sub` claim from Google ID token (stable identifier)
- `email` is UNIQUE but NOT the primary identifier (emails can change)
- UNIQUE constraints on both `google_user_id` and `email`
- Default locale matches APP_LOCALE from .env

### 002_create_user_tokens.sql

**Purpose:** Store encrypted OAuth tokens separately from users table

```sql
CREATE TABLE user_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    encrypted_tokens TEXT NOT NULL COMMENT 'Format: version|nonce|ciphertext',
    key_version INT UNSIGNED DEFAULT 1 COMMENT 'For APP_SECRET rotation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id(user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('002_create_user_tokens');
```

**Key Points:**
- Separate table for better normalization
- Encrypted using libsodium with key versioning
- Format: `version|nonce|ciphertext`
- **idx_user_id index is REQUIRED** (was missing in initial design)

### 003_create_tasks.sql

**Purpose:** Store shared and private tasks

```sql
CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'Task owner',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_shared BOOLEAN DEFAULT FALSE COMMENT 'Shared between both users',
    google_event_id VARCHAR(255) DEFAULT NULL COMMENT 'Linked Calendar event',
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATETIME NULL COMMENT 'Stored in UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_created(user_id, created_at),
    INDEX idx_user_google(user_id, google_event_id),
    INDEX idx_status(status),
    INDEX idx_shared(is_shared),
    UNIQUE KEY unique_user_google_event(user_id, google_event_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('003_create_tasks');
```

**Key Points:**
- UNIQUE constraint on `(user_id, google_event_id)` 
- MySQL allows multiple NULL values in UNIQUE constraint (acceptable for private tasks)
- FK constraint with ON DELETE CASCADE for automatic cleanup
- All required indexes included
- **due_date uses DATETIME (not TIMESTAMP) for UTC storage**

### 004_create_schedule_requests.sql

**Purpose:** Store schedule request parent records

```sql
CREATE TABLE schedule_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL COMMENT 'User who created request',
    recipient_id INT UNSIGNED NOT NULL COMMENT 'User who receives request',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    accepted_slot_id INT UNSIGNED DEFAULT NULL COMMENT 'Selected time slot',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_requester(requester_id),
    INDEX idx_recipient(recipient_id),
    INDEX idx_status(status),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('004_create_schedule_requests');
```

**Note:** FK constraint for `accepted_slot_id` added in migration 011 (after slots table exists).

### 005_create_schedule_request_slots.sql

**Purpose:** Store time slot options for schedule requests (child table)

```sql
CREATE TABLE schedule_request_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL COMMENT 'Slot start time in UTC',
    end_at DATETIME NOT NULL COMMENT 'Slot end time in UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request(request_id),
    INDEX idx_times(start_at, end_at),
    FOREIGN KEY (request_id) REFERENCES schedule_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('005_create_schedule_request_slots');
```

**Key Points:**
- Separate table instead of JSON for better querying
- All times stored in UTC using DATETIME (not TIMESTAMP)
- ON DELETE CASCADE ensures cleanup when parent request deleted

### 006_create_audit_logs.sql

**Purpose:** Track security events for auditing

```sql
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL for anonymous events',
    event_type VARCHAR(50) NOT NULL COMMENT 'login, logout, token_refresh, etc.',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
    user_agent TEXT,
    details TEXT COMMENT 'Additional event context (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created(user_id, created_at),
    INDEX idx_event_type(event_type),
    INDEX idx_created(created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('006_create_audit_logs');
```

**Key Points:**
- **idx_user_created index is REQUIRED** (was missing in initial design)
- NULL user_id for anonymous events (pre-login)
- ON DELETE SET NULL preserves logs when user deleted

### Audit Log Event Types

**Standard Event Types:**

**Authentication:**
- `login_success` - User logged in via OAuth
- `login_failure` - OAuth flow failed (invalid state, denied, etc.)
- `logout` - User initiated logout
- `token_refresh` - OAuth token refreshed automatically

**Authorization:**
- `access_denied` - User tried to access forbidden resource
- `email_not_allowed` - Email not in ALLOWED_EMAILS list

**Data Changes:**
- `task_created` - Task created
- `task_updated` - Task updated
- `task_deleted` - Task deleted
- `schedule_request_created` - Schedule request sent
- `schedule_request_accepted` - Schedule request accepted
- `schedule_request_declined` - Schedule request declined

**Security Events:**
- `rate_limit_exceeded` - Rate limit hit
- `csp_violation_high` - Content Security Policy violation (high-severity only)
- `csrf_failure` - CSRF token validation failed

**Example Log Entry (Phase 3 implementation):**
```php
logAuditEvent([
    'user_id' => $userId,
    'event_type' => 'login_success',
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'details' => json_encode(['method' => 'google_oauth'])
]);
```

### 010_alter_timestamps_to_datetime.sql

**Purpose:** Convert TIMESTAMP columns to DATETIME for proper UTC storage

```sql
-- Convert schedule request slots to DATETIME
ALTER TABLE schedule_request_slots
    MODIFY COLUMN start_at DATETIME NOT NULL COMMENT 'Slot start time in UTC',
    MODIFY COLUMN end_at DATETIME NOT NULL COMMENT 'Slot end time in UTC';

-- Convert tasks due_date to DATETIME
ALTER TABLE tasks
    MODIFY COLUMN due_date DATETIME NULL COMMENT 'Stored in UTC';

-- Record migration
INSERT INTO _migrations (migration) VALUES ('010_alter_timestamps_to_datetime');
```

**Why DATETIME instead of TIMESTAMP:**
- TIMESTAMP auto-converts to session timezone (can cause drift)
- DATETIME stores exactly what you give it (better for UTC)
- Application enforces UTC at connection and PHP level

### 011_add_fk_to_schedule_requests.sql

**Purpose:** Add FK constraint and index for accepted_slot_id

```sql
-- Add foreign key constraint and index for accepted_slot_id
ALTER TABLE schedule_requests
    ADD INDEX idx_accepted_slot (accepted_slot_id),
    ADD CONSTRAINT fk_schedule_requests_accepted_slot
        FOREIGN KEY (accepted_slot_id)
        REFERENCES schedule_request_slots(id)
        ON DELETE SET NULL;

-- Record migration
INSERT INTO _migrations (migration) VALUES ('011_add_fk_to_schedule_requests');
```

**Why separate migration:**
- FK requires target table (schedule_request_slots) to exist first
- Better data integrity (accepted_slot must belong to same request)
- ON DELETE SET NULL prevents orphaned references

### Timezone Handling Convention

**Storage:** All DATETIME columns store UTC timestamps  
**Database Session:** Connection sets `time_zone = '+00:00'`  
**PHP Default:** Set `date_default_timezone_set('UTC')` in bootstrap  
**Conversion:** Convert to/from user timezone only at UI layer (FullCalendar, views)

**Example (Phase 3):**
```php
// Insert current time (UTC)
$now = gmdate('Y-m-d H:i:s');
$stmt->execute(['created_at' => $now]);

// Display in user timezone (in views)
$utc = new DateTime($row['created_at'], new DateTimeZone('UTC'));
$userTz = new DateTimeZone($_SESSION['user_timezone'] ?? 'UTC');
$local = $utc->setTimezone($userTz);
echo $local->format('Y-m-d H:i:s');
```

---

## Running Migrations

### Create Migration Runner Script

Create `scripts/migrate.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Connect to database
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Set timezone to UTC
$pdo->exec("SET time_zone = '+00:00'");

echo "🔍 Checking migration status...\n\n";

// Check if migrations table exists
$tableExists = $pdo->query("SHOW TABLES LIKE '_migrations'")->rowCount() > 0;

if (!$tableExists) {
    echo "📝 Running migration 000 (create migrations table)...\n";
    $sql = file_get_contents(__DIR__ . '/../migrations/000_create_migrations_table.sql');
    $pdo->exec($sql);
    echo "✅ Migration system initialized\n\n";
}

// Get applied migrations
$stmt = $pdo->query("SELECT migration FROM _migrations ORDER BY id");
$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "✅ Applied migrations: " . count($applied) . "\n";
foreach ($applied as $migration) {
    echo "   - {$migration}\n";
}
echo "\n";

// Scan migration files
$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files); // Ensure order

$pending = [];
foreach ($files as $file) {
    $migration = basename($file, '.sql');
    
    // Skip if already applied
    if (in_array($migration, $applied)) {
        continue;
    }
    
    $pending[] = $migration;
}

if (empty($pending)) {
    echo "✅ All migrations up to date!\n";
    exit(0);
}

echo "📋 Pending migrations: " . count($pending) . "\n";
foreach ($pending as $migration) {
    echo "   - {$migration}\n";
}
echo "\n";

// Apply pending migrations
foreach ($pending as $migration) {
    echo "⏳ Applying {$migration}...\n";
    
    $file = __DIR__ . "/../migrations/{$migration}.sql";
    $sql = file_get_contents($file);
    
    try {
        $pdo->exec($sql);
        echo "✅ Applied {$migration}\n\n";
    } catch (Exception $e) {
        echo "❌ Failed to apply {$migration}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n✅ All migrations applied successfully!\n";
```

**Run migrations:**
```bash
php scripts/migrate.php
```

**Migration Rollback Strategy**

This project does NOT use down migrations.

**Why?**
- 2-user scale allows backup/restore strategy
- Simpler than maintaining bidirectional migrations
- Lower risk with few users and manual deployments

**Rollback Procedure:**
1. Restore latest GPG-encrypted backup
2. Restart application
3. Verify data integrity
4. Re-apply any lost data manually (if needed)

**When to rollback:**
- Migration breaks production
- Data corruption detected
- Schema change causes application errors

**Backup schedule:**
- **Daily automated**: 2 AM UTC via cron job
- **Pre-deployment manual**: Always backup before migrations
- **Retention**: 30 daily backups + 12 monthly backups
- **Expected volume**: <1MB growth per month (2 users, low activity)

**Full backup procedure documented in Phase 6.**

---

## Environment Configuration

### Generate APP_SECRET

Create `scripts/generate_key.php`:

```php
<?php
// Generate secure 64-character hex string for APP_SECRET
echo bin2hex(random_bytes(32)) . PHP_EOL;
```

Run and save output:
```bash
php scripts/generate_key.php
# Output: a1b2c3d4e5f6... (64 characters)
```

### Create .env File

Copy from .env.example and fill in real values:

```env
# Application
APP_NAME="IrmaJosh Calendar"
APP_ENV=development
APP_URL=http://localhost
APP_LOCALE=en
APP_TIMEZONE=UTC

# Security
APP_SECRET_CURR=<64-char-hex-from-generate-key-script>
# APP_SECRET_PREV=<previous-key-for-rotation>  # Uncomment during key rotation
KEY_ROTATION_WINDOW_DAYS=8

# Database
DB_HOST=localhost
DB_NAME=irmajosh_db
DB_USER=irmajosh_user
DB_PASS=<strong-database-password>
DB_CHARSET=utf8mb4

# Google OAuth
GOOGLE_CLIENT_ID=<your-client-id>.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=<your-client-secret>
GOOGLE_REDIRECT_URI=http://localhost/auth/callback

# OAuth Scopes (space-separated, MUST include openid)
# CRITICAL: 'openid' required for ID token with sub claim
GOOGLE_SCOPES="openid email profile https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events"

# Email Allowlist (comma-separated, exact matches only)
# CRITICAL: Only these emails can authenticate via Google OAuth
ALLOWED_EMAILS="irma@example.com,josh@example.com"

# Session Security
SESSION_SECURE=false  # MUST be true in production (required for __Host- prefix)
SESSION_COOKIE_NAME="__Host-ij_sess"
SESSION_LIFETIME=7200  # 2 hours

# SMTP Email (Development - Gmail with App Password)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=your-email@gmail.com
SMTP_PASS=<16-char-app-password>  # NOT your account password
SMTP_FROM_EMAIL=your-email@gmail.com  # Match SMTP domain in dev
SMTP_FROM_NAME="IrmaJosh Calendar (Dev)"

# Rate Limiting (SELECTIVE - auth endpoints only)
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_WINDOW_SECONDS=900  # 15 minutes
```

### .env.production.example

```env
# Application
APP_NAME="IrmaJosh Calendar"
APP_ENV=production
APP_URL=https://irmajosh.com
APP_LOCALE=en
APP_TIMEZONE=UTC

# Security
APP_SECRET_CURR=<64-char-hex-production-key>
# APP_SECRET_PREV=<previous-key-for-rotation>
KEY_ROTATION_WINDOW_DAYS=8

# Database
DB_HOST=localhost
DB_NAME=irmajosh_db
DB_USER=irmajosh_user
DB_PASS=<strong-production-password>
DB_CHARSET=utf8mb4

# Google OAuth
GOOGLE_CLIENT_ID=<your-prod-client-id>.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=<your-prod-secret>
GOOGLE_REDIRECT_URI=https://irmajosh.com/auth/callback

# OAuth Scopes (CRITICAL: must include 'openid')
GOOGLE_SCOPES="openid email profile https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events"

# Email Allowlist (CRITICAL - production emails)
ALLOWED_EMAILS="irma@example.com,josh@example.com"

# Session Security (CRITICAL for production)
SESSION_SECURE=true  # MUST be true for __Host- prefix and HTTPS
SESSION_COOKIE_NAME="__Host-ij_sess"
SESSION_LIFETIME=7200

# SMTP Email (Production - SendGrid or similar)
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=apikey
SMTP_PASS=<sendgrid-api-key>
SMTP_FROM_EMAIL=noreply@irmajosh.com  # Domain configured with SPF/DKIM
SMTP_FROM_NAME="IrmaJosh Calendar"

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_WINDOW_SECONDS=900
```

### .env.development.example

```env
# Same as main .env example above
# Use for local development with http://localhost
```

### Critical Configuration Requirements

**⚠️ SESSION_SECURE must be true in production**

The application uses `__Host-` prefixed session cookies which require:
- `SESSION_SECURE=true` (Secure flag)
- HTTPS/SSL
- No Domain attribute
- Path=/

Setting `SESSION_SECURE=false` will cause authentication to fail in production.
Only use `false` for local HTTP development.

**⚠️ GOOGLE_SCOPES must include 'openid'**

Without `openid`, Google won't return an ID token, and we can't get the `sub` 
claim (stable user identifier). The auth flow will fail at token verification.

**⚠️ ALLOWED_EMAILS must be configured**

This is the primary access control mechanism. Without it, any Google user 
could authenticate. Only emails in this list can access the application.

### Email Allowlist Configuration

**Purpose:** Restrict authentication to authorized users only

**Configuration in .env:**
```env
ALLOWED_EMAILS="irma@example.com,josh@example.com"
```

**Enforcement Logic (Phase 3 implementation):**
```php
/**
 * Check if email is on the allowlist
 * 
 * @param string $email Email address to check
 * @return bool True if allowed, false otherwise
 */
function isEmailAllowed(string $email): bool {
    $raw = getenv('ALLOWED_EMAILS') ?: '';
    $allowed = array_filter(array_map('trim', explode(',', $raw)));
    
    // Case-insensitive comparison
    $allowedLower = array_map('mb_strtolower', $allowed);
    return in_array(mb_strtolower($email), $allowedLower, true);
}

// Usage in OAuth callback (routes/auth.php)
$email = $idTokenClaims['email'] ?? '';

if (!isEmailAllowed($email)) {
    http_response_code(403);
    error_log("OAuth login denied for: " . $email);
    require __DIR__ . '/../views/errors/403.php';
    exit;
}
```

**Important Notes:**
- Exact matches only (no wildcards)
- Case-insensitive comparison
- Checked AFTER Google OAuth succeeds but BEFORE session creation
- If email not in allowlist, show "403 Forbidden" error

### SMTP Configuration Details

**Development (Gmail with App Password):**

Gmail requires 2-factor authentication and App Passwords for SMTP:

1. Enable 2FA on Gmail account
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use App Password (not account password) in .env

**⚠️ IMPORTANT:** In development, use a from-address that matches SMTP domain
to avoid spam filters. Either:
  A) Use same address as SMTP_USER (your-email@gmail.com), OR
  B) Configure SPF/DKIM for irmajosh.com to authorize Gmail

**Production (Recommended Services):**

| Provider | SMTP Host | Port | TLS | Notes |
|----------|-----------|------|-----|-------|
| SendGrid | smtp.sendgrid.net | 587 | ✓ | 100 emails/day free |
| Mailgun | smtp.mailgun.org | 587 | ✓ | 5,000 emails/month free |
| AWS SES | email-smtp.us-east-1.amazonaws.com | 587 | ✓ | $0.10 per 1,000 emails |

**Why not Gmail for production?**
- Daily sending limits (500 emails/day)
- May flag automated emails as spam
- Not designed for application email
- Account suspension risk

### Key Rotation Strategy (Annual)

**Rotation Cadence:** Annually (or on-demand if APP_SECRET compromised)

**Environment Variables:**

```env
# Current active key for new encryptions
APP_SECRET_CURR=current-64-char-hex-key

# Previous key for decryption during 8-day migration window
# APP_SECRET_PREV=previous-64-char-hex-key

# Rotation window (days to support both keys)
KEY_ROTATION_WINDOW_DAYS=8
```

**Rotation Process (8-Day Window):**

**Day 0 (Preparation):**
1. Generate new `APP_SECRET` using: `php scripts/generate_key.php`
2. Store in password manager with label: `APP_SECRET_v2_YYYY-MM-DD`
3. Do NOT deploy yet

**Day 1 (Start Rotation):**
1. Set `APP_SECRET_CURR` to new key
2. Set `APP_SECRET_PREV` to old key (uncomment line)
3. Deploy to production
4. New tokens encrypted with `key_version=2`
5. Old tokens still decryptable with `key_version=1`

**Days 2-7 (Re-encryption):**

Create `scripts/rotate_token_keys.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Find tokens with old key version
$stmt = $pdo->query("
    SELECT id, user_id, encrypted_tokens, key_version 
    FROM user_tokens 
    WHERE key_version = 1
");

$reEncrypted = 0;

foreach ($stmt as $row) {
    echo "Re-encrypting tokens for user {$row['user_id']}...\n";
    
    // Parse encrypted_tokens: version|nonce|ciphertext
    list($version, $nonceB64, $ciphertext) = explode('|', $row['encrypted_tokens'], 3);
    
    // Decrypt with old key
    $oldKey = hex2bin($_ENV['APP_SECRET_PREV']);
    $nonce = base64_decode($nonceB64);
    $encrypted = base64_decode($ciphertext);
    
    $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $oldKey);
    
    if ($decrypted === false) {
        echo "❌ Failed to decrypt token for user {$row['user_id']}\n";
        continue;
    }
    
    // Re-encrypt with new key
    $newKey = hex2bin($_ENV['APP_SECRET_CURR']);
    $newNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $newEncrypted = sodium_crypto_secretbox($decrypted, $newNonce, $newKey);
    
    // Format: version|nonce|ciphertext
    $newTokenString = '2|' . base64_encode($newNonce) . '|' . base64_encode($newEncrypted);
    
    // Update database
    $updateStmt = $pdo->prepare("
        UPDATE user_tokens 
        SET encrypted_tokens = ?, key_version = 2 
        WHERE id = ?
    ");
    $updateStmt->execute([$newTokenString, $row['id']]);
    
    $reEncrypted++;
    echo "✅ Re-encrypted tokens for user {$row['user_id']}\n";
}

echo "\n✅ Re-encrypted {$reEncrypted} token records\n";
```

Run re-encryption:
```bash
php scripts/rotate_token_keys.php
```

**Day 8 (Cleanup):**
1. Verify all tokens use `key_version=2`:
   ```sql
   SELECT COUNT(*) FROM user_tokens WHERE key_version = 1;
   -- Should return 0
   ```
2. Remove `APP_SECRET_PREV` from `.env` (comment it out)
3. Deploy updated `.env`
4. Archive old key in password manager (marked as "rotated YYYY-MM-DD, DO NOT USE")

**Encryption/Decryption Helpers (Phase 3):**

```php
<?php
/**
 * Encrypt token with current key
 */
function encryptToken(string $token): array {
    $key = hex2bin($_ENV['APP_SECRET_CURR']);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $encrypted = sodium_crypto_secretbox($token, $nonce, $key);
    
    return [
        'encrypted_tokens' => '2|' . base64_encode($nonce) . '|' . base64_encode($encrypted),
        'key_version' => 2
    ];
}

/**
 * Decrypt token with versioned key
 */
function decryptToken(string $encrypted, int $keyVersion): string {
    // Parse: version|nonce|ciphertext
    list($version, $nonceB64, $ciphertext) = explode('|', $encrypted, 3);
    
    // Get appropriate key
    if ($keyVersion === 2 || !isset($_ENV['APP_SECRET_PREV'])) {
        $key = hex2bin($_ENV['APP_SECRET_CURR']);
    } else {
        $key = hex2bin($_ENV['APP_SECRET_PREV']);
    }
    
    $nonce = base64_decode($nonceB64);
    $encryptedData = base64_decode($ciphertext);
    
    $decrypted = sodium_crypto_secretbox_open($encryptedData, $nonce, $key);
    
    if ($decrypted === false) {
        throw new Exception('Failed to decrypt token');
    }
    
    return $decrypted;
}
```

### APP_SECRET Backup Strategy (CRITICAL)

**⚠️ NEVER store .env in application backups**

**Quarterly Backup Procedure:**
1. Open password manager (1Password, Bitwarden, etc.)
2. Create new entry: "irmajosh.com APP_SECRET - [Date]"
3. Copy APP_SECRET from production .env
4. Save to password manager
5. Keep last 2 versions for emergency rollback

**Why this matters:**
- APP_SECRET decrypts user_tokens.encrypted_tokens
- Without it, all stored OAuth tokens are irrecoverable
- Users must re-authenticate if APP_SECRET is lost
- Separate from backups prevents circular dependency

### .env File Permissions

**Understanding File Permissions:**
- `chmod 640` = Owner: read+write (6), Group: read (4), Others: none (0)
- PHP-FPM process must be able to READ the file
- Deployment user may need to WRITE the file

**Recommended Strategy (PHP-FPM as www-data):**

```bash
# Set ownership to www-data user and group
chown www-data:www-data .env

# Owner (www-data) can read/write, group (www-data) can read
chmod 640 .env

# Verify
ls -la .env
# Expected: -rw-r----- 1 www-data www-data
```

**Alternative: Separate Deployment User**

If you deploy as a different user (e.g., `deployer`):

```bash
# deployer owns file, www-data group can read
chown deployer:www-data .env
chmod 640 .env

# Verify deployer can write, www-data can read
ls -la .env
# Expected: -rw-r----- 1 deployer www-data
```

**❌ NEVER:**
- `chmod 644` (world-readable, security risk)
- `chmod 666` (world-writable, critical vulnerability)
- `chmod 600` with PHP-FPM not as owner (can't read file)

### Create .gitignore

Create `.gitignore` in project root:

```
# Environment
.env
.env.local
.env.*.local

# Dependencies
/vendor/

# Storage (logs, cache, rate limits)
/storage/logs/*.log
/storage/cache/*
/storage/rate_limits/*
!storage/logs/.gitkeep
!storage/cache/.gitkeep
!storage/rate_limits/.gitkeep

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Backups
*.sql
*.sql.gpg
backups/

# Composer
# composer.lock  # Uncomment to ignore, but RECOMMENDED to commit for stability
```

**Note on composer.lock:**
- **Recommendation:** COMMIT composer.lock for version stability
- Ensures exact dependency versions across environments
- Critical for production reliability

### Configuration Validation Script

Create `scripts/validate_config.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$errors = [];

// Required variables
$required = [
    'APP_NAME', 'APP_ENV', 'APP_URL', 'APP_SECRET_CURR', 'APP_LOCALE', 'APP_TIMEZONE',
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI', 'GOOGLE_SCOPES',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS',
    'SESSION_SECURE', 'SESSION_COOKIE_NAME',
    'ALLOWED_EMAILS', 'RATE_LIMIT_ENABLED'
];

foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        $errors[] = "Missing required variable: {$var}";
    }
}

// Validate APP_SECRET_CURR length (must be 64 hex chars)
if (isset($_ENV['APP_SECRET_CURR'])) {
    if (strlen($_ENV['APP_SECRET_CURR']) !== 64) {
        $errors[] = "APP_SECRET_CURR must be exactly 64 characters (32 bytes hex-encoded)";
    }
    if (!ctype_xdigit($_ENV['APP_SECRET_CURR'])) {
        $errors[] = "APP_SECRET_CURR must be hexadecimal";
    }
}

// Validate SESSION_SECURE in production
if ($_ENV['APP_ENV'] === 'production' && $_ENV['SESSION_SECURE'] !== 'true') {
    $errors[] = "CRITICAL: SESSION_SECURE must be 'true' in production (required for __Host- cookies)";
}

// Validate HTTPS in production
if ($_ENV['APP_ENV'] === 'production' && !str_starts_with($_ENV['APP_URL'], 'https://')) {
    $errors[] = "CRITICAL: APP_URL must use HTTPS in production (required for Secure cookies)";
}

// Validate OAuth scopes include 'openid'
if (isset($_ENV['GOOGLE_SCOPES'])) {
    if (!str_contains($_ENV['GOOGLE_SCOPES'], 'openid')) {
        $errors[] = "CRITICAL: GOOGLE_SCOPES must include 'openid' for ID token";
    }
}

// Validate ALLOWED_EMAILS is set
if (empty($_ENV['ALLOWED_EMAILS'])) {
    $errors[] = "CRITICAL: ALLOWED_EMAILS must be configured (security requirement)";
}

// Validate timezone
if (!in_array($_ENV['APP_TIMEZONE'] ?? '', timezone_identifiers_list())) {
    $errors[] = "Invalid APP_TIMEZONE: {$_ENV['APP_TIMEZONE']}";
}

// Validate SESSION_COOKIE_NAME uses __Host- prefix in production
if ($_ENV['APP_ENV'] === 'production' && !str_starts_with($_ENV['SESSION_COOKIE_NAME'], '__Host-')) {
    $errors[] = "CRITICAL: SESSION_COOKIE_NAME must use '__Host-' prefix in production";
}

// Report results
if ($errors) {
    echo "❌ Configuration validation failed:\n\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    exit(1);
} else {
    echo "✅ Configuration validation passed!\n";
    echo "Environment: {$_ENV['APP_ENV']}\n";
    echo "App URL: {$_ENV['APP_URL']}\n";
    echo "Allowed Emails: {$_ENV['ALLOWED_EMAILS']}\n";
    echo "Session Secure: {$_ENV['SESSION_SECURE']}\n";
}
```

**Run before deployment:**
```bash
php scripts/validate_config.php
```

---

## Routing Configuration

### Create config/routes.php

```php
<?php
return [
    // Public routes
    'GET /' => 'App\Controllers\AuthController@showLogin',
    'GET /auth/login' => 'App\Controllers\AuthController@redirectToGoogle',
    'GET /auth/callback' => 'App\Controllers\AuthController@handleCallback',
    'POST /auth/logout' => 'App\Controllers\AuthController@logout',
    
    // Authenticated routes
    'GET /dashboard' => 'App\Controllers\DashboardController@index',
    
    // Calendar
    'GET /calendar' => 'App\Controllers\CalendarController@index',
    'POST /calendar/events' => 'App\Controllers\CalendarController@create',
    'PUT /calendar/events/{id}' => 'App\Controllers\CalendarController@update',
    'DELETE /calendar/events/{id}' => 'App\Controllers\CalendarController@delete',
    'POST /calendar/sync' => 'App\Controllers\CalendarController@sync',
    
    // Tasks
    'GET /tasks/shared' => 'App\Controllers\TaskController@shared',
    'GET /tasks/private' => 'App\Controllers\TaskController@private',
    'POST /tasks' => 'App\Controllers\TaskController@create',
    'PUT /tasks/{id}' => 'App\Controllers\TaskController@update',
    'DELETE /tasks/{id}' => 'App\Controllers\TaskController@delete',
    
    // Schedule Requests
    'GET /schedule-requests' => 'App\Controllers\ScheduleController@index',
    'POST /schedule-requests' => 'App\Controllers\ScheduleController@create',
    'POST /schedule-requests/{id}/accept' => 'App\Controllers\ScheduleController@accept',
    'POST /schedule-requests/{id}/decline' => 'App\Controllers\ScheduleController@decline',
    
    // Locale
    'POST /locale' => 'App\Controllers\LocaleController@change',
    
    // PWA
    'GET /manifest.json' => 'App\Controllers\PWAController@manifest',
    'GET /service-worker.js' => 'App\Controllers\PWAController@serviceWorker',
    
    // Health & Security
    'GET /health' => 'App\Controllers\HealthController@check',
    'POST /csp-report' => 'App\Controllers\SecurityController@cspReport',
];
```

### Middleware Pipeline Order

```
Request → Rate Limiting → Authentication → CSRF → Route Handler
```

**Exceptions:**
- `/auth/callback` bypasses CSRF (uses OAuth state parameter instead)
- Rate limiting applied ONLY to: `/auth/login`, `/auth/callback`, `/csp-report`, email endpoints

### Method Override

**Restriction:** Only works on POST requests with CSRF protection

```html
<form method="POST" action="/tasks/123">
    <?= csrfField() ?>
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete Task</button>
</form>
```

---

## Security Configuration

### CSRF Protection

**Implementation:** Support headers, POST data, and JSON bodies

```php
// Check in this order:
1. X-CSRF-Token header
2. POST data: _token field
3. JSON body: _token property

// Validation:
hash_equals($_SESSION['csrf_token'], $providedToken)
```

### OAuth State Parameter

**Generation (before redirect):**
```php
$_SESSION['oauth_state'] = bin2hex(random_bytes(32)); // 64-char hex
$_SESSION['oauth_state_expiry'] = time() + 600; // 10 minutes
```

**Validation (in callback):**
```php
if (!isset($_SESSION['oauth_state']) || 
    !isset($_GET['state']) ||
    time() > $_SESSION['oauth_state_expiry']) {
    throw new Exception('Invalid state');
}

if (!hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    throw new Exception('State mismatch');
}

// Clear after use
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_expiry']);
```

### Rate Limiting (SELECTIVE)

**Apply ONLY to these endpoints:**
- `/auth/login`
- `/auth/callback`
- `/csp-report`
- Email-sending endpoints (schedule request notifications)

**Do NOT apply to:**
- Regular authenticated routes
- Dashboard, calendar, tasks

**Configuration:** 100 requests per 15 minutes per IP

**Implementation Strategy (Phase 3):**

**Storage:** File-based rate limiting (simplest for 2-user scale)

Create `src/RateLimit.php`:

```php
<?php
class RateLimit {
    private string $storageDir;
    
    public function __construct() {
        $this->storageDir = __DIR__ . '/../storage/rate_limits';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }
    
    public function check(string $key, int $maxAttempts, int $windowSeconds): bool {
        $ip = $_SERVER['REMOTE_ADDR'];
        $file = $this->storageDir . '/' . md5($key . '_' . $ip) . '.json';
        
        $now = time();
        $data = ['attempts' => [], 'window_start' => $now];
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        
        // Remove expired attempts
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });
        
        // Check limit
        if (count($data['attempts']) >= $maxAttempts) {
            return false; // Rate limit exceeded
        }
        
        // Add current attempt
        $data['attempts'][] = $now;
        
        // Save
        file_put_contents($file, json_encode($data));
        
        return true; // Allowed
    }
}
```

**Usage:**
```php
// In auth/login/index.php
$rateLimit = new RateLimit();

if (!$rateLimit->check('auth_login', 100, 900)) {
    http_response_code(429);
    require __DIR__ . '/../../views/errors/429.php';
    exit;
}
```

**Cleanup cron job:**
```bash
# Clean up expired rate limit files daily
0 3 * * * find /var/www/irmajosh.com/storage/rate_limits -name "*.json" -mtime +1 -delete
```

**Why file-based (not APCu or database):**
- ✅ Simple, no dependencies
- ✅ Persists across PHP-FPM restarts
- ✅ Easy to debug
- ✅ Sufficient for 2-user scale

### Content Security Policy (CSP)

**⚠️ CRITICAL: CSP MUST be set in PHP, NOT Apache**

**Why?** Nonces are generated per-request and must be dynamic.

**Implementation in bootstrap.php:**
```php
// Generate nonce (request-scoped, NOT in session)
$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

// Build CSP header
$csp = "default-src 'self'; " .
       "script-src 'self' 'nonce-{$nonce}'; " .
       "style-src 'self' 'nonce-{$nonce}'; " .
       "img-src 'self' data:; " .
       "font-src 'self'; " .
       "worker-src 'self'; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none'; " .
       "form-action 'self'; " .
       "base-uri 'none'; " .
       "report-uri /csp-report; " .
       "report-to csp-endpoint";

header("Content-Security-Policy: {$csp}");

// Modern reporting (2023+) - Triple compatibility
header('Reporting-Endpoints: csp-endpoint="/csp-report"');

// Transition reporting (2020-2023)
header('Report-To: {"group":"csp-endpoint","max_age":86400,"endpoints":[{"url":"/csp-report"}]}');

// Store for views (CRITICAL: Use $GLOBALS, not $_SESSION)
$GLOBALS['csp_nonce'] = $nonce;
```

### CSP Nonce Usage in Views

**In view templates:**
```php
<!-- Inline scripts -->
<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'], ENT_QUOTES) ?>">
    console.log('This script will execute');
</script>

<!-- Inline styles -->
<style nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'], ENT_QUOTES) ?>">
    .custom { color: blue; }
</style>

<!-- External scripts (self-hosted, no nonce needed) -->
<script src="/assets/js/htmx.min.js"></script>
```

**Common Mistakes:**
- ❌ Using `$_SESSION['csp_nonce']` (race conditions with parallel requests)
- ❌ Hardcoding nonces (defeats CSP protection)
- ❌ Forgetting to escape nonce value in HTML attributes
- ✅ Always use `$GLOBALS['csp_nonce']` with htmlspecialchars()

### CSP Violation Handling

**Storage Strategy: Both files + database**

**File Storage (ALL violations):**
```php
// storage/logs/csp-YYYY-MM-DD.log
// Includes development noise, legitimate violations during testing
// Retention: 90 days
```

**Database Storage (HIGH-SEVERITY only):**
```php
// audit_logs table with event_type='csp_violation_high'
// Only violations indicating potential attacks
// Retention: Permanent (or 2 years per compliance)
```

**High-Severity Criteria:**
- Inline script/style injection attempts
- External domain script loads (not from APP_URL)
- eval() usage attempts
- Repeated violations from same IP (>10 in 5 minutes)

**Implementation (Phase 3):**
```php
<?php
// routes/csp.php

// CSP reports are POST JSON, no CSRF token (exempted)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Parse CSP violation report
$raw = file_get_contents('php://input');
$report = json_decode($raw, true);

// Log to file (all violations)
$logFile = __DIR__ . '/../storage/logs/csp-' . date('Y-m-d') . '.log';
error_log('[CSP-REPORT] ' . $raw . "\n", 3, $logFile);

// Check if high-severity
$violation = $report['csp-report'] ?? [];

if (isHighSeverityViolation($violation)) {
    // Log to audit_logs table
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, event_type, ip_address, user_agent, details)
        VALUES (?, 'csp_violation_high', ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $raw
    ]);
}

http_response_code(204); // No content

function isHighSeverityViolation(array $violation): bool {
    $directive = $violation['violated-directive'] ?? '';
    $blockedUri = $violation['blocked-uri'] ?? '';
    
    // Inline script/style injection
    if (str_contains($directive, 'script-src') && $blockedUri === 'inline') {
        return true;
    }
    
    // External domain loads
    $appUrl = parse_url($_ENV['APP_URL'], PHP_URL_HOST);
    if (!empty($blockedUri) && !str_contains($blockedUri, $appUrl)) {
        return true;
    }
    
    return false;
}
```

**Cleanup:**
```bash
# Cron job to clean old CSP logs
0 3 * * * find /var/www/irmajosh.com/storage/logs/csp-*.log -mtime +90 -delete
```

### CSRF Protection Exemptions

**Endpoints that bypass CSRF token validation:**

1. **`POST /auth/callback`** - OAuth callback (validates `state` parameter instead)
2. **`POST /csp-report`** - CSP violation reports (JSON, rate-limited)
3. **`GET` requests** - All GET requests (safe methods don't need CSRF)

**Implementation (Phase 3):**

```php
function requireCsrfToken(): void {
    $exemptPaths = ['/auth/callback', '/csp-report'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Exempt specific paths
    if (in_array($path, $exemptPaths)) {
        return;
    }
    
    // Exempt GET requests (safe methods)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;
    }
    
    // Validate CSRF token
    validateCsrfToken();
}
```

### PII Redaction

**Required for logs to prevent sensitive data leakage:**

```php
function redactPII(string $message): string {
    $patterns = [
        // Email: keep domain, hide username
        '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/' => '***@$2',
        
        // OAuth tokens
        '/"(access_token|refresh_token|id_token)"\s*:\s*"[^"]+"/' => '"$1":"***REDACTED***"',
        
        // APP_SECRET
        '/APP_SECRET(_CURR|_PREV)?\s*=\s*[^\s]+/' => 'APP_SECRET$1=***REDACTED***',
        
        // IP addresses (GDPR compliance)
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '***.***.***.***.***',
        '/\b(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}\b/' => '****:****:****:****',
        
        // Session IDs
        '/PHPSESSID=[a-zA-Z0-9]+/' => 'PHPSESSID=***REDACTED***',
        '/__Host-[a-zA-Z0-9_-]+=[a-zA-Z0-9]+/' => '__Host-***=***REDACTED***',
        
        // CSRF tokens
        '/_token=[a-zA-Z0-9]+/' => '_token=***REDACTED***',
        '/csrf_token["\']?\s*:\s*["\'][^"\']+["\']/' => 'csrf_token:"***REDACTED***"',
    ];
    
    return preg_replace(array_keys($patterns), array_values($patterns), $message);
}
```

**Apply to:**
- `logError()` function
- Audit log inserts
- Error log messages

**PII Redaction Policy:**
- Apply to ALL log messages (error logs, audit logs, debug logs)
- Redact before writing to files (not at display time)
- Balance security (redact PII) vs debugging (keep useful info)
- IP addresses redacted for GDPR compliance
- User IDs kept in audit logs (authorized access) but redacted in error logs

### robots.txt

Create `public_html/robots.txt`:

```
User-agent: *
Disallow: /
```

**Note:** Fixed syntax (no quotes, proper spacing)

### No-Index Policy (Defense-in-Depth)

**Triple Defense Strategy:**

**1. robots.txt** (Cooperative crawlers)
```
User-agent: *
Disallow: /
```

**2. X-Robots-Tag HTTP Header** (All crawlers)

Add to `bootstrap.php`:
```php
// Send X-Robots-Tag header on ALL responses
header('X-Robots-Tag: noindex, nofollow', false);
```

**3. Meta Tag in HTML** (HTML-based detection)

Add to base layout (`views/layouts/base.php` in Phase 3):
```html
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['locale'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- ... -->
</head>
```

**Why triple defense:**
- robots.txt: Standard method, but can be ignored
- X-Robots-Tag: HTTP-level enforcement
- Meta tag: HTML-level enforcement
- Together provides robust no-indexing policy

---

## Translation System

### Create locales/en/messages.php

```php
<?php
return [
    'app.name' => 'IrmaJosh Calendar',
    'auth.login' => 'Login with Google',
    'auth.logout' => 'Logout',
    'dashboard.title' => 'Dashboard',
    'calendar.title' => 'Calendar',
    'tasks.shared' => 'Shared Tasks',
    'tasks.private' => 'Private Tasks',
    'tasks.create' => 'Create Task',
    'schedule.title' => 'Schedule Requests',
    'common.save' => 'Save',
    'common.cancel' => 'Cancel',
    'common.delete' => 'Delete',
    'errors.403' => 'Forbidden',
    'errors.404' => 'Not Found',
    'errors.500' => 'Server Error',
];
```

### Create locales/id/messages.php

```php
<?php
return [
    'app.name' => 'Kalender IrmaJosh',
    'auth.login' => 'Masuk dengan Google',
    'auth.logout' => 'Keluar',
    'dashboard.title' => 'Dasbor',
    'calendar.title' => 'Kalender',
    'tasks.shared' => 'Tugas Bersama',
    'tasks.private' => 'Tugas Pribadi',
    'tasks.create' => 'Buat Tugas',
    'schedule.title' => 'Permintaan Jadwal',
    'common.save' => 'Simpan',
    'common.cancel' => 'Batal',
    'common.delete' => 'Hapus',
    'errors.403' => 'Terlarang',
    'errors.404' => 'Tidak Ditemukan',
    'errors.500' => 'Kesalahan Server',
];
```

### Translation Helper Function

**Implementation in helpers.php:**

```php
/**
 * Translation helper with XSS protection
 * 
 * @param string $key Translation key
 * @param array $params Replacement parameters (will be escaped)
 * @param string|null $forceLocale Override session locale
 * @return string Translated and escaped string
 */
function __(string $key, array $params = [], ?string $forceLocale = null): string {
    static $translations = [];
    
    $locale = $forceLocale ?? $_SESSION['locale'] ?? $_ENV['APP_LOCALE'] ?? 'en';
    
    // Load translations for this locale if not cached
    if (!isset($translations[$locale])) {
        $file = __DIR__ . "/../locales/{$locale}/messages.php";
        
        if (!file_exists($file)) {
            $locale = 'en'; // Fallback
            $file = __DIR__ . "/../locales/en/messages.php";
        }
        
        $translations[$locale] = require $file;
    }
    
    $value = $translations[$locale][$key] ?? $key; // Fallback to key if missing
    
    // Replace parameters with HTML-escaped values
    foreach ($params as $paramKey => $paramValue) {
        $escaped = htmlspecialchars((string)$paramValue, ENT_QUOTES, 'UTF-8');
        $value = str_replace(":{$paramKey}", $escaped, $value);
    }
    
    return $value;
}
```

**Usage in views:**
```php
<h1><?= __('dashboard.title') ?></h1>
<button><?= __('common.save') ?></button>

<!-- With parameters (automatically escaped) -->
<p><?= __('welcome_message', ['name' => $userName]) ?></p>
<span><?= __('task_count', ['count' => $taskCount]) ?></span>
```

**Translation Security:**

**XSS Protection:** All parameter values passed to `__()` are automatically HTML-escaped.

**Trusted Translation Strings:** Translation keys/values in `locales/*/messages.php` are considered trusted (developer-controlled). Only user-provided parameter values are escaped.

**Raw HTML in Translations:** If you need HTML in translations (rare), use a separate helper or mark as safe explicitly.

**Locale Switching Behavior:**
- Translations are cached per-locale in static variable
- Changing `$_SESSION['locale']` takes effect on NEXT request
- To force immediate translation in specific locale: `__('key', [], 'id')`
- Current request will use whatever locale was set at start

### Translation Validation Script (Optional)

Create `scripts/validate_translations.php` to find missing keys:

```php
<?php
$en = require __DIR__ . '/../locales/en/messages.php';
$id = require __DIR__ . '/../locales/id/messages.php';

$missing_in_id = array_diff_key($en, $id);
$missing_in_en = array_diff_key($id, $en);

if ($missing_in_id) {
    echo "⚠️  Keys in EN but missing in ID:\n";
    foreach (array_keys($missing_in_id) as $key) {
        echo "   - {$key}\n";
    }
}

if ($missing_in_en) {
    echo "⚠️  Keys in ID but missing in EN:\n";
    foreach (array_keys($missing_in_en) as $key) {
        echo "   - {$key}\n";
    }
}

if (!$missing_in_id && !$missing_in_en) {
    echo "✅ All translation keys are synchronized!\n";
}
```

Run before deployment:
```bash
php scripts/validate_translations.php
```

---

## Phase 2 Completion Checklist

Before moving to Phase 3, verify:

**Database:**
- [ ] Database `irmajosh_db` created with utf8mb4 charset
- [ ] Database user `irmajosh_user` created with privileges
- [ ] Database connection test script created (`scripts/test_db.php`)
- [ ] Migration file `000_create_migrations_table.sql` created with self-registration
- [ ] Migration file `001_create_users.sql` created (no IF NOT EXISTS)
- [ ] Migration file `002_create_user_tokens.sql` created with idx_user_id
- [ ] Migration file `003_create_tasks.sql` created with DATETIME due_date
- [ ] Migration file `004_create_schedule_requests.sql` created
- [ ] Migration file `005_create_schedule_request_slots.sql` created with DATETIME columns
- [ ] Migration file `006_create_audit_logs.sql` created with idx_user_created
- [ ] Migration file `010_alter_timestamps_to_datetime.sql` created
- [ ] Migration file `011_add_fk_to_schedule_requests.sql` created
- [ ] All migrations use utf8mb4 charset and utf8mb4_unicode_ci collation
- [ ] All required indexes documented and included
- [ ] FK constraints with ON DELETE CASCADE/SET NULL configured
- [ ] Migration runner script created (`scripts/migrate.php`)
- [ ] All migrations tested successfully

**Environment:**
- [ ] `scripts/generate_key.php` created
- [ ] APP_SECRET_CURR generated (64-char hex) and saved
- [ ] `.env` file created with all real values
- [ ] `.env.production.example` created
- [ ] `.env.development.example` created
- [ ] `APP_LOCALE` configured (default: en)
- [ ] `APP_TIMEZONE` set to UTC
- [ ] `GOOGLE_SCOPES` includes `openid` (CRITICAL)
- [ ] `ALLOWED_EMAILS` configured with both user email addresses (CRITICAL)
- [ ] `SESSION_SECURE=true` in production .env (CRITICAL)
- [ ] `SESSION_COOKIE_NAME` set to `__Host-ij_sess`
- [ ] SMTP configuration complete (Gmail dev, SendGrid production)
- [ ] Rate limiting configuration complete
- [ ] **APP_SECRET_CURR backed up to password manager**
- [ ] .env permissions set (640 with appropriate ownership)
- [ ] `.gitignore` created
- [ ] Configuration validation script created (`scripts/validate_config.php`)
- [ ] Configuration validation passes

**Security:**
- [ ] Email allowlist enforcement plan documented
- [ ] Session cookie hardening configured (`__Host-`, Secure, HttpOnly, SameSite=Strict)
- [ ] CSP nonce generation strategy understood
- [ ] CSP reporting uses triple compatibility (Reporting-Endpoints, Report-To, report-uri)
- [ ] CSP violation handling strategy documented
- [ ] `/csp-report` route plan documented
- [ ] CSRF exemptions documented (`/auth/callback`, `/csp-report`)
- [ ] OAuth state parameter validation understood
- [ ] Rate limiting implementation strategy documented
- [ ] PII redaction patterns complete (email, tokens, IPs, session IDs)
- [ ] No-index policy: `robots.txt` + `X-Robots-Tag` + meta tag

**Configuration:**
- [ ] `config/routes.php` structure understood
- [ ] Middleware pipeline order documented
- [ ] Method override restriction documented
- [ ] Database connection timezone strategy (UTC) documented
- [ ] Timezone handling convention documented

**Translation:**
- [ ] `locales/en/messages.php` created
- [ ] `locales/id/messages.php` created
- [ ] All common translation keys included
- [ ] Translation helper with XSS protection understood
- [ ] Fallback to English configured
- [ ] Translation validation script created (`scripts/validate_translations.php`)

**Key Rotation:**
- [ ] Key rotation playbook documented
- [ ] `APP_SECRET_CURR` and `APP_SECRET_PREV` support understood
- [ ] Re-encryption script plan documented (`scripts/rotate_token_keys.php`)
- [ ] Annual rotation scheduled (calendar reminder for next year)

**Scripts Created:**
- [ ] `scripts/generate_key.php` - Generate APP_SECRET
- [ ] `scripts/test_db.php` - Test database connection
- [ ] `scripts/migrate.php` - Run migrations
- [ ] `scripts/validate_config.php` - Validate environment configuration
- [ ] `scripts/validate_translations.php` - Check translation completeness
- [ ] `scripts/rotate_token_keys.php` - Key rotation (documented for Phase 6)

**Final Validation:**
- [ ] All scripts tested and working
- [ ] Database migrations run successfully
- [ ] Configuration validation passes
- [ ] Translation validation passes
- [ ] All critical issues from reviews addressed
- [ ] All high-priority issues from reviews addressed

---

## Next Steps

✅ **Phase 2 Complete!**

Proceed to **Phase 3: Code Implementation** to:
- Build MVC structure (Controllers, Models, Services)
- Implement 25+ helper functions
- Create view templates with CSP nonce support
- Build frontend assets (CSS, JavaScript, PWA)
- Create utility scripts

---

## Revision History

**v2.0 - October 22, 2025**
- ✅ Added database creation instructions with charset requirements
- ✅ Added migration execution script and documentation
- ✅ Added migrations 010 (DATETIME conversion) and 011 (FK constraint)
- ✅ Added timezone handling convention (UTC enforcement)
- ✅ Added comprehensive .env configuration with all security variables
- ✅ Added email allowlist configuration and enforcement
- ✅ Added OAuth scopes configuration (including openid requirement)
- ✅ Added session cookie hardening details (__Host- prefix)
- ✅ Added key rotation playbook with 8-day dual-key process
- ✅ Added SMTP configuration details (App Password, production services)
- ✅ Added rate limiting implementation strategy
- ✅ Added CSP nonce usage documentation for views
- ✅ Added CSP violation handling (file + database storage)
- ✅ Added CSRF exemption documentation
- ✅ Added triple CSP reporting compatibility
- ✅ Added enhanced PII redaction (IPs, session IDs, CSRF tokens)
- ✅ Added no-index policy (triple defense)
- ✅ Added translation XSS protection
- ✅ Added .gitignore configuration
- ✅ Added configuration validation script
- ✅ Added translation validation script
- ✅ Added database connection test script
- ✅ Added prerequisites verification commands
- ✅ Added audit log event types specification
- ✅ Updated time estimate to 4-6 hours (more realistic)
- ✅ Addressed all critical and high-priority issues from both reviews

**v1.0 - October 22, 2025**
- Initial Phase 2 configuration document

---

**PHASE 2 - CONFIGURATION v2.0 - October 22, 2025**  
**Status:** ✅ Ready for implementation - All critical issues addressed
