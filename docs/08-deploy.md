# 08 — Deploy in produzione

Produzione: VPS **Ubuntu 24.04**, dominio **homequest.deplot.xyz**, **senza Docker**: tutto gira direttamente sulla macchina.

| Componente | Dove | Note |
|---|---|---|
| Apache2 | host | HTTPS (certbot); serve l'app web da `/var/www-homequest/public` e `/api/*` a Yii — **`09-apache-e-cicd.md`** |
| PHP 8.3-FPM | pool `homequest` (`deploy/php-fpm/homequest.conf`) | gira come utente `deploy`, socket `/run/php/homequest-fpm.sock` |
| MySQL 8.0 | host, `localhost` | DB `homequest`, utente `homequest` |
| Cron | crontab dell'utente `deploy` (`deploy/crontab`) | `cron/minute` ogni minuto, `cron/daily` ogni ora, `cron/weekly` lunedì 07:00, backup alle 03:00 |
| Backup | `deploy/backup.sh` → `/var/backups/homequest` | dump DB + foto ricette, 14 giorni |

```
/var/www-homequest/            proprietario deploy
├── public/                    build dell'app web (copiata dalla CI)
└── backend/                   sorgenti Yii + vendor (copiati dalla CI)
    ├── .env                   impostazioni di produzione (solo sul server)
    ├── config/params-local.php  facoltativo (solo sul server)
    ├── runtime/               log, cache (solo sul server)
    └── web/uploads/           foto ricette (solo sul server)
```

Configurazione: `backend/.env` (modello `backend/.env.example`, mai committato), letto da `config/env.php` sia dal web sia dalla console. Con `HQ_ENV=prod`: niente debug, niente gii/debug, cache dello schema DB, CORS chiuso (stessa origine).

## 1. Preparare il server (una volta)

```bash
# DNS: record A (e AAAA se c'è IPv6) di homequest.deplot.xyz → IP della VPS

# fuso orario (cron, MySQL e PHP in ora di Roma)
sudo timedatectl set-timezone Europe/Rome

# pacchetti
sudo apt-get update
sudo apt-get install -y apache2 certbot mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-curl php8.3-xml php8.3-intl

# utente di deploy (la CI entra via SSH con questo utente)
sudo adduser --disabled-password --gecos "" deploy
# chiave pubblica della CI in /home/deploy/.ssh/authorized_keys (vedi 09 §5)

# cartelle
sudo mkdir -p /var/www-homequest/public /var/www-homequest/backend /var/backups/homequest
sudo chown -R deploy:deploy /var/www-homequest /var/backups/homequest

# firewall: solo SSH e web
sudo ufw allow OpenSSH && sudo ufw allow 'Apache Full' && sudo ufw enable
```

### MySQL

```bash
sudo mysql <<'SQL'
CREATE DATABASE homequest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'homequest'@'localhost' IDENTIFIED BY '<password-lunga>';
GRANT ALL PRIVILEGES ON homequest.* TO 'homequest'@'localhost';
SQL
```

MySQL ascolta solo su `127.0.0.1` (default Ubuntu): la porta 3306 non va aperta.

### PHP-FPM

```bash
sudo cp deploy/php-fpm/homequest.conf /etc/php/8.3/fpm/pool.d/homequest.conf   # dal repository
sudo php-fpm8.3 -t && sudo systemctl reload php8.3-fpm
ls -l /run/php/homequest-fpm.sock     # deve esistere
```

Il pool fissa fuso, limiti di upload (6 MB, l'API accetta foto fino a 5 MB) e log errori in `backend/runtime/logs/php-error.log`.

## 2. Primo deploy

1. **File**: lancia la pipeline su `main` e avvia il job manuale `build-web` (il job `deploy` parte da solo dopo) — oppure copia a mano come al §3 di `09-apache-e-cicd.md`.
2. **Impostazioni e cartelle di lavoro** (come utente `deploy`):
   ```bash
   cd /var/www-homequest/backend
   cp .env.example .env && chmod 600 .env
   nano .env        # DB_PASSWORD, COOKIE_KEY (openssl rand -hex 32), MAILER_*
   mkdir -p runtime/logs web/uploads/recipes
   ```
3. **Database e chiavi push**:
   ```bash
   php yii migrate --interactive=0
   php yii push/generate-keys     # copia le chiavi in .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)
   ```
4. **Primo genitore e famiglia**:
   ```bash
   php yii user/create anna "Anna"       # stampa il token d'accesso
   php yii family/create "Rossi" anna
   ```
5. **Cron e backup**:
   ```bash
   sudo install -o deploy -m 755 deploy/backup.sh /usr/local/bin/homequest-backup   # dal repository
   sudo -u deploy crontab -e      # incolla deploy/crontab
   ```
6. **Apache e HTTPS**: `09-apache-e-cicd.md` §2–4.

Verifica: `https://homequest.deplot.xyz/api/v1/health` → `{"data":{"status":"ok","db":true,…}}`.

## 3. Deploy successivi

Pipeline su `main` → job manuale `build-web` → `deploy` (dettagli in `09-apache-e-cicd.md` §5). La CI sostituisce **solo** `public/` e i sorgenti di `backend/`; DB, `.env`, `runtime/` e foto restano come sono.

Se il rilascio contiene migration, subito dopo sul server:

```bash
cd /var/www-homequest/backend
php yii migrate --interactive=0
php yii cache/flush-schema db --interactive=0     # in prod lo schema DB è in cache
```

## 4. Backup e ripristino

```bash
ls -lh /var/backups/homequest                     # homequest-AAAA-MM-GG.sql.gz + uploads-AAAA-MM-GG.tgz
scp deploy@homequest.deplot.xyz:/var/backups/homequest/homequest-2026-11-16.sql.gz .   # copia anche fuori dalla VPS

# ripristino DB (sovrascrive i dati!)
gunzip -c homequest-2026-11-16.sql.gz | sudo mysql homequest
# ripristino foto
tar xzf /var/backups/homequest/uploads-2026-11-16.tgz -C /var/www-homequest/backend/web
```

Prova del backup a mano: `sudo -u deploy /usr/local/bin/homequest-backup`.

## 5. Log e monitoraggio

| Log | Percorso |
|---|---|
| Applicazione (Yii, senza dati personali) | `backend/runtime/logs/app.log` |
| Cron / backup | `backend/runtime/logs/cron.log`, `backup.log` |
| Errori PHP | `backend/runtime/logs/php-error.log` |
| Apache | `/var/log/apache2/homequest.access.log` (senza query string), `homequest.error.log` |

Monitoraggio esterno minimo: un uptime checker su `/api/v1/health` (risponde 503 se il DB non è raggiungibile). Error tracking (GlitchTip/Sentry EU) non ancora integrato.
