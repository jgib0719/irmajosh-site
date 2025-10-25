#!/bin/bash
#
# Check File Permissions
# Ensures storage directories have correct permissions
#
# Usage: ./check_permissions.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "Checking file permissions..."
echo "========================================="

# Directories that need to be writable
WRITABLE_DIRS=(
    "storage"
    "storage/cache"
    "storage/logs"
    "storage/rate_limits"
)

issues=0

# Check each directory
for dir in "${WRITABLE_DIRS[@]}"; do
    echo -n "Checking $dir... "
    
    if [ ! -d "$dir" ]; then
        echo "MISSING - creating directory"
        mkdir -p "$dir"
        chmod 755 "$dir"
    elif [ ! -w "$dir" ]; then
        echo "NOT WRITABLE - fixing permissions"
        chmod 755 "$dir"
        ((issues++)) || true
    else
        echo "OK"
    fi
done

# Check vendor directory exists
echo -n "Checking vendor/... "
if [ ! -d "vendor" ]; then
    echo "MISSING - run 'composer install'"
    ((issues++)) || true
else
    echo "OK"
fi

# Check .env exists
echo -n "Checking .env... "
if [ ! -f ".env" ]; then
    echo "MISSING"
    ((issues++)) || true
elif [ ! -r ".env" ]; then
    echo "NOT READABLE"
    ((issues++)) || true
else
    echo "OK"
fi

# Check scripts are executable
echo -n "Checking script permissions... "
scripts_fixed=0
for script in scripts/*.sh; do
    if [ -f "$script" ] && [ ! -x "$script" ]; then
        chmod +x "$script"
        ((scripts_fixed++)) || true
    fi
done

if [ $scripts_fixed -gt 0 ]; then
    echo "FIXED ($scripts_fixed scripts)"
else
    echo "OK"
fi

echo "========================================="

if [ $issues -gt 0 ]; then
    echo "Found $issues issue(s)"
    exit 1
else
    echo "All permissions OK"
    exit 0
fi
