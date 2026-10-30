#!/bin/sh
set -eu
umask 077
export PORT="${PORT:-10000}"
case "$PORT" in ''|*[!0-9]*) echo 'Invalid HTTP port.' >&2; exit 1 ;; esac
[ "$PORT" -gt 0 ] && [ "$PORT" -le 65535 ] || exit 1
mountpoint -q /data || { echo 'A persistent /data volume is required.' >&2; exit 1; }
mkdir -p /data/keys /data/sessions /data/tmp /data/logs /data/uploads/products /data/uploads/profile
mkdir -p /data/clamav /data/clamav-logs /run/clamav
php /var/www/avela/deploy/materialize-secrets.php
cp /opt/avela-image-uploads/products/.htaccess /data/uploads/products/.htaccess
cp /opt/avela-image-uploads/profile/.htaccess /data/uploads/profile/.htaccess
chown www-data:www-data /data/keys /data/sessions /data/tmp /data/logs /data/uploads /data/uploads/products /data/uploads/profile
find /data/keys -maxdepth 1 -type f -exec chown www-data:www-data {} +
chmod 700 /data/keys /data/sessions /data/tmp /data/logs
chmod 600 /data/keys/*
chmod 755 /data /data/uploads /data/uploads/products /data/uploads/profile
chown clamav:clamav /data/clamav /data/clamav-logs
chown clamav:www-data /run/clamav
chmod 750 /run/clamav
if ! [ -f /data/clamav/daily.cvd ] && ! [ -f /data/clamav/daily.cld ]; then
    cp -a /opt/avela-clamav/. /data/clamav/
    chown -R clamav:clamav /data/clamav
fi
clamd --config-file=/etc/clamav/clamd.conf &
freshclam --daemon --config-file=/etc/clamav/freshclam.conf &
php /var/www/avela/deploy/check-environment.php
ready=0
for attempt in $(seq 1 90); do
    if php /var/www/avela/deploy/scanner-ready.php >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
[ "$ready" -eq 1 ] || { echo 'Upload scanner did not become ready.' >&2; exit 1; }
php /var/www/avela/deploy/seed-uploads.php
find /data/uploads -type f -exec chown www-data:www-data {} +
# No database installer, migration, account mutation or payment worker runs at startup.
exec apache2-foreground
