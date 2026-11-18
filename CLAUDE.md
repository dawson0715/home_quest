# CLAUDE.md — HomeQuest

> Nome di lavoro: **HomeQuest**. Web app per famiglie che trasforma faccende e routine dei figli in un gioco a punti (stile Levelty), con in più **menù settimanale** e **lista della spesa** condivisi.

Leggi sempre questo file per primo. Per i dettagli vedi `docs/`:

| File | Contenuto |
|---|---|
| `docs/01-prodotto.md` | Visione, utenti, funzionalità, user story, regole di gioco (NON tecnico) |
| `docs/02-architettura.md` | Stack, struttura repo, autenticazione, ambienti, deploy |
| `docs/03-database.md` | Schema MySQL completo, relazioni, indici |
| `docs/04-api.md` | Contratto REST (endpoint, payload, errori) |
| `docs/05-frontend.md` | Rotte SvelteKit, store, componenti, UX bambino vs genitore |
| `docs/06-roadmap.md` | Milestone e task in ordine di implementazione |
| `docs/07-convenzioni.md` | Stile codice, git, test, definizione di "fatto" |
| `docs/08-deploy.md` | Produzione: variante Apache senza Docker (`deploy/apache/`: server, PHP-FPM e MySQL locali, `.env`, cron, backup) e variante Docker (`deploy/docker/`) |
| `docs/09-apache-e-cicd.md` | Apache2 sull'host (certbot, `/api` → Yii) e cartelle copiate dal job CI/CD |

## Origine del backend

`backend/` **non nasce da zero**: è il backend Yii2 di un progetto precedente ("Chicco", app di monitoraggio neonato) riadattato. Se ne riusa l'infrastruttura (modulo `v1`, controller base, envelope `{data, meta}`, auth Bearer, Docker). Il dominio Chicco è stato rimosso in M0.2; `checklist_item` (non scoped per famiglia) è stato eliminato in M1.3 con una migration di drop.

## Stack

- **Frontend**: SvelteKit 2 + Svelte 5 (runes), TypeScript strict, Tailwind CSS 4, PWA. Cartella `frontend/` nella root del repo (da creare in M0).
- **Backend**: Yii2 (template *basic*, solo API REST, nessuna view), PHP 8.5. Cartella `backend/`.
- **Database**: MySQL 8.0 (DB `homequest`, test `homequest_test`), charset `utf8mb4`, collation `utf8mb4_unicode_ci`. Schema solo via migration Yii.
- **Auth (fase attuale)**: Bearer token statici risolti da `app\components\ApiAuth`: utente genitore (`user.access_token`, creato da console), dispositivo (`d_…`) e sessione figlio (`c_…`), questi ultimi salvati come SHA-256. Nessuna registrazione/login via API per ora; JWT + refresh token rimandati (vedi roadmap).
- **Dev env**: Docker Compose nella root, due container: `hq_httpd` (`yiisoftware/yii2-php:8.5-apache`, `backend/` montato in `/app`, DocumentRoot `/app/web`) e `hq_mysql` (MySQL 8.0, nessun volume montato: i dati vivono solo nel container).

## Comandi

```bash
# Avvio ambiente (dalla root)
docker compose up -d

# Backend: comandi PHP sempre dentro il container.
# XDEBUG_MODE=off evita i warning di Xdebug in console (in web resta attivo per PhpStorm, porta 9009).
docker exec -e XDEBUG_MODE=off -w /app hq_httpd composer install
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii migrate --interactive=0
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii migrate/create create_xxx_table
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii user/create <username> ["Nome"]   # crea utente + stampa access_token
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii family/create "Rossi" <username>  # famiglia con l'utente come admin
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii family/add-member <id> <username> [coparent]
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii user/regenerate-token <username>
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii seed/demo                      # famiglia demo "Rossi" (stampa i token di demo-anna e demo-marco)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii cron/daily                     # istanze di oggi, missed, serie (cron orario)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii cron/minute                    # promemoria push routine (ogni minuto)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii cron/weekly                     # riepilogo email (lunedì)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii user/set-email <username> <email>
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii push/generate-keys             # chiavi VAPID → config/params-local.php (non committare)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php yii ai/check                       # prova Gemini (GEMINI_API_KEY in backend/.env, piano a pagamento)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php tests/bin/yii migrate --interactive=0  # migration sul DB di test (homequest_test)
docker exec -e XDEBUG_MODE=off -w /app hq_httpd php -d register_argc_argv=On vendor/bin/codecept run  # test (unit + functional API); Codeception needs register_argc_argv, off in the PHP 8.5 image
docker exec -e XDEBUG_MODE=off -w /app hq_httpd vendor/bin/phpstan analyse --memory-limit=1G  # analisi statica (level 6)

# Frontend (sull'host)
cd frontend && npm install
npm run dev            # http://localhost:5173 (proxy /api → http://localhost:8080)
npm run check          # svelte-check + tsc
npm run lint           # eslint + prettier
npm run test           # vitest
npm run test:e2e       # playwright (finto backend in memoria)
HQ_PARENT_TOKEN=<token demo-anna> npm run test:e2e:live   # contro il backend vero (dopo php yii seed/demo)
```

API locale: `http://localhost:8080/api/v1/` — MySQL: `localhost:3306` (root / `database`).

## Regole fondamentali (da rispettare sempre)

1. **Multi-tenant per famiglia**: ogni query su dati di dominio DEVE essere filtrata per `family_id` dell'utente autenticato. I model di dominio estendono `app\models\FamilyScopedRecord` (`find()` → `FamilyScopedQuery`, `family_id` auto-assegnato e immutabile, mai in `rules()`); nelle query usa sempre `->forFamily()`, mai `find()` nudo. La famiglia corrente è `Yii::$app->familyContext` (`getId()`, `getRole()`, `requireRole()`). `ApiActiveController` applica già lo scope a index/view/update/delete (altre famiglie → 404) quando `modelClass` è un `FamilyScopedRecord`: se sovrascrivi `prepareDataProvider`/`findModel` mantieni `->forFamily()`. Ogni endpoint nuovo ha un test di isolamento tra due famiglie.
2. **Bambini = dati di minori**: nessuna email/cognome obbligatorio per il profilo figlio, nessun tracciamento, nessun analytics di terze parti. Vedi sezione Privacy in `docs/02-architettura.md`.
3. **I punti si muovono solo tramite `PointsService`**, che scrive sempre una riga in `point_transaction` dentro una transazione DB. Mai aggiornare `child_profile.points_balance` a mano.
4. **Schema DB solo con migration** (`php yii migrate/create ...`). Mai modificare una migration già committata: creane una nuova. (Unica eccezione: la rimozione una tantum delle migration Chicco in M0, prima della prima migration HomeQuest.)
5. **Contratto API**: rotte sotto `/api/v1/` (modulo `app\modules\v1`, regole in `config/web.php`). Se cambi un endpoint aggiorna `docs/04-api.md` e i tipi in `frontend/src/lib/api/types.ts` nello stesso commit. Non esiste più uno spec OpenAPI.
6. **Envelope di risposta**: ogni risposta (successo o errore) è `{"data": ..., "meta": {...}}` tramite `app\rest\Serializer` + `beforeSend` in `config/web.php`. Gli errori stanno in `meta.errors[]` con `code` (es. `validation_failed`, `insufficient_points`), `message` e `field` opzionale. Non introdurre altri formati.
7. **Date e fuso**: fuso unico **Europe/Rome** per tutta l'app (`timeZone` in `config/web.php`, `TZ` del container MySQL). `created_at`/`updated_at` come INT unix (`TimestampBehavior`, come già nel backend); date di calendario (giorno del menù, `due_date`) come `DATE`; istanti di dominio (`submitted_at`, `checked_at`…) come `DATETIME` in ora di Roma. Nessun fuso per famiglia.
8. **Lingua**: codice, nomi DB e commenti in inglese; testi UI in italiano tramite i18n (`frontend/src/lib/i18n/it.json`), mai stringhe hard-coded nei componenti.
9. Prima di dire che un task è finito: test verdi, `npm run check` e `phpstan` puliti, doc aggiornate.

## Struttura (sintesi)

```
docker-compose.yml   # hq_httpd + hq_mysql
backend/             # montato in /app nel container
  config/            # web.php (UrlRule, envelope errori), console.php, db.php, params.php, test_db.php
  modules/v1/        # Module.php + controllers/ REST routati su /api/v1/...
  controllers/       # basi NON routate: ApiActiveController, ApiController, EnvelopeMetaTrait
  rest/              # Serializer (envelope {data, meta})
  models/            # ActiveRecord (1 per tabella)
  forms/             # form model di input/validazione (da creare)
  services/          # logica di dominio (PointsService, ShoppingListService, ...)
  components/        # ErrorCode, ApiException, ApiErrorHandler, ResponseEnvelope; FamilyScopedQuery (M1)
  commands/          # console: UserController (token), seed, cron
  migrations/
  tests/             # Codeception: unit, functional (API)
  web/               # docroot: index.php, .htaccess
frontend/            # SvelteKit (da creare in M0)
  src/routes/        # (auth) (parent) (child)
  src/lib/api/       # client fetch tipizzato
  src/lib/stores/    # stato globale (runes)
  src/lib/components/
  src/lib/i18n/
docs/
```

CI: `.gitlab-ci.yml` nella root (test backend + frontend, job `deploy` manuale da `main`); `.github/workflows/deploy.yml` (stessi test + deploy automatico a ogni push su `main`). Produzione: VPS Ubuntu senza Docker (Apache2 + PHP-FPM + MySQL locali, homequest.deplot.xyz), modelli in `deploy/apache/`; alternativa Docker in `deploy/docker/`, guide `docs/08-deploy.md` e `docs/09-apache-e-cicd.md`. Il deploy pubblica sul server reale: solo su richiesta esplicita.
