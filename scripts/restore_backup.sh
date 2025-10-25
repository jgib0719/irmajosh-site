#!/bin/bash
#
# Database Restore Script
# Restores database from encrypted backup
#
# Usage: ./restore_backup.sh <backup_file.sql.gpg>
#

set -euo pipefail

# Check arguments
if [ $# -ne 1 ]; then
    echo "Usage: $0 <backup_file.sql.gpg>"
    exit 1
fi

ENCRYPTED_FILE="$1"

# Validate backup file exists
if [ ! -f "$ENCRYPTED_FILE" ]; then
    echo "Error: Backup file not found: $ENCRYPTED_FILE"
    exit 1
fi

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
TEMP_SQL="/tmp/irmajosh_restore_$$.sql"

# Load environment variables
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: .env file not found at $ENV_FILE"
    exit 1
fi

source "$ENV_FILE"

# Validate required environment variables
if [ -z "${DB_NAME:-}" ] || [ -z "${DB_USER:-}" ] || [ -z "${DB_PASS:-}" ]; then
    echo "Error: DB_NAME, DB_USER, and DB_PASS must be set in .env"
    exit 1
fi

echo "WARNING: This will overwrite the current database: $DB_NAME"
read -p "Are you sure you want to continue? (yes/no): " -r
if [ "$REPLY" != "yes" ]; then
    echo "Restore cancelled"
    exit 0
fi

# Decrypt backup
echo "Decrypting backup..."
PASSPHRASE_FILE="/root/.config/irmajosh_backup.pass"
if [ ! -f "$PASSPHRASE_FILE" ]; then
    echo "Error: Passphrase file not found: $PASSPHRASE_FILE"
    rm -f "$TEMP_SQL"
    exit 1
fi

if ! gpg --batch --yes --passphrase-file "$PASSPHRASE_FILE" --decrypt --output "$TEMP_SQL" "$ENCRYPTED_FILE"; then
    echo "Error: Failed to decrypt backup"
    rm -f "$TEMP_SQL"
    exit 1
fi

echo "Decryption successful: $(du -h "$TEMP_SQL" | cut -f1)"

# Decompress if it's a gzip file
if file "$TEMP_SQL" | grep -q "gzip compressed"; then
    echo "Decompressing backup..."
    TEMP_DECOMPRESSED="/tmp/irmajosh_restore_decompressed_$$.sql"
    if ! gunzip -c "$TEMP_SQL" > "$TEMP_DECOMPRESSED"; then
        echo "Error: Failed to decompress backup"
        rm -f "$TEMP_SQL" "$TEMP_DECOMPRESSED"
        exit 1
    fi
    rm -f "$TEMP_SQL"
    TEMP_SQL="$TEMP_DECOMPRESSED"
    echo "Decompression successful: $(du -h "$TEMP_SQL" | cut -f1)"
fi

# Restore database
echo "Restoring database..."
if ! mysql \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --host="${DB_HOST:-localhost}" \
    --port="${DB_PORT:-3306}" \
    "$DB_NAME" < "$TEMP_SQL"; then
    echo "Error: Database restore failed"
    rm -f "$TEMP_SQL"
    exit 1
fi

# Clean up temporary file
rm -f "$TEMP_SQL"

echo "Database restored successfully from: $ENCRYPTED_FILE"
exit 0
