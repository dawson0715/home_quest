# 08 — Deploy in produzione

Produzione: VPS **Ubuntu 24.04**, dominio **homequest.deplot.xyz**. In `deploy/` ci sono due varianti:

| Cartella | Variante | Uso |
|---|---|---|
| `deploy/apache/` | **Apache2 + PHP-FPM + MySQL direttamente sull'host**, senza Docker | **produzione** (questa guida §1–5, Apache e CI/CD in `09-apache-e-cicd.md`) |
| `deploy/docker/` | stack Docker Compose (app, cron, MySQL, backup, Caddy HTTPS) | alternativa autonoma, deploy a mano (§6) |

Il job CI `deploy` usa la variante **Apache**.

## Variante Apache (produzione)


| Componente | Dove | Note |
|---|---|---|
| Apache2 | host | HTTPS (certbot); serve l'app web da `/var/www-homequest/public` e `/api/*` a Yii — **`09-apache-e-cicd.md`** |
| PHP 8.5-FPM | pool `homequest` (`deploy/apache/php-fpm-homequest.conf`) | gira come utente `deploy`, socket `/run/php/homequest-fpm.sock` |
| MySQL 8.0 | host, `localhost` | DB `homequest`, utente `homequest` |
| Cron | crontab dell'utente `deploy` (`deploy/apache/crontab`) | `cron/minute` ogni minuto, `cron/daily` ogni ora, `cron/weekly` lunedì 07:00, backup alle 03:00 |
| Backup | `deploy/apache/backup.sh` → `/var/backups/homequest` | dump DB + foto ricette, 14 giorni |

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

Serve **Ubuntu 24.04** con **PHP 8.5** dal PPA `ondrej/php` (i repository ufficiali di 24.04 hanno solo la 8.3): `vendor/` è costruito dalla CI con PHP 8.5. Controllo: `lsb_release -ds`. Su 22.04 (PHP 8.1) aggiorna prima il sistema (§7).

```bash
# DNS: record A (e AAAA se c'è IPv6) di homequest.deplot.xyz → IP della VPS

# fuso orario (cron, MySQL e PHP in ora di Roma)
sudo timedatectl set-timezone Europe/Rome

# pacchetti (PHP 8.5 dal PPA ondrej/php)
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y apache2 certbot mysql-server \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-gd php8.5-curl php8.5-xml php8.5-intl

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
sudo cp deploy/apache/php-fpm-homequest.conf /etc/php/8.5/fpm/pool.d/homequest.conf   # dal repository
sudo php-fpm8.5 -t && sudo systemctl reload php8.5-fpm
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
   sudo install -o deploy -m 755 deploy/apache/backup.sh /usr/local/bin/homequest-backup   # dal repository
   sudo -u deploy crontab -e      # incolla deploy/apache/crontab
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

## 6. Variante Docker (`deploy/docker/`)

Alternativa alla variante Apache, **non usata dalla CI**. Tutto gira in container, con questi servizi definiti in `deploy/docker/docker-compose.prod.yml` (progetto `homequest`):

| Servizio | Cosa fa |
|---|---|
| `caddy` | HTTPS automatico (Let's Encrypt) per `$DOMAIN` sulle porte 80/443 |
| `app` | immagine `deploy/docker/Dockerfile`: Apache + PHP 8.5 con l'API Yii e la build del frontend sulla stessa origine (`apache.htaccess`: `/api/*` → Yii, file reali, poi fallback SPA) |
| `cron` | stessa immagine, `cron.sh`: `cron/minute` ogni minuto, `cron/daily` ogni ora, `cron/weekly` lunedì 07:00 |
| `db` | MySQL 8.0, volume `db-data` |
| `backup` | `mysqldump` ogni giorno alle 03:00 nel volume `backups`, 14 giorni |

Configurazione in `deploy/docker/.env` (modello `.env.example`, mai committato), passata ai container come variabili d'ambiente.

```bash
# server: Docker Engine + plugin compose, firewall 80/443 aperto, niente Apache sull'host (porte 80/443 a Caddy)
# copia del repository (dalla macchina di sviluppo)
rsync -az --delete --exclude .git --exclude android --exclude node_modules --exclude frontend/.svelte-kit \
  --exclude frontend/build --exclude backend/vendor --exclude backend/runtime --exclude deploy/docker/.env \
  ./ deploy@homequest.deplot.xyz:/var/www-homequest/

# sul server
cd /var/www-homequest/deploy/docker
cp .env.example .env && nano .env          # password DB, COOKIE_KEY, MAILER_*
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php yii migrate --interactive=0
docker compose -f docker-compose.prod.yml exec app php yii push/generate-keys   # chiavi in .env, poi `up -d`
docker compose -f docker-compose.prod.yml exec app php yii user/create anna "Anna"
docker compose -f docker-compose.prod.yml exec app php yii family/create "Rossi" anna
```

Aggiornamento: stesso rsync, poi `up -d --build` e `migrate`.

Backup:
- elenco: `docker compose -f docker-compose.prod.yml exec backup ls -lh /backups`;
- copia di un dump: `docker compose -f docker-compose.prod.yml cp backup:/backups/<file> .`;
- foto ricette: volume `homequest_uploads`.

Log: `docker compose -f docker-compose.prod.yml logs -f app cron caddy`.

## 7. Aggiornare il server da Ubuntu 22.04 a 24.04

Sintomo tipico su 22.04: `Your Composer dependencies require a PHP version ">= 8.3.0". You are running 8.1.x` (in risposta all'API o lanciando `php yii`). Ubuntu 22.04 ha solo PHP 8.1, e il controllo di piattaforma di Composer (`vendor/composer/platform_check.php`) blocca l'avvio. Non abbassare il requisito in `composer.json`: aggiorna il sistema.

### Prima

```bash
mysqldump --single-transaction --routines homequest | gzip > ~/homequest-pre-upgrade.sql.gz
sudo tar czf ~/etc-pre-upgrade.tgz /etc/apache2 /etc/php /etc/mysql /etc/letsencrypt
sudo -u deploy crontab -l > ~/crontab-deploy.bak
```

Se il provider lo permette, fai anche uno **snapshot della VPS** (rollback più rapido). Lancia l'upgrade dentro `screen` o `tmux`: una disconnessione SSH non lo interrompe.

### Upgrade

```bash
sudo apt-get update && sudo apt-get full-upgrade -y && sudo reboot
sudo do-release-upgrade
```

- Alle domande sui file di configurazione modificati (`apache2.conf`, `sshd_config`, `mysqld.cnf`…) **mantieni la versione locale** (N); nel dubbio guarda prima il diff (D).
- MySQL resta alla serie 8.0: i dati non vanno migrati.

### Dopo il riavvio

1. **PHP 8.5 e pool**: la 8.1 diventa obsoleta e il pool va ricreato per la 8.5.
   ```bash
   sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update
   sudo apt-get install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-gd php8.5-curl php8.5-xml php8.5-intl
   sudo cp deploy/apache/php-fpm-homequest.conf /etc/php/8.5/fpm/pool.d/homequest.conf   # dal repository
   sudo php-fpm8.5 -t && sudo systemctl enable --now php8.5-fpm
   sudo systemctl disable --now php8.1-fpm 2>/dev/null   # un vecchio pool 8.1 occuperebbe lo stesso socket
   ls -l /run/php/homequest-fpm.sock
   ```
2. **Apache**: `sudo apache2ctl -M | grep -E 'php|mpm|proxy_fcgi'`. Se trovi ancora `php8.1_module`: `sudo a2dismod php8.1 && sudo a2enmod proxy_fcgi setenvif`, poi `sudo apache2ctl configtest && sudo systemctl reload apache2` (configurazione completa in `09-apache-e-cicd.md` §2).
3. **Cron**: `sudo -u deploy crontab -l` deve coincidere con `deploy/apache/crontab`. `php` ora è la 8.5 (`php -v`; altrimenti `sudo update-alternatives --set php /usr/bin/php8.5`).
4. **Verifica**:
   ```bash
   cd /var/www-homequest/backend && sudo -u deploy php yii migrate --interactive=0   # "No new migrations found"
   curl -s https://homequest.deplot.xyz/api/v1/health                               # {"data":{"status":"ok","db":true,…}}
   sudo certbot renew --dry-run
   ```
5. **Pulizia** (facoltativa, quando tutto funziona): `sudo apt-get purge 'php8.1*' && sudo apt-get autoremove`.

Non serve rifare il deploy: il `vendor/` già sul server, costruito con PHP 8.5, ora parte.

### Cambio di versione PHP (es. 8.3 → 8.5)

Il pool sta nella cartella della singola versione (`/etc/php/<ver>/fpm/pool.d/`): dopo l'upgrade va copiato nella nuova, altrimenti sul socket resta il vecchio servizio e l'API risponde `Primary script unknown` o 503.

```bash
sudo apt-get install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-gd php8.5-curl php8.5-xml php8.5-intl
sudo cp /etc/php/8.3/fpm/pool.d/homequest.conf /etc/php/8.5/fpm/pool.d/
sudo systemctl disable --now php8.3-fpm
sudo php-fpm8.5 -t && sudo systemctl enable --now php8.5-fpm && sudo systemctl restart php8.5-fpm
sudo update-alternatives --set php /usr/bin/php8.5    # cron e `php yii`
php -v && curl -s https://homequest.deplot.xyz/api/v1/health
```

Poi un nuovo deploy (la CI ricostruisce `vendor/` con la versione nuova).
