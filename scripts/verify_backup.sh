#!/bin/bash
#
# Verify Backup Integrity
# Checks that backup files are properly encrypted and can be decrypted
#
# Usage: ./verify_backup.sh [backup_file.sql.gpg]
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_ROOT/backups}"

# Function to verify a single backup
verify_backup() {
    local file="$1"
    local filename=$(basename "$file")
    
    echo -n "Verifying $filename... "
    
    # Check file exists and is readable
    if [ ! -r "$file" ]; then
        echo "FAILED (not readable)"
        return 1
    fi
    
    # Check file size
    local size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null || echo 0)
    if [ "$size" -lt 100 ]; then
        echo "FAILED (file too small: ${size} bytes)"
        return 1
    fi
    
    # Verify GPG encryption
    if ! gpg --list-packets "$file" > /dev/null 2>&1; then
        echo "FAILED (invalid GPG encryption)"
        return 1
    fi
    
    # Try to decrypt (without saving)
    if ! gpg --decrypt "$file" > /dev/null 2>&1; then
        echo "FAILED (cannot decrypt)"
        return 1
    fi
    
    # Check decrypted content contains SQL
    if ! gpg --decrypt "$file" 2>/dev/null | head -n 10 | grep -q "CREATE\|INSERT\|DROP"; then
        echo "FAILED (invalid SQL content)"
        return 1
    fi
    
    echo "OK (${size} bytes)"
    return 0
}

# If specific file provided, verify it
if [ $# -eq 1 ]; then
    verify_backup "$1"
    exit $?
fi

# Otherwise, verify all backups in backup directory
if [ ! -d "$BACKUP_DIR" ]; then
    echo "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

echo "Verifying backups in: $BACKUP_DIR"
echo "========================================="

total=0
passed=0
failed=0

# Find and verify all backup files
while IFS= read -r -d '' file; do
    ((total++)) || true
    if verify_backup "$file"; then
        ((passed++)) || true
    else
        ((failed++)) || true
    fi
done < <(find "$BACKUP_DIR" -name "irmajosh_*.sql.gpg" -type f -print0 | sort -z)

echo "========================================="
echo "Total: $total | Passed: $passed | Failed: $failed"

if [ "$failed" -gt 0 ]; then
    exit 1
fi

exit 0
