#!/bin/sh
# Daily backup on the server (crontab of user deploy, see deploy/apache/crontab):
# MySQL dump + recipe photos in /var/backups/homequest, kept 14 days.
# Install: sudo install -o deploy -m 755 deploy/apache/backup.sh /usr/local/bin/homequest-backup
set -eu
APP=${APP:-/var/www-homequest/backend}
DEST=${BACKUP_DIR:-/var/backups/homequest}
DAY=$(date +%F)
env_get() { sed -n "s/^$1=//p" "$APP/.env" | tail -n 1; }

mkdir -p "$DEST"
# password via MYSQL_PWD: not visible in `ps`
MYSQL_PWD=$(env_get DB_PASSWORD) mysqldump -u "$(env_get DB_USER)" \
  --single-transaction --no-tablespaces --routines "$(env_get DB_NAME)" \
  | gzip > "$DEST/homequest-$DAY.sql.gz.tmp"
mv "$DEST/homequest-$DAY.sql.gz.tmp" "$DEST/homequest-$DAY.sql.gz"
tar czf "$DEST/uploads-$DAY.tgz" -C "$APP/web" uploads
find "$DEST" -name 'homequest-*.sql.gz' -mtime +14 -delete
find "$DEST" -name 'uploads-*.tgz' -mtime +14 -delete
