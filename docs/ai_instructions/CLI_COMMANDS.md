# Useful CLI Commands & Scripts

Use these scripts located in `/var/www/irmajosh.com/scripts/` to perform common maintenance tasks. Run them from the project root.

## Deployment & Maintenance
- **`./scripts/deploy.sh`**: Pulls git changes, runs migrations, installs dependencies, and busts cache. Use this for full updates.
- **`./scripts/bust_cache.sh`**: Updates the asset version timestamp and reloads PHP-FPM. Run this after modifying CSS/JS/Views.
- **`./scripts/check_permissions.sh`**: Fixes file ownership and permissions (www-data).
- **`./scripts/preflight.sh`**: Checks environment requirements (PHP version, extensions, writable dirs).

## Database & Migrations
- **`php scripts/migrate.php`**: Runs pending database migrations.
- **`./scripts/backup.sh`**: Creates a SQL dump of the database in `storage/backups/`.
- **`./scripts/restore_backup.sh <file>`**: Restores the database from a backup file.

## Testing & Debugging
- **`php scripts/test_email.php <email>`**: Sends a test email to verify SMTP config.
- **`php scripts/test_notifications.php`**: Tests web push notifications.
- **`php scripts/validate_config.php`**: Validates `.env` configuration.

## Cron & Scheduled Tasks
- **`php scripts/send_reminders.php`**: Sends event reminders (run via crontab).
- **`php scripts/rotate_app_secret.php`**: Rotates the application secret key (use with caution).

## Usage Example
```bash
# Run a migration
php scripts/migrate.php

# Fix permissions after creating files
sudo ./scripts/check_permissions.sh
```
