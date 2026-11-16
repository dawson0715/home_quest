#!/bin/sh
# Scheduler for the `cron` service (TZ=Europe/Rome):
#   every minute → cron/minute (push reminders)
#   every hour   → cron/daily  (occurrences, missed, streaks; idempotent)
#   Monday 07:00 → cron/weekly (summary email)
set -u
# first deploy: wait until the migrations have created the tables
until php /app/yii cron/daily; do sleep 30; done
while true; do
  php /app/yii cron/minute
  M=$(date +%M); H=$(date +%H); D=$(date +%u)
  [ "$M" = "00" ] && php /app/yii cron/daily
  [ "$D" = "1" ] && [ "$H$M" = "0700" ] && php /app/yii cron/weekly
  sleep $((60 - $(date +%-S)))
done
