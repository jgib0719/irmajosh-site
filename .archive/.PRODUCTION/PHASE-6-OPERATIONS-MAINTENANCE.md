# PHASE 6: OPERATIONS & MAINTENANCE

**Estimated Time:** 12-15 hours (initial setup)
**Review Grade:** A (Combined GPT + Claude)
**Last Updated:** October 22, 2025

**Purpose:** Set up ongoing operations, monitoring, maintenance procedures, and recovery documentation

---

## Overview

Establish long-term operational procedures:
- Automated maintenance tasks
- Monitoring and alerting (self-hosted)
- Maintenance schedules
- Recovery procedures
- Troubleshooting guides
- Security incident response
- Operational policies

**Critical:** Document all procedures and create runbooks for emergency situations.

**Key Improvements from Review:**
- Self-hosted uptime monitoring (no SaaS dependencies)
- Non-interactive backup verification
- Enhanced security verification procedures
- Comprehensive operational policies
- Email service configuration
- Backup retention policies
- Performance baselines

---

## Table of Contents

1. [Architecture Decisions](#architecture-decisions)
2. [Email Service Setup](#email-service-setup)
3. [Automated Maintenance Setup](#automated-maintenance-setup)
4. [Monitoring and Alerting](#monitoring-and-alerting)
5. [Backup Verification](#backup-verification)
6. [APP_SECRET Management](#app_secret-management)
7. [Recovery Procedures](#recovery-procedures)
8. [Troubleshooting Guides](#troubleshooting-guides)
9. [Security Incident Response](#security-incident-response)
10. [Operational Policies](#operational-policies)
11. [Maintenance Schedule Summary](#maintenance-schedule-summary)
12. [Quick Command Reference](#quick-command-reference)
13. [Phase 6 Completion Checklist](#phase-6-completion-checklist)

---

## Architecture Decisions

### Single-Server Architecture

**Explicit Statement:**

> **Architecture:** Single-server deployment. No failover/standby server.
> 
> **Acceptable Downtime:** For a 2-user personal application, 8-hour RTO is acceptable. Server failure is rare, and both users can tolerate downtime during recovery.
> 
> **Cost-benefit:** High availability would require 2+ servers, load balancer, and database replication (~$200+/month). For 2 users, this is not justified.
>
> **Mitigation:** Daily backups (automated), quarterly backup restoration tests, cloud provider snapshots (if available).

### Google OAuth Client Secret Rotation

**Policy:** Rotate only during security incidents (no proactive rotation)

**When to Rotate:**
- Client secret leaked/exposed
- `.env` file compromised
- Security audit recommends it
- Google notifies suspicious activity

**Procedure:**
```bash
1. Generate new secret in Google Cloud Console:
   - APIs & Services → Credentials → OAuth 2.0 Client
   - Click Edit → Reset Secret → Copy new secret

2. Update .env: GOOGLE_CLIENT_SECRET=new_secret_here
3. Update password manager entry
4. Restart PHP-FPM: systemctl restart php8.2-fpm
5. Notify both users: "Please log out and log back in"
6. Verify both users can authenticate
7. Document incident and reason for rotation
```

### Google Calendar API Dependency

**Decision:** No degraded mode. Accept dependency on Google.

**Policy:**
- Google Calendar API uptime: ~99.9%
- 2-user personal app: Downtime acceptable during Google outages
- **No fallback mode:** Complexity not justified for 2 users
- **No queuing:** Events created during outage are lost

**Response to Outage:**
1. **Detection:** Users report "Calendar not loading"
2. **Verify it's Google:** `curl -I https://www.googleapis.com/calendar/v3/users/me/calendarList`
3. **If Google outage confirmed:** Wait for Google to resolve, notify other user
4. **If our issue:** Follow normal troubleshooting

---

## Email Service Setup

### Postfix with Gmail Relay

**Installation:**
```bash
# Install Postfix
apt-get install postfix mailutils

# Configure as satellite (relay via Gmail)
# In /etc/postfix/main.cf:
relayhost = [smtp.gmail.com]:587
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_tls_security_level = encrypt

# Store credentials
echo "[smtp.gmail.com]:587 youremail@gmail.com:app-password" > /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd*
systemctl restart postfix
```

**Add to `.env`:**
```
MAIL_FROM=noreply@irmajosh.com
MAIL_FROM_NAME=IrmaJosh
ADMIN_EMAIL=admin@irmajosh.com
```

**Test email delivery:**
```bash
echo "Test email from IrmaJosh" | mail -s "Test Subject" admin@irmajosh.com
```

---

## Implementation Order

### 1. Automated Maintenance Setup (1-2 hours)

#### Cron Jobs (Already configured in Phase 4)
Verify all cron jobs are active:

```bash
crontab -l
```

Expected output:
```bash
# Daily backup at 2 AM
0 2 * * * /var/www/irmajosh.com/scripts/backup.sh

# Daily session cleanup at 3 AM
0 3 * * * find /var/lib/php/sessions -type f -mtime +1 -delete

# Daily backup retention cleanup at 3:30 AM
30 3 * * * find /var/backups/irmajosh -name "db-*.gpg" -type f -mtime +14 ! -name "*-01-*" ! -name "*-Sunday-*" -delete
30 3 * * * find /var/backups/irmajosh -name "storage-*.gpg" -type f -mtime +14 ! -name "*-01-*" ! -name "*-Sunday-*" -delete

# Weekly cache cleanup (Sundays at 4 AM)
0 4 * * 0 find /var/www/irmajosh.com/storage/cache -type f -mtime +7 -delete

# Weekly composer audit (Sundays at 2 AM)
0 2 * * 0 cd /var/www/irmajosh.com && composer audit >> storage/logs/composer-audit.log 2>&1

# Weekly backup cleanup (Sundays 3:45 AM) - Keep 8 weekly backups
45 3 * * 0 find /var/backups/irmajosh -name "*-Sunday-*.gpg" -type f -mtime +56 ! -name "*-01-*" -delete

# Monthly backup cleanup (1st of month, 4 AM) - Keep 12 monthly backups
0 4 1 * * find /var/backups/irmajosh -name "*-01-*.gpg" -type f -mtime +365 -delete

# Daily disk space check (5 AM)
0 5 * * * df -h / | awk 'NR==2 {if ($(NF-1)+0 > 80) print "WARNING: Disk usage: " $(NF-1)}' | grep "WARNING" | mail -s "[IrmaJosh] Disk Space Alert" admin@irmajosh.com

# Weekly oversized log check (Sundays 6 AM)
0 6 * * 0 find /var/www/irmajosh.com/storage/logs -type f -name "*.log" -size +50M -printf "%p %k KB\n" | mail -s "[IrmaJosh] Oversized logs report" admin@irmajosh.com

# Daily rate limit report (7 AM)
0 7 * * * grep "rate_limit_exceeded" /var/www/irmajosh.com/storage/logs/security.log | grep "$(date +%Y-%m-%d)" | wc -l | xargs -I {} echo "Rate limit hits yesterday: {}" | mail -s "IrmaJosh Rate Limit Report" admin@irmajosh.com

# Weekly disk usage report (Sundays 7 AM)
0 7 * * 0 du -sh /var/www/irmajosh.com /var/backups/irmajosh /var/log | mail -s "[IrmaJosh] Weekly Disk Usage Report" admin@irmajosh.com
```

#### Backup Retention Policy

**Retention Schedule:**
- Daily backups: Keep 14 days
- Weekly backups (Sundays): Keep 8 weeks (56 days)
- Monthly backups (1st): Keep 12 months (365 days)

**Implementation:** See cron jobs above (automated cleanup)

#### Log Rotation
- [ ] Configure log rotation for Apache logs
- [ ] Configure log rotation for application logs (10MB max, 14 days retention)
- [ ] Configure security log retention (90 days)

Create `/etc/logrotate.d/irmajosh`:
```
/var/www/irmajosh.com/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    size 10M
}

/var/www/irmajosh.com/storage/logs/security.log {
    daily
    missingok
    rotate 90
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    size 10M
}
```

#### Offsite Backup Strategy

**Manual Weekly Backup (Sundays, after automated backup):**

1. Download latest weekly backup:
   ```bash
   scp root@irmajosh.com:/var/backups/irmajosh/db-$(date +%Y-%m-%d)*-Sunday*.gpg ~/Backups/IrmaJosh/
   scp root@irmajosh.com:/var/backups/irmajosh/storage-$(date +%Y-%m-%d)*-Sunday*.gpg ~/Backups/IrmaJosh/
   ```

2. Store in multiple locations:
   - Local external drive (encrypted)
   - Cloud storage (optional)
   - Second workstation

3. Verify download:
   ```bash
   ls -lh ~/Backups/IrmaJosh/ | grep $(date +%Y-%m-%d)
   ```

**Calendar Reminder:** Every Sunday at 10 AM: "Download IrmaJosh weekly backup"

### 2. Monitoring and Alerting (1-2 hours)

#### Uptime Monitoring (Self-Hosted)

**Implementation:** Self-hosted systemd timer (replaces UptimeRobot)

Create `/usr/local/bin/ij-healthcheck.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail
HEALTH_URL="https://irmajosh.com/health"
HOSTNAME=$(hostname -f || hostname)
STAMP=$(date -Is)
if curl -fsS --max-time 8 "$HEALTH_URL" > /dev/null; then
  exit 0
else
  printf "[%s] HEALTHCHECK FAILED on %s for %s\n" "$STAMP" "$HOSTNAME" "$HEALTH_URL" \
    | mail -s "[IrmaJosh] Health check FAILED" admin@irmajosh.com
  exit 2
fi
```

Create `/etc/systemd/system/ij-healthcheck.service`:
```ini
[Unit]
Description=IrmaJosh.com health check

[Service]
Type=oneshot
ExecStart=/usr/local/bin/ij-healthcheck.sh
```

Create `/etc/systemd/system/ij-healthcheck.timer`:
```ini
[Unit]
Description=Run IrmaJosh health check every 5 minutes

[Timer]
OnBootSec=2m
OnUnitActiveSec=5m
AccuracySec=30s
Unit=ij-healthcheck.service

[Install]
WantedBy=timers.target
```

**Enable:**
```bash
chmod +x /usr/local/bin/ij-healthcheck.sh
systemctl daemon-reload
systemctl enable --now ij-healthcheck.timer
systemctl list-timers | grep ij-healthcheck
```

#### Health Endpoint

Create `public_html/health.php`:
```php
<?php
/**
 * Health Check Endpoint
 * Returns 200 OK with status information
 */

header('Content-Type: application/json');

$status = 'ok';
$checks = [];

// 1. Database connectivity
try {
    require_once __DIR__ . '/bootstrap.php';
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $checks['database'] = 'ok';
} catch (PDOException $e) {
    $status = 'error';
    $checks['database'] = 'error';
}

// 2. Session directory writable
if (is_writable(session_save_path())) {
    $checks['sessions'] = 'ok';
} else {
    $status = 'warning';
    $checks['sessions'] = 'not_writable';
}

// 3. Log directory writable
if (is_writable(__DIR__ . '/../storage/logs')) {
    $checks['logs'] = 'ok';
} else {
    $status = 'warning';
    $checks['logs'] = 'not_writable';
}

// 4. APP_SECRET exists
if (!empty($_ENV['APP_SECRET'])) {
    $checks['config'] = 'ok';
} else {
    $status = 'error';
    $checks['config'] = 'missing_secret';
}

http_response_code($status === 'ok' ? 200 : 503);

echo json_encode([
    'status' => $status,
    'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
    'checks' => $checks
], JSON_PRETTY_PRINT);
```

#### Error Log Monitoring
Already configured via cron job. Additional monitoring:

- [ ] Set up weekly log review reminder (calendar event)
- [ ] Document log analysis procedures

#### Manual Review Schedule
- [ ] **Weekly (Sundays):**
  - Review error.log for anomalies
  - Review security.log for failed logins, unusual activity
  - Check Apache access.log for unusual traffic patterns
  - PII redaction verification
  - Download weekly offsite backup
  
- [ ] **Monthly (1st of month):**
  - Review composer-audit.log for security vulnerabilities
  - Run `composer update` in LOCAL environment
  - Test thoroughly, commit composer.lock
  - Deploy to production
  - Monthly security review
  
- [ ] **Quarterly (Jan 1, Apr 1, Jul 1, Oct 1):**
  - Full security audit
  - APP_SECRET rotation (annually on Jan 1)
  - Backup restoration test (automated)
  - SSL certificate renewal verification
  - Review and update documentation

#### Weekly Security Audit (Sundays)

```bash
# 1. PII Redaction Check
grep -E "[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" storage/logs/error.log
grep -E "access_token|refresh_token" storage/logs/*.log

# 2. Review failed logins
grep "login_failed" storage/logs/security.log | tail -50

# 3. Check for unusual activity
grep "unauthorized" storage/logs/security.log | tail -50

# 4. Verify rate limiting
tail -n 100 storage/logs/security.log | grep "rate_limit"

# 5. Check Apache access patterns
tail -n 1000 /var/log/apache2/irmajosh-access.log | awk '{print $1}' | sort | uniq -c | sort -rn
# Expected: 2-4 IPs with most requests
```

#### Monthly Security Review (1st of month)

```bash
# 1. Check for unauthorized allowed_emails
mysql -u irmajosh_user -p irmajosh_db -e "SELECT * FROM allowed_emails;"
# Expected: Only 2 emails

# 2. Review audit logs for unusual patterns
mysql -u irmajosh_user -p irmajosh_db -e "SELECT action, COUNT(*) as count FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY action ORDER BY count DESC;"

# 3. Check for stale sessions
find /var/lib/php/sessions -type f -mtime +7 -ls
# Expected: None (cron should clean daily)

# 4. Verify SSL certificate expiry
echo | openssl s_client -servername irmajosh.com -connect irmajosh.com:443 2>/dev/null | openssl x509 -noout -dates
# Should show >30 days until expiry

# 5. Check Apache security headers
curl -I https://irmajosh.com | grep -E "Strict-Transport|Content-Security|X-Frame"
# Verify all security headers present
```

#### Quarterly Security Audit

**Jan 1, Apr 1, Jul 1, Oct 1:**
- [ ] Backup restoration test (automated via verify_backup.sh)
- [ ] SSL certificate check (>30 days expiry)
- [ ] Review security.log for patterns
- [ ] Check allowed_emails table
- [ ] Verify rate limiting configuration
- [ ] Test session security (SESSION_SECURE, __Host- prefix)
- [ ] Update documentation if needed

### 3. Backup Verification (30 minutes)

#### Quarterly Backup Restoration Test (Non-Interactive)

Create script: `scripts/verify_backup.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail
LOG="/var/www/irmajosh.com/storage/logs/backup-verification.log"
exec >>"$LOG" 2>&1
STAMP=$(date -Is)
echo "==== [$STAMP] Starting backup verification ===="

# Paths
DB_BKP=$(ls -t /var/backups/irmajosh/db-*.sql.gz.gpg | head -1)
ST_BKP=$(ls -t /var/backups/irmajosh/storage-*.tar.gz.gpg | head -1)
[ -f "$DB_BKP" ] && [ -f "$ST_BKP" ] || { echo "ERROR: Missing backup files"; exit 1; }

echo "DB: $DB_BKP"; echo "FS: $ST_BKP"

# Temp DB name
TMPDB="ij_restore_test_$(date +%s)"

# Non-interactive MySQL (configure once with):
# mysql_config_editor set --login-path=ijlocal --host=localhost --user=root --password

# Create temp DB
mysql --login-path=ijlocal -e "CREATE DATABASE \`$TMPDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Decrypt & restore
if ! gpg --batch --yes --decrypt "$DB_BKP" | gunzip | mysql --login-path=ijlocal "$TMPDB"; then
  echo "ERROR: DB restore failed"; mysql --login-path=ijlocal -e "DROP DATABASE IF EXISTS \`$TMPDB\`;"; exit 1
fi

# Verify required tables exist
REQ=(users allowed_emails user_tokens audit_logs tasks schedule_slots _migrations)
for t in "${REQ[@]}"; do
  C=$(mysql --login-path=ijlocal -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TMPDB' AND table_name='$t';")
  if [ "$C" != "1" ]; then echo "ERROR: Missing table '$t'"; mysql --login-path=ijlocal -e "DROP DATABASE \`$TMPDB\`;"; exit 1; fi
done

echo "✓ Database restoration check passed"
mysql --login-path=ijlocal -e "DROP DATABASE \`$TMPDB\`;"

# Verify storage archive integrity
if gpg --batch --yes --decrypt "$ST_BKP" | tar -tzf - > /dev/null 2>&1; then
  echo "✓ Storage archive integrity OK"
else
  echo "ERROR: Storage archive is corrupted"; exit 1
fi

echo "✓ Backup verification complete"
```

**Configure non-interactive MySQL access (one-time setup):**
```bash
mysql_config_editor set --login-path=ijlocal --host=localhost --user=root --password
# Enter MySQL root password when prompted
```

Schedule quarterly:
```bash
# Add to crontab (runs Jan 1, Apr 1, Jul 1, Oct 1 at 3 AM)
0 3 1 1,4,7,10 * /var/www/irmajosh.com/scripts/verify_backup.sh
```

### 4. APP_SECRET Management (1 hour)

#### Annual APP_SECRET Rotation

**Schedule:** January 1st (annually, not quarterly)

**Procedure:**
1. Generate new APP_SECRET:
   ```bash
   printf "APP_SECRET=%s\n" "$(php -r 'echo bin2hex(random_bytes(32));')" > .env.new
   ```

2. Run rotation script:
   ```bash
   php scripts/rotate_app_secret.php
   ```
   
3. **Verify token re-encryption succeeded:**
   ```bash
   grep "token.*decrypt" storage/logs/error.log | tail -20
   # Expected: No decryption errors
   # If ANY errors, proceed to step 7 (force re-authentication)
   ```

4. **CRITICAL:** Backup new APP_SECRET to password manager:
   - Open 1Password/Bitwarden
   - Find existing entry: "irmajosh.com APP_SECRET"
   - Update with new value
   - Add version note: "Rotated on [Date]"
   - Archive old version (keep last 2 versions)

5. Restart PHP-FPM:
   ```bash
   systemctl restart php8.2-fpm
   ```

6. Test application (login, calendar sync)

7. Monitor logs for decryption errors

**Estimated downtime:** 1-2 minutes

#### Emergency APP_SECRET Recovery
If APP_SECRET is lost:
1. Retrieve from password manager
2. Update .env file
3. Restart PHP-FPM
4. If password manager backup also lost:
   - Generate new APP_SECRET
   - All users must re-authenticate
   - All encrypted tokens will be lost

### 5. Recovery Procedures Documentation (2-3 hours)

#### Database Corruption Recovery
**RTO: 4 hours | RPO: 24 hours**

Procedure:
1. Stop Apache: `systemctl stop apache2`
2. Identify latest valid backup:
   ```bash
   ls -lh /var/backups/irmajosh/
   ```
3. Restore:
   ```bash
   bash scripts/restore_backup.sh /var/backups/irmajosh/db-YYYYMMDD.sql.gz.gpg /var/backups/irmajosh/storage-YYYYMMDD.tar.gz.gpg
   ```
4. Verify database integrity:
   ```bash
   mysqlcheck -u irmajosh_user -p irmajosh_db
   ```
5. Start Apache: `systemctl start apache2`
6. Test application functionality
7. Monitor logs for errors

#### Lost Google Tokens Recovery
**RTO: 30 minutes | RPO: 0 (user re-authenticates)**

Procedure:
1. User reports "can't sync calendar"
2. Check user_tokens table:
   ```sql
   SELECT user_id, updated_at FROM user_tokens WHERE user_id = ?;
   ```
3. If tokens are old or decryption fails:
   - User must re-authenticate via Google OAuth
   - Old tokens will be replaced
4. If APP_SECRET is lost:
   - All users must re-authenticate
   - Clear all tokens: `DELETE FROM user_tokens;`

#### APP_SECRET Compromise (Enhanced)
**RTO: 2 hours | RPO: 0**

Procedure:
1. Generate new APP_SECRET (save to .env.new)
2. Run rotation script (re-encrypts all tokens)
3. Update password manager with new APP_SECRET
4. Restart PHP-FPM

5. **Verify token re-encryption succeeded:**
   ```bash
   grep "token.*decrypt" storage/logs/error.log | tail -20
   # If ANY errors, proceed to step 6
   ```

6. **Decision: Force re-authentication?**
   
   **Force if:**
   - Token re-encryption failed (errors in step 5)
   - Breach confirmed (attacker accessed encrypted tokens)
   - Unable to verify token integrity
   
   **Skip if:**
   - Re-encryption succeeded (no errors)
   - Proactive rotation (no known breach)
   - Both users available to test
   
   **To force re-auth:**
   ```sql
   -- Delete all tokens
   DELETE FROM user_tokens;
   ```
   -- Notify users via email that re-login required

7. Review security logs for breach indicators
8. Document incident and response actions

#### Server Failure
**RTO: 8 hours | RPO: 24 hours**

Procedure:
1. Provision new server (same OS/version)
2. Install all dependencies (Phase 4)
3. Clone repository from GitHub
4. Restore latest backup
5. Manually create .env from password manager
6. Verify APP_SECRET correct
7. Test token decryption
8. Update DNS A records (if new IP)
9. Obtain new SSL certificate
10. Test application thoroughly

#### Code Deployment Error
**RTO: 30 minutes | RPO: 0**

Procedure:
1. Identify broken commit:
   ```bash
   git log --oneline -10
   ```
2. Revert:
   ```bash
   git revert <commit-hash>
   ```
3. Redeploy:
   ```bash
   bash scripts/deploy.sh
   ```
4. Test application
5. Investigate issue in local environment
6. Fix and redeploy

#### Migration Failure (Enhanced)
**RTO: 1 hour | RPO: 0**

Procedure:
1. **Identify failed migration:**
   ```sql
   SELECT * FROM _migrations ORDER BY applied_at DESC LIMIT 5;
   -- Note the migration_name
   ```

2. **Check if partially applied:**
   ```bash
   # Review error.log for SQL errors
   tail -100 storage/logs/error.log
   ```

3. Restore pre-migration backup:
   ```bash
   bash scripts/restore_backup.sh <pre-migration-backup>
   ```

4. **Verify migration tracking state:**
   ```sql
   SELECT * FROM _migrations WHERE migration_name = '[failed_migration]';
   -- If exists, remove it:
   DELETE FROM _migrations WHERE migration_name = '[failed_migration]';
   ```

5. Restore codebase:
   ```bash
   git revert <migration-commit>
   bash scripts/deploy.sh
   ```

6. **Verify application works:**
   - Test login
   - Test calendar sync
   - Check error logs

7. Investigate in local environment:
   - Run migration on local DB copy
   - Fix migration file
   - Test multiple times

8. Redeploy fixed migration:
   - Commit fix
   - Deploy to production
   - Monitor closely
   - Verify success

### 6. Troubleshooting Guides (1-2 hours)

#### Can't Login?
Decision tree:

1. **Verify session security configuration (production only):**
   ```bash
   grep "SESSION_SECURE\|session.cookie_" .env /etc/php/8.2/fpm/php.ini
   
   # Expected in .env:
   # SESSION_SECURE=true
   
   # Expected in php.ini:
   # session.cookie_secure = 1
   # session.cookie_httponly = 1
   # session.cookie_samesite = Lax
   # session.name = __Host-PHPSESSID
   
   # If incorrect, fix and restart PHP-FPM:
   systemctl restart php8.2-fpm
   ```

2. Is email in allowed_emails?
   ```bash
   mysql -u irmajosh_user -p irmajosh_db -e "SELECT * FROM allowed_emails;"
   ```
   - **No:** Add via SQL: `INSERT INTO allowed_emails (email) VALUES ('user@example.com');`
   - **Yes:** Continue to step 3

3. Check security.log for login_failed events:
   ```bash
   grep "login_failed" storage/logs/security.log | tail -20
   ```
   - If OAuth errors, check Google Console for issues

4. Are session files writable?
   ```bash
   ls -ld /var/lib/php/sessions/
   ```
   - Fix: `chmod 755 /var/lib/php/sessions`

5. Try clearing browser cookies

6. Verify OAuth state validation not failing (check logs)

#### Calendar Not Syncing?
Decision tree:
1. Check user_tokens.updated_at:
   ```sql
   SELECT user_id, updated_at FROM user_tokens WHERE user_id = ?;
   ```
   - If old (>1 hour), prompt re-auth

2. Check error.log for Google_Service_Exception:
   ```bash
   grep "Google_Service_Exception" storage/logs/error.log | tail -20
   ```
   - If 401, token expired → user must re-authenticate

3. Check network connectivity:
   ```bash
   curl https://www.googleapis.com/calendar/v3/users/me/calendarList
   ```

4. **Check Google API quota:**
   ```bash
   # Check error logs for quota errors
   grep -i "quota" storage/logs/error.log | tail -20
   
   # Manual check:
   # - Open Google Cloud Console
   # - APIs & Services → Quotas
   # - Calendar API → Requests per day
   # - For 2 users: Should never exceed ~1,000/day
   
   # If quota exceeded:
   # - Investigate for sync loops or bugs
   # - Check audit_logs for unusual patterns
   # - Contact Google Support for quota increase
   ```

5. Verify time sync active:
   ```bash
   timedatectl status
   ```
   - If not synced, restart chrony: `systemctl restart chrony`

#### 500 Error?
Decision tree:
1. Check error.log immediately:
   ```bash
   tail -n 100 storage/logs/error.log
   ```

2. Is .env file readable?
   ```bash
   ls -l .env
   ```
   - Fix: `chmod 640 .env && chown www-data:www-data .env`

3. Can PHP connect to database?
   ```bash
   php -r "new PDO('mysql:host=localhost;dbname=irmajosh_db', 'user', 'pass');"
   ```

4. Are storage/logs writable?
   ```bash
   ls -ld storage/logs/
   ```
   - Fix: `chmod 755 storage/logs && chown www-data:www-data storage/logs`

5. Is PHP-FPM running?
   ```bash
   systemctl status php8.2-fpm
   ```
   - If not, restart: `systemctl restart php8.2-fpm`

6. Check OPcache:
   ```bash
   php -i | grep opcache.enable
   ```
   - If issues, restart PHP-FPM

#### Slow Performance?
Decision tree:
1. Check OPcache status:
   ```bash
   php -i | grep opcache
   ```

2. Check database slow query log:
   ```bash
   grep "Query_time" /var/log/mysql/slow-query.log
   ```

3. Check server load:
   ```bash
   top
   # or
   htop
   ```

4. Check Apache worker availability:
   ```bash
   apachectl status
   ```

5. Check disk I/O:
   ```bash
   iostat -x 1 5
   ```

#### CSP Violations in Console?
Decision tree:

1. **Verify nonce generation uses $GLOBALS (not $_SESSION):**
   ```bash
   grep -A 5 "csp_nonce" public_html/bootstrap.php
   
   # Expected output:
   # $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
   # $GLOBALS['csp_nonce'] = $nonce; // CRITICAL: Must use $GLOBALS, not $_SESSION
   ```

2. **Verify nonce is passed to views:**
   ```bash
   grep "csp_nonce" public_html/views/dashboard.php
   
   # Should contain: <script nonce="<?= $GLOBALS['csp_nonce'] ?>">
   ```

3. Check for duplicate CSP headers:
   ```bash
   curl -I https://irmajosh.com | grep Content-Security-Policy | wc -l
   # Must return: 1 (exactly one CSP header)
   ```

4. Review CSP violation reports:
   ```bash
   grep "csp-report" storage/logs/security.log | tail -20
   ```

5. Temporarily disable CSP (development only) to isolate issue

#### Rate Limiting Verification

**Verify rate limiting is ONLY on auth endpoints:**
```bash
# Check Apache configuration
grep -r "RateLimit" /etc/apache2/sites-available/irmajosh.conf

# Expected: Rate limits ONLY for:
# - /auth/login
# - /auth/callback
# - /csp-report

# Should NOT show:
# - /calendar/*
# - /tasks/*
# - /schedule/*
# - /health
```

**Monitor rate limit violations:**
```bash
# Check today's rate limit hits
grep "rate_limit_exceeded" storage/logs/security.log | grep "$(date +%Y-%m-%d)" | wc -l

# Review recent violations
grep "rate_limit_exceeded" storage/logs/security.log | tail -20
```

#### Log Analysis Guide

**error.log - What's normal vs concerning:**

**NORMAL (expected):**
- Failed login attempts (1-2 per week)
- Token refresh failures followed by success
- Network timeouts to Google APIs (transient)

**CONCERNING (investigate immediately):**
- Repeated 500 errors (>5 per day)
- Database connection errors
- Token decryption failures
- Multiple failed logins from same IP (>5 in 1 hour)
- PHP fatal errors
- Any error containing "injection", "exploit", "attack"

**security.log - What's normal vs concerning:**

**NORMAL:**
- 0-2 failed login attempts per week (user typos)
- Token refresh events (daily per user)
- Successful authentications (several per day)

**CONCERNING:**
- Failed logins from unknown IPs
- Failed logins with non-allowlisted emails (>10 per day = attack)
- Multiple failed logins for same email (>5 per day)
- Geographically impossible logins (US → Asia in 1 hour)
- Token decryption failures for active users

**Apache access.log - What's normal vs concerning:**

**NORMAL:**
- 50-200 requests per day (2 users)
- 90%+ requests to /calendar, /tasks, /schedule
- User-Agent: Modern browsers
- IP addresses: 2-4 unique IPs

**CONCERNING:**
- >1000 requests per day (scraping/attack)
- Requests to non-existent paths (scanning)
- User-Agent: Automated tools (curl, python)
- Many unique IPs
- 404 errors for /.env, /admin, /phpMyAdmin (attack)

### 7. Security Incident Response (1 hour)

#### Suspected Breach Procedure

**7-Step Process:**

1. **Isolate** (5-10 minutes)
   - Stop Apache: `systemctl stop apache2`
   - Or enable maintenance mode

2. **Assess** (30-60 minutes)
   - Review security.log:
     ```bash
     grep -E "login_failed|token_|unauthorized" storage/logs/security.log
     ```
   - Review audit_logs table:
     ```sql
     SELECT * FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC;
     ```
   - Check for unusual IP addresses:
     ```bash
     grep -oP 'ip:\K[0-9.]+' storage/logs/security.log | sort | uniq -c | sort -rn
     ```

3. **Contain** (30-60 minutes)
   - Rotate APP_SECRET
   - Change database password
   - Rotate Google OAuth client secret (Google Console)
   - Revoke all user tokens: `DELETE FROM user_tokens;`

4. **Investigate** (1-4 hours)
   - Identify attack vector from logs
   - Check file integrity
   - Review recent code changes
   - Check for malware/backdoors

5. **Remediate** (1-8 hours)
   - Patch vulnerability
   - Update dependencies
   - Strengthen security controls

6. **Restore** (if needed)
   - Restore from last known-good backup
   - Verify no malicious code

7. **Notify** (24 hours)
   - Inform both users if PII compromised
   - Provide timeline and actions taken

8. **Document**
   - Write post-mortem with:
     - Timeline of events
     - Root cause analysis
     - Lessons learned
     - Preventive measures

#### Incident Severity Levels

**P0 - Critical (Response: Immediate)**
- Complete service outage
- Data breach or exfiltration
- APP_SECRET compromise
- Database corruption

**P1 - High (Response: <1 hour)**
- Authentication completely broken
- Calendar sync broken for all users
- Data loss affecting both users

**P2 - Medium (Response: <4 hours)**
- Single user can't login
- Calendar sync slow/intermittent
- Performance degradation

**P3 - Low (Response: <24 hours)**
- Minor UI issues
- Non-critical feature broken
- Cosmetic problems

#### Incident Communication Templates

**5-Minute Update Template:**

**Subject:** [IrmaJosh] Incident Update – <short description>

**Message:**
- Detected: <UTC time>
- Scope: <which features/users>
- Impact: <what's broken>
- Action taken: <isolation/containment>
- Next update: <UTC time +30m>

**Post-Mortem Outline (24h after resolution):**
- Timeline (UTC)
- Root Cause
- Impact & Metrics
- What Went Well / What Hurt
- Fixes Deployed
- Preventive Actions (owners & dates)

#### Emergency Token Revocation

```sql
-- Revoke all user tokens (forces re-authentication)
DELETE FROM user_tokens;
```

All users must log in again via Google OAuth.

### 8. User Management Procedures

#### User Onboarding Procedure (15 minutes)

**Adding New Users:**

Prerequisites:
- User has Google account
- User shares email address with admin

Procedure:
1. SSH to production server

2. Access MySQL:
   ```bash
   mysql -u irmajosh_user -p irmajosh_db
   ```

3. Add to allowlist:
   ```sql
   INSERT INTO allowed_emails (email, created_at) 
   VALUES ('newuser@example.com', NOW());
   ```

4. Verify:
   ```sql
   SELECT * FROM allowed_emails;
   ```

5. Notify user to visit https://irmajosh.com and click "Login with Google"

6. After first login, verify user created:
   ```sql
   SELECT id, email, google_user_id, created_at FROM users;
   ```

#### User Removal Procedure

**Prerequisites:**
- Admin access to production
- Confirmation from other user

**Procedure:**
```bash
1. SSH to production

2. **Backup user data:**
   mysqldump -u irmajosh_user -p irmajosh_db \
     --tables users user_tokens tasks schedule_slots audit_logs \
     --where="user_id=[USER_ID]" > user_backup_$(date +%Y%m%d).sql

3. Access MySQL:
   mysql -u irmajosh_user -p irmajosh_db

4. **Remove user data (in order):**
   -- Get user ID
   SELECT id, email FROM users WHERE email = 'user@example.com';
   
   -- Delete user data
   DELETE FROM user_tokens WHERE user_id = [USER_ID];
   DELETE FROM audit_logs WHERE user_id = [USER_ID];
   DELETE FROM schedule_slots WHERE user_id = [USER_ID];
   DELETE FROM tasks WHERE user_id = [USER_ID];
   DELETE FROM allowed_emails WHERE email = 'user@example.com';
   DELETE FROM users WHERE id = [USER_ID];

5. **Verify removal:**
   SELECT * FROM users WHERE email = 'user@example.com';
   -- Should return: Empty set

6. **Revoke Google OAuth:**
   - User visits: https://myaccount.google.com/permissions
   - Remove "IrmaJosh" app access

7. **Optional: Preserve shared tasks:**
   UPDATE tasks SET user_id = [REMAINING_USER_ID] 
   WHERE user_id = [REMOVED_USER_ID] AND is_shared = 1;

8. Notify remaining user
```

---

## Operational Policies

### Maintenance Communication Protocol

**Planned Maintenance (>5 min downtime):**
1. **Notify other user:** 24 hours advance (text or in-person)
2. **Schedule:** Low-usage time (2-4 AM) or mutually agreed
3. **Reminder:** 1 hour before maintenance
4. **During:** Site unavailable 5-30 minutes

**Emergency Maintenance (<5 min):**
1. **Proceed immediately**
2. **Notify:** Text/call when starts
3. **Follow-up:** Brief explanation after

**Communication Channels:**
- **Primary:** Text message
- **Secondary:** In-person
- **Emergency:** Phone call (P0/P1 incidents)

**Examples:**
- APP_SECRET rotation: 24h notice, 2 AM
- Security patch: Emergency, notify immediately
- Dependency updates: 24h notice, 2 AM
- Server restart: <5min, anytime

### Testing Cadence

**Quarterly (automated):**
- ✅ Database restoration (verify_backup.sh)
- ✅ Storage archive integrity (verify_backup.sh)

**Annual:**
- ✅ APP_SECRET rotation (real rotation = test)
- ✅ Token re-encryption verification

**Never tested (acceptable risk):**
- ❌ Server failure recovery (too disruptive, docs sufficient)
- ❌ Migration rollback (test local only)
- ❌ Full disaster recovery drill (overkill for 2 users)

### Operational Success Criteria

**No formal KPIs. Informal quarterly review:**

**Automated indicators:**
- ✅ Daily backups successful
- ✅ Uptime checks passing
- ✅ Disk space <80%
- ✅ No critical security vulnerabilities

**Manual indicators (quarterly):**
- ✅ Backup restoration successful
- ✅ SSL certificate valid (>30 days)
- ✅ No security incidents
- ✅ Both users can login and sync

**Success criteria:**
- No user complaints
- No unplanned outages
- Security logs clean
- Backups working

**Optional: Quarterly "State of the Site" (5 min):**
- Last outage: [date or "none"]
- Disk usage: [XX%]
- Backups: [all successful]
- Security: [no incidents]
- Action items: [list or "none"]

---

## Maintenance Schedule Summary

### Daily (Automated)
- **2:00 AM:** Database backup (GPG encrypted)
- **3:00 AM:** Session cleanup
- **3:30 AM:** Backup retention cleanup (daily backups)
- **5:00 AM:** Disk space check (alert if >80%)
- **7:00 AM:** Rate limit report
- **Continuous:** Health check (every 5 minutes via systemd timer)

### Weekly (Manual + Automated)
- **Sundays 2:00 AM:** Composer security audit (automated)
- **Sundays 3:45 AM:** Weekly backup cleanup (automated)
- **Sundays 4:00 AM:** Cache cleanup (automated)
- **Sundays 6:00 AM:** Oversized log check (automated)
- **Sundays 7:00 AM:** Disk usage report (automated)
- **Sundays (manual):** 
  - Review error.log and security.log
  - PII redaction verification
  - Weekly security audit
  - Download weekly offsite backup

### Monthly (Manual - Local Environment)
- **1st of month:**
  - Monthly backup cleanup (automated at 4 AM)
  - Review composer-audit.log for vulnerabilities
  - Run `composer update` locally
  - **Test thoroughly before production:**
    - Login with Google OAuth (both users)
    - Calendar sync working
    - Create/edit/delete tasks
    - Schedule slot operations
    - Review error.log for ANY errors
  - If tests pass: commit composer.lock, deploy to production
  - Monitor production for 24 hours
  - Monthly security review

### Quarterly (Jan/Apr/Jul/Oct - 1st)
- **3:00 AM:** Automated backup verification
- **Manual tasks:**
  - Review backup verification log
  - SSL certificate check
  - Full security audit
  - Documentation refresh
  - Review operational success criteria

### Annual (Jan 1)
- **9:00 AM:** APP_SECRET rotation
- **9:30 AM:** Smoke tests
  - Both users can log in
  - Calendar sync working
  - No decryption errors in logs
  - Document rotation in changelog

---

## Quick Command Reference

### Daily Operations
```bash
# Check application health
curl https://irmajosh.com/health

# View recent errors
tail -n 50 storage/logs/error.log

# View recent security events
tail -n 50 storage/logs/security.log

# Check server load
htop

# Check systemd health check timer
systemctl list-timers | grep ij-healthcheck

# Check disk space
df -h

# Check backup status
ls -lh /var/backups/irmajosh/ | tail -10
```

### Deployment
```bash
# Standard deployment
git pull origin main
bash scripts/deploy.sh

# Emergency rollback
git revert HEAD
bash scripts/deploy.sh
```

### Maintenance
```bash
# Manual backup
bash scripts/backup.sh

# Rotate APP_SECRET (annual)
printf "APP_SECRET=%s\n" "$(php -r 'echo bin2hex(random_bytes(32));')" > .env.new
php scripts/rotate_app_secret.php

# Run migrations (with pre-backup)
bash scripts/backup.sh && php scripts/migrate.php

# Verify backup (quarterly)
bash scripts/verify_backup.sh

# Configure MySQL non-interactive access (one-time)
mysql_config_editor set --login-path=ijlocal --host=localhost --user=root --password
```

### Troubleshooting
```bash
# Check PHP-FPM status
systemctl status php8.2-fpm

# Check Apache status
systemctl status apache2

# Check disk space
df -h

# Check recent Apache errors
tail -f /var/log/apache2/irmajosh-error.log

# Test database connection
php -r "new PDO('mysql:host=localhost;dbname=irmajosh_db', 'user', 'pass');"

# Check time sync
timedatectl status

# Verify rate limiting configuration
grep -r "RateLimit" /etc/apache2/sites-available/irmajosh.conf

# Check for PII in logs
grep -E "[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" storage/logs/error.log

# Review failed logins
grep "login_failed" storage/logs/security.log | tail -50

# Check rate limit violations
grep "rate_limit_exceeded" storage/logs/security.log | tail -20
```

### Security Verification
```bash
# Verify session security
grep "SESSION_SECURE\|session.cookie_" .env /etc/php/8.2/fpm/php.ini

# Verify CSP nonce implementation
grep -A 5 "csp_nonce" public_html/bootstrap.php

# Check CSP headers
curl -I https://irmajosh.com | grep Content-Security-Policy | wc -l

# SSL certificate check
echo | openssl s_client -servername irmajosh.com -connect irmajosh.com:443 2>/dev/null | openssl x509 -noout -dates

# Check allowed users
mysql -u irmajosh_user -p irmajosh_db -e "SELECT * FROM allowed_emails;"
```

---

## Performance Baselines

**Expected Metrics (2-user app):**
- Page load time: <500ms (local), <2s (internet)
- Database queries: <10ms per query
- Google Calendar API calls: <1000ms
- Server load: <0.5 (htop)
- Memory usage: <200MB (PHP-FPM)
- Disk I/O: <10% utilization

**Establish baseline (first week):**
```bash
# Measure response times
ab -n 100 -c 1 https://irmajosh.com/ | grep "Time per request"

# Record baseline
echo "Baseline recorded: $(date)" >> storage/logs/performance-baseline.log
```

**Performance degradation thresholds:**
- Page load >3x baseline = investigate
- Database queries >5x baseline = check slow query log
- Server load >1.0 = investigate
- Memory usage >500MB = check for leaks

**Expected Log Volumes:**
- error.log: ~1-5 MB/month
- security.log: ~500 KB/month
- apache2/access.log: ~10-20 MB/month
- **Total:** ~15-30 MB/month

---

## Phase 6 Completion Checklist

### Critical Fixes (Must Complete Before Launch)
- [ ] Replace UptimeRobot with systemd health check timer
- [ ] Create health endpoint (public_html/health.php)
- [ ] Add CSP nonce verification to troubleshooting
- [ ] Add rate limiting verification procedure
- [ ] Enhance APP_SECRET compromise procedure
- [ ] Add session security verification
- [ ] Add PII redaction verification

### Email Setup
- [ ] Install and configure Postfix with Gmail relay
- [ ] Update .env with MAIL_FROM and ADMIN_EMAIL
- [ ] Test email delivery

### Automated Maintenance
- [ ] All cron jobs configured and active (see complete list above)
- [ ] Backup retention policies implemented
- [ ] Log rotation configured
- [ ] Backup automation working
- [ ] Weekly composer audit scheduled
- [ ] Disk space monitoring configured
- [ ] Rate limit monitoring configured

### Monitoring
- [ ] Self-hosted health check systemd timer deployed
- [ ] Health endpoint created and tested
- [ ] Email alerts configured
- [ ] Manual review schedule documented
- [ ] Calendar reminders set for weekly/quarterly tasks

### Backup & Recovery
- [ ] Non-interactive backup verification script created
- [ ] MySQL login-path configured for non-interactive access
- [ ] Verification scheduled in cron
- [ ] First verification test completed successfully
- [ ] Offsite backup procedure documented
- [ ] Calendar reminder set for weekly offsite backup

### Security Verification
- [ ] Weekly PII redaction check procedure documented
- [ ] Monthly security review procedure documented
- [ ] Quarterly security audit checklist created
- [ ] CSP nonce verification documented
- [ ] Rate limiting verification documented
- [ ] Session security verification documented

### APP_SECRET Management
- [ ] Annual rotation procedure documented (Jan 1)
- [ ] Password manager backup procedure documented
- [ ] Emergency recovery procedure documented
- [ ] Token re-encryption verification documented

### Recovery Procedures
- [ ] Database corruption recovery documented
- [ ] Token recovery procedure documented
- [ ] APP_SECRET compromise procedure enhanced
- [ ] Server failure procedure documented
- [ ] Code deployment error procedure documented
- [ ] Migration failure procedure enhanced

### Troubleshooting
- [ ] Login issues decision tree enhanced
- [ ] Calendar sync issues decision tree enhanced
- [ ] 500 error decision tree created
- [ ] Performance issues decision tree created
- [ ] CSP violations troubleshooting enhanced
- [ ] Rate limiting verification added
- [ ] Log analysis guide created

### Security & Incident Response
- [ ] Incident response procedure documented
- [ ] Incident severity levels defined
- [ ] Incident communication templates created
- [ ] Emergency token revocation procedure documented
- [ ] Post-mortem template created

### Operational Policies
- [ ] Maintenance communication protocol documented
- [ ] Testing cadence defined
- [ ] Operational success criteria defined
- [ ] Architecture decisions documented

### User Management
- [ ] User onboarding procedure documented
- [ ] User removal procedure documented
- [ ] Tested adding a user to allowlist

### Documentation
- [ ] All procedures tested and verified
- [ ] Quick command reference created
- [ ] Performance baselines documented
- [ ] Maintenance schedule documented
- [ ] Architecture decisions documented
- [ ] Review feedback integrated

---

## Post-Launch Checklist

### First Week
- [ ] Verify daily backups run successfully
- [ ] Verify health check timer working (check systemd logs)
- [ ] Verify disk space reports arrive
- [ ] Test manual backup restoration
- [ ] Verify all cron jobs execute
- [ ] Monitor logs daily
- [ ] Establish performance baseline

### First Month
- [ ] Complete first monthly security review
- [ ] Run first composer update with full testing
- [ ] Download 4 weekly offsite backups
- [ ] Review log patterns
- [ ] Note baseline disk usage
- [ ] Verify expected request volume

### First Quarter
- [ ] Verify automated backup restoration test runs
- [ ] Complete first quarterly security audit
- [ ] Review all procedures for accuracy
- [ ] Update documentation based on lessons learned

### First Year
- [ ] Complete APP_SECRET rotation (Jan 1)
- [ ] Verify token re-encryption worked
- [ ] Conduct informal operational review
- [ ] Update documentation based on operational experience

---

## Next Steps

✅ **Phase 6 Complete!**

🎉 **Implementation Complete!**

**Immediate Post-Launch Tasks (First 48 Hours):**
- Monitor application continuously
- Review logs every 6 hours
- Verify all automated tasks execute successfully
- Test health check endpoint responding
- Verify email alerts working

**First Week Tasks:**
- Review logs daily
- Establish performance baselines
- Collect user feedback
- Document any issues and resolutions
- Fine-tune monitoring thresholds

**First Month Tasks:**
- Complete first monthly maintenance cycle
- Review operational procedures for accuracy
- Adjust cron schedules if needed
- Update documentation based on experience

**Long-term:**
- Schedule first quarterly maintenance (3 months out)
- Set calendar reminder for annual APP_SECRET rotation (Jan 1)
- Maintain regular backup verification schedule
- Keep documentation up to date

---

## Appendix: Common Issues & Solutions

### Health Check Fails But Site Works

**Cause:** Health endpoint may be checking something incorrectly

**Solution:**
1. Check health endpoint directly: `curl https://irmajosh.com/health`
2. Review what's failing in the JSON response
3. Fix the specific check (database, sessions, logs, config)
4. Test again

### Backup Verification Fails

**Cause:** GPG key issues or database credentials

**Solution:**
1. Check GPG can decrypt: `gpg --decrypt /var/backups/irmajosh/db-latest.sql.gz.gpg | head`
2. Verify MySQL login-path: `mysql --login-path=ijlocal -e "SELECT 1;"`
3. Re-configure if needed: `mysql_config_editor set --login-path=ijlocal --host=localhost --user=root --password`

### Email Alerts Not Arriving

**Cause:** Postfix misconfiguration or Gmail blocking

**Solution:**
1. Check Postfix status: `systemctl status postfix`
2. Check mail queue: `mailq`
3. Test send: `echo "Test" | mail -s "Test" admin@irmajosh.com`
4. Review mail logs: `tail -f /var/log/mail.log`
5. Verify Gmail app password still valid

### Cron Jobs Not Running

**Cause:** Syntax errors or permission issues

**Solution:**
1. Check cron logs: `grep CRON /var/log/syslog | tail -50`
2. Test script manually: `bash /var/www/irmajosh.com/scripts/backup.sh`
3. Verify crontab syntax: `crontab -l`
4. Ensure scripts are executable: `chmod +x /var/www/irmajosh.com/scripts/*.sh`

---

**PHASE 6 - OPERATIONS & MAINTENANCE v2.0 - October 22, 2025**
**Integrated: GPT + Claude Combined Review**
**Grade: A (Combined)**
