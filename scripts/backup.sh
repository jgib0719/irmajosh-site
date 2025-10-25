#!/bin/bash
set -euo pipefail

# Configuration
BACKUP_DIR="/var/backups/irmajosh"
APP_DIR="/var/www/irmajosh.com"
DATE=$(date +%Y%m%d_%H%M%S)
LOCKFILE="/var/lock/irmajosh-backup.lock"
PASSPHRASE_FILE="/root/.config/irmajosh_backup.pass"

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
mysqldump --single-transaction --routines --triggers \
    -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip -9 > "$BACKUP_DIR/db-${DATE}.sql.gz"

# Backup storage (exclude .env - stored in password manager)
echo "Backing up storage..."
tar --exclude=.env -czf "$BACKUP_DIR/storage-${DATE}.tar.gz" -C "$APP_DIR" storage/

# GPG encryption (if passphrase file exists)
if [ -f "$PASSPHRASE_FILE" ]; then
    echo "Encrypting backups..."
    gpg --batch --yes --passphrase-file "$PASSPHRASE_FILE" --symmetric --cipher-algo AES256 "$BACKUP_DIR/db-${DATE}.sql.gz"
    gpg --batch --yes --passphrase-file "$PASSPHRASE_FILE" --symmetric --cipher-algo AES256 "$BACKUP_DIR/storage-${DATE}.tar.gz"
    
    # Remove unencrypted files
    rm -f "$BACKUP_DIR/db-${DATE}.sql.gz"
    rm -f "$BACKUP_DIR/storage-${DATE}.tar.gz"
    
    echo "Backups encrypted"
fi

# Retention: Keep last 30 days
echo "Pruning old backups (keeping last 30 days)..."
find "$BACKUP_DIR" -name "db-*.gpg" -type f -mtime +30 -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "storage-*.gpg" -type f -mtime +30 -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "db-*.sql.gz" -type f -mtime +30 -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "storage-*.tar.gz" -type f -mtime +30 -delete 2>/dev/null || true

echo "Backup complete at $(date)"
echo "Backup location: $BACKUP_DIR"
ls -lh "$BACKUP_DIR" | tail -5
