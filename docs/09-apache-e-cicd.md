# 09 — Apache2 e cartelle del deploy CI/CD

Guida operativa per la VPS **Ubuntu** di **homequest.deplot.xyz**: **Apache2 sull'host** con HTTPS Let's Encrypt (certbot), PHP-FPM e MySQL locali, nessun Docker. Preparazione del server, `.env`, cron e backup sono in `08-deploy.md`.

```
Internet ──443──▶ Apache2
                   ├─ /api/*      → backend/web/index.php (PHP-FPM, pool homequest) ─▶ MySQL localhost
                   ├─ /uploads/*  → backend/web/uploads/ (foto ricette, file statici)
                   └─ /*          → public/ (app web statica, fallback a index.html)
```

- **Niente proxy**: Apache esegue PHP tramite FPM (`proxy_fcgi`) e serve i file direttamente. L'IP del client è quello reale, quindi `TRUSTED_PROXIES` resta vuoto.
- **`/api`**: `AliasMatch` manda ogni URL `/api/...` a `backend/web/index.php`. Yii instrada su `REQUEST_URI` (`scriptUrl` fissato a `/index.php` in `config/web.php`).
- **Isolamento del backend**: solo `index.php` e `uploads/` del backend sono raggiungibili. `.env`, `config/`, `vendor/` e `runtime/` stanno fuori dalla docroot. Le URL inesistenti ricadono sulla pagina dell'app.
- **Header**: l'API manda i suoi header di sicurezza. Apache aggiunge HTTPS, HSTS, compressione, la cache dei file dell'app e gli header delle pagine dell'app. La CSP dell'app è un `<meta>` generato da SvelteKit.

File nel repository:

| File | Uso |
|---|---|
| `deploy/apache/homequest-http.conf` | vhost iniziale solo HTTP, per ottenere il certificato |
| `deploy/apache/homequest.conf` | vhost definitivo (redirect 80→443, HTTPS, `/api`, `/uploads`, app) |
| `deploy/apache/php-fpm-homequest.conf` | pool PHP-FPM (`08-deploy.md` §1) |

Verificati su Ubuntu 24.04 (Apache 2.4 + PHP 8.5-FPM):
- `apache2ctl configtest`;
- l'API risponde con l'envelope, `Authorization: Bearer` arriva a PHP;
- fallback SPA, `_app` mancanti → 404;
- `.env` e `index-test.php` non raggiungibili;
- HTTP/2.

---

## 1. Moduli Apache

```bash
sudo a2enmod proxy_fcgi setenvif headers rewrite ssl http2 deflate
sudo a2dissite 000-default          # sito di esempio, se non serve
sudo mkdir -p /var/www/certbot
```

Con PHP-FPM Apache usa `mpm_event`, necessario per HTTP/2. Se `apache2ctl -M | grep mpm` mostra `mpm_prefork` (succede se è installato `libapache2-mod-php`):

```bash
sudo a2dismod php8.5 mpm_prefork && sudo a2enmod mpm_event
```

## 2. Vhost iniziale (solo HTTP)

Servono già i file in `/var/www-homequest/public` (§5), altrimenti la home risponde 403.

```bash
sudo cp deploy/apache/homequest-http.conf /etc/apache2/sites-available/homequest.conf   # dal repository
sudo a2ensite homequest
sudo apache2ctl configtest && sudo systemctl reload apache2

curl -sI http://homequest.deplot.xyz/ | head -1        # → 200
```

## 3. Copia manuale dei file (alternativa alla CI)

Dalla macchina di sviluppo, dalla root del repository:

```bash
# app web
(cd frontend && npm ci && npm run build)
rsync -az --delete frontend/build/ deploy@homequest.deplot.xyz:/var/www-homequest/public/

# backend con le sole dipendenze di produzione
docker exec -e XDEBUG_MODE=off -w /app hq_httpd composer install --no-dev --optimize-autoloader
rsync -az --delete \
  --exclude /.env --exclude /config/params-local.php \
  --exclude /runtime/ --exclude /web/uploads/ --exclude /web/assets/ \
  --exclude /tests/ --exclude /codeception.yml --exclude /phpstan.neon --exclude /web/index-test.php \
  backend/ deploy@homequest.deplot.xyz:/var/www-homequest/backend/
docker exec -e XDEBUG_MODE=off -w /app hq_httpd composer install      # rimette le dipendenze di sviluppo
```

## 4. Certificato HTTPS e vhost definitivo

```bash
# certificato con webroot (Apache resta acceso, nessuna modifica automatica ai file)
sudo certbot certonly --webroot -w /var/www/certbot \
  -d homequest.deplot.xyz --email info@deplot.xyz --agree-tos --no-eff-email

# vhost definitivo
sudo cp deploy/apache/homequest.conf /etc/apache2/sites-available/homequest.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

**Rinnovo automatico**: il pacchetto `certbot` installa `certbot.timer`. Perché Apache carichi il certificato rinnovato:

```bash
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-apache.sh > /dev/null <<'EOF'
#!/bin/sh
systemctl reload apache2
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-apache.sh
sudo certbot renew --dry-run      # prova
```

**Verifica finale**

```bash
curl -sI http://homequest.deplot.xyz | grep -i location          # → https://...
curl -s https://homequest.deplot.xyz/api/v1/health               # → status ok, db true
curl -sI https://homequest.deplot.xyz/ | grep -i strict-transport
curl -s https://homequest.deplot.xyz/.env | head -c 30           # → HTML dell'app, mai il file
```

Poi apri `https://homequest.deplot.xyz` nel browser: compare la pagina di accesso. Dal telefono, "Aggiungi a schermata Home" installa la PWA.

## 5. CI/CD: cosa fanno i job

Due job della pipeline GitLab, solo sul branch `main`, stage `deploy` in `.gitlab-ci.yml`:

1. **`build-web`** (manuale, `node:22-alpine`): esegue `npm ci && npm run build` in `frontend/` e salva l'artifact `frontend/build/`.
2. **`deploy`** (parte da solo dopo `build-web`, immagine `yiisoftware/yii2-php:8.5-apache`):
   - esegue `composer install --no-dev --optimize-autoloader` in `backend/`;
   - fa `rsync --delete` di `backend/` verso `/var/www-homequest/backend/`, con le esclusioni elencate sotto;
   - fa `rsync --delete` di `frontend/build/` verso `/var/www-homequest/public/`.

La CI **cambia solo questi file**. Non tocca:
- Apache e PHP-FPM;
- i certificati;
- il database: niente migration, si lanciano a mano (`08-deploy.md` §3);
- `.env`, `runtime/` e le foto.

PHP rilegge i file modificati da solo (OPcache controlla le date ogni 2 s), quindi non serve alcun riavvio.

**Variabili CI/CD** (Settings → CI/CD → Variables, *protected* + *masked*):

| Variabile | Valore |
|---|---|
| `SSH_PRIVATE_KEY` | chiave privata di una coppia creata apposta (`ssh-keygen -t ed25519 -C gitlab-deploy`). La **pubblica** va in `/home/deploy/.ssh/authorized_keys` |
| `DEPLOY_HOST` | `homequest.deplot.xyz` |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_PATH` | *(facoltativa)* cartella del progetto, default `/var/www-homequest` |

### GitHub Actions: deploy automatico al push su `main`

Il repository è anche su GitHub: `.github/workflows/deploy.yml` fa lo stesso deploy, ma **automatico a ogni push su `main`** (o a mano da Actions → deploy → *Run workflow*).

1. **`backend`** e **`frontend`**: gli stessi test della pipeline GitLab (stesse immagini, MySQL di servizio `hq_mysql`).
2. **`deploy`** parte solo se entrambi passano: build dell'app, `composer install --no-dev`, poi i due `rsync` con le stesse esclusioni del job GitLab. Due deploy non girano mai in parallelo (`concurrency`).

Configurazione (Settings → Secrets and variables → Actions; conviene metterli nell'environment `production`, che si può proteggere con approvazione obbligatoria):

| Nome | Tipo | Valore |
|---|---|---|
| `SSH_PRIVATE_KEY` | secret | chiave privata di una coppia creata apposta (`ssh-keygen -t ed25519 -C github-deploy`); la pubblica in `/home/deploy/.ssh/authorized_keys` |
| `DEPLOY_HOST` | variable (o secret) | `homequest.deplot.xyz` |
| `DEPLOY_USER` | variable (o secret) | `deploy` |
| `DEPLOY_PATH` | variable | *(facoltativa)* default `/var/www-homequest` |

Come per GitLab: niente migration automatiche. Se un commit ne contiene, lanciale sul server subito dopo il deploy (`08-deploy.md` §3). Usare **una sola** delle due CI per il deploy, per non pubblicare due volte.

### Cartelle sul server

| Percorso | Copiato dalla CI? | Contenuto / note |
|---|---|---|
| `/var/www-homequest/public/` | ✅ sostituito per intero | build statica dell'app web (docroot di Apache) |
| `/var/www-homequest/backend/` | ✅ sorgenti + `vendor/` | senza `tests/`, `codeception.yml`, `phpstan.neon`, `web/index-test.php` |
| `backend/.env` | ❌ mai | impostazioni e segreti, creati a mano (`08-deploy.md` §2) |
| `backend/config/params-local.php` | ❌ mai | facoltativo, solo sul server |
| `backend/runtime/` | ❌ mai | log, cache dello schema, email in file |
| `backend/web/uploads/` | ❌ mai | foto delle ricette (incluse nel backup giornaliero) |

Le esclusioni proteggono questi percorsi anche da `--delete`. **Qualunque altro file messo a mano** in `public/` o `backend/` viene invece cancellato al deploy successivo.

### Dove stanno le altre cose

| Cosa | Dove |
|---|---|
| Database | MySQL locale, `/var/lib/mysql` |
| Backup | `/var/backups/homequest` (14 giorni) |
| vhost Apache | `/etc/apache2/sites-available/homequest.conf`, copiato a mano da `deploy/apache/` |
| Pool PHP-FPM | `/etc/php/8.5/fpm/pool.d/homequest.conf` |
| Certificati | `/etc/letsencrypt/live/homequest.deplot.xyz/` |
| Log | `08-deploy.md` §5 |

### Tornare a una versione precedente

Rilancia `build-web` → `deploy` dalla pipeline del commit precedente (CI/CD → Pipelines); su GitHub fai *Re-run* del workflow `deploy` di quel commit (Actions), oppure fai la copia manuale del §3 da quel commit. Le migration non vengono annullate in automatico: se la versione precedente non è compatibile con lo schema, ripristina anche il dump del giorno (`08-deploy.md` §4).

## 6. Problemi frequenti

| Sintomo | Causa probabile | Verifica / rimedio |
|---|---|---|
| **503 Service Unavailable** su `/api` | PHP-FPM fermo o socket assente | `systemctl status php8.5-fpm`, `ls /run/php/homequest-fpm.sock`, `homequest.error.log` |
| `Primary script unknown` nell'error log | sul socket risponde un servizio PHP-FPM diverso da `php8.5-fpm` col pool `homequest` | `ls /etc/php/8.5/fpm/pool.d/homequest.conf`, `systemctl status 'php*-fpm'` (deve girare solo `php8.5-fpm`) |
| `/icons/...` → 404 | `Alias /icons/` globale di Apache (`mods-enabled/alias.conf`) | le icone dell'app stanno in `/app-icons/` |
| **403** sulla home | `public/` vuota o non leggibile | primo deploy fatto? `ls /var/www-homequest/public/index.html` |
| **401 con token valido** | header `Authorization` non passato a PHP | `CGIPassAuth On` nel blocco `<Files index.php>` del vhost |
| **500** su `/api` | errore applicativo o permessi | `backend/runtime/logs/app.log`, `php-error.log`; `runtime/` e `web/uploads/` scrivibili da `deploy` |
| `/api/v1/health` → `db: false` | credenziali o MySQL | `DB_*` in `backend/.env`; `sudo systemctl status mysql` |
| Errori "Unknown column" dopo un deploy | migration non lanciate | `php yii migrate` + `php yii cache/flush-schema db` (`08-deploy.md` §3) |
| Foto ricetta rifiutata | oltre 5 MB | limite dell'API; PHP accetta fino a 6 MB (`upload_max_filesize` nel pool) |
| Certificato non rinnovato | timer o hook | `systemctl list-timers \| grep certbot`; `sudo certbot renew --dry-run` |
| App non aggiornata dopo il deploy | cache del service worker | si aggiorna alla riapertura dell'app (`index.html` e `service-worker.js` sono `no-cache`) |
| Il job CI fallisce su SSH | chiave o host | chiave pubblica in `authorized_keys` di `deploy`; `ssh deploy@homequest.deplot.xyz` dalla propria macchina |
