#!/usr/bin/env bash
# ==============================================================================
# Daily Cron Data Synchronization Script (Linux / cPanel / VPS)
# Products Analytics Dashboard
#
# Usage in Crontab (e.g. Run every day at 02:00 AM):
# 0 2 * * * /path/to/project/scripts/cron-daily.sh >> /path/to/project/writable/logs/cron_daily.log 2>&1
# ==============================================================================

# Resolve the project root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT" || exit 1

# Detect PHP executable if not in PATH
if command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
elif [ -f "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
elif [ -f "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
elif [ -f "/opt/cpanel/ea-php82/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
elif [ -f "/opt/cpanel/ea-php83/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php83/root/usr/bin/php"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Error: PHP binary not found!" >&2
    exit 1
fi

echo "======================================================================"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Daily Cron Sync from $PROJECT_ROOT"
echo "======================================================================"

# Execute Spark daily cron command
"$PHP_BIN" spark cron:daily --vectorize "$@"

EXIT_CODE=$?
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Daily Cron finished successfully."
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Daily Cron failed with exit code $EXIT_CODE." >&2
fi

exit $EXIT_CODE
