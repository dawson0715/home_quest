#!/bin/sh
# Daily MySQL dump at 03:00 (Europe/Rome), kept 14 days in the `backups` volume.
set -u
while true; do
  if [ "$(date +%H%M)" = "0300" ]; then
    FILE="/backups/homequest-$(date +%F).sql.gz"
    mysqldump -h db -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE" | gzip > "$FILE.tmp" \
      && mv "$FILE.tmp" "$FILE" && echo "backup ok: $FILE"
    find /backups -name 'homequest-*.sql.gz' -mtime +14 -delete
  fi
  sleep 60
done
