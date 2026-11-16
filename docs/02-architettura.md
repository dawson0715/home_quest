# 02 — Architettura tecnica

> Il backend riusa il progetto Yii2 esistente "Chicco" (vedi `CLAUDE.md` → *Origine del backend*). Le scelte qui sotto riflettono quell'infrastruttura; dove differiscono dal progetto originale di HomeQuest è indicato.

## 1. Cosa serve (prerequisiti)

### Strumenti di sviluppo
| Strumento | Versione | Note |
|---|---|---|
| Docker + Docker Compose | 24+ / v2 | `docker-compose.yml` nella root |
| PHP | 8.3 (nel container `yiisoftware/yii2-php:8.3-apache`) | estensioni già incluse nell'immagine; Xdebug attivo (porta 9009, server PhpStorm `hq.local`) |
| Composer | 2.x | dentro il container |
| Node.js | 22 LTS | + npm, sull'host (non containerizzato) |
| MySQL | 8.0 | container `hq_mysql` |
| Git | | monorepo |
| Claude Code | ultima | lavora leggendo `CLAUDE.md` e `docs/` |

### Pacchetti principali
**Backend (composer)**: `yiisoftware/yii2` ~2.0.45; dev: Codeception 5 + moduli `yii2`, `rest`, `asserts`, `filesystem`; `phpstan/phpstan` ^2 + `yii2-extensions/phpstan` (configurazione di analisi in `config/phpstan.php`; `proget-hq/phpstan-yii2` non supporta PHPStan 2); `fakerphp/faker`. Più avanti, quando servono: `yiisoft/yii2-symfonymailer` (M7), `minishlink/web-push` (M7), `firebase/php-jwt` (solo se si passa a JWT).

**Frontend (npm)** — i18n senza librerie (modulo interno `#lib/i18n/index.ts`: `svelte-i18n` scartato perché trascina un `esbuild` 0.19 vulnerabile e l'app è solo in italiano): `@sveltejs/kit`, `svelte@5`, `typescript`, `tailwindcss@4`, `@vite-pwa/sveltekit`, `zod` (validazione form), `uqr` (QR code SVG, zero dipendenze); la coda offline della spesa usa `localStorage` (niente `idb-keyval`: poche operazioni, nessuna dipendenza); dev: `vitest` (+ `vitest-browser-svelte` per i component test in Chromium), `playwright`, `eslint`, `prettier`.

### Servizi per la produzione
- Hosting PHP + MySQL con Apache (come il progetto Chicco) oppure VPS con Docker.
- Dominio + HTTPS (Let's Encrypt).
- SMTP transazionale (Brevo, Postmark, SES) — solo da M7.
- Chiavi VAPID per web push (generate con `minishlink/web-push`).
- Storage immagini: filesystem locale in MVP, S3-compatibile dopo.
- Backup giornaliero MySQL (mysqldump + retention 14 gg).
- Error tracking self-hosted o EU (es. GlitchTip / Sentry EU) — **senza dati personali dei minori**.

## 2. Vista d'insieme

```
[Browser/PWA genitore] ─┐
                        ├─ HTTPS ─> [Apache] ─┬─> /          SvelteKit (build statica, adapter-static, SPA fallback)
[Browser/PWA figlio] ───┘                     └─> /api/v1/*  Yii2 REST (mod_php) ──> MySQL 8
                                                                  │
                                                                  ├─> SMTP (M7)
                                                                  └─> Web Push (VAPID, M7)
[cron] ── php yii cron/daily, cron/minute
```

Scelte:
- **SPA** (adapter-static): niente server Node in produzione. SSR non serve (app dietro login).
- **API stateless con Bearer token**; stesso dominio in produzione. In dev il frontend gira su `localhost:5173` e il proxy Vite inoltra `/api` a `http://localhost:8080`, quindi niente CORS da configurare (il filtro `Cors` accetta solo le origini in `params['corsOrigins']`).
- **Tempo reale** lista spesa: MVP con polling ogni 5 s quando la lista è aperta + `If-None-Match`/ETag (304 se invariata). Evoluzione: SSE o Mercure.

## 3. Struttura repository (monorepo)

```
home_quest/
├── CLAUDE.md
├── docker-compose.yml           # hq_httpd (Apache+PHP 8.3, backend/ → /app) + hq_mysql
├── docs/
├── backend/                     # app Yii2 basic, docroot web/
│   ├── config/ web.php console.php db.php test_db.php params.php test.php
│   ├── modules/v1/
│   │   ├── Module.php
│   │   └── controllers/         # TaskController, ChildProfileController, ShoppingListController, ...
│   ├── controllers/             # basi NON routate: ApiActiveController, ApiController, EnvelopeMetaTrait
│   ├── rest/Serializer.php      # envelope {data, meta}
│   ├── components/
│   │   ├── FamilyScopedQuery.php   # ActiveQuery con ->forFamily()
│   │   ├── ErrorCode.php           # codici errore stabili
│   │   ├── ApiException.php        # eccezione con code di dominio → meta.errors[].code
│   │   ├── ApiErrorHandler.php     # eccezioni → envelope
│   │   └── ResponseEnvelope.php    # beforeSend: rete di sicurezza per errori non incapsulati
│   ├── models/                  # ActiveRecord
│   ├── forms/                   # TaskForm, ...
│   ├── services/
│   │   ├── PointsService.php
│   │   ├── OccurrenceGenerator.php   # crea istanze dalle routine
│   │   ├── StreakService.php
│   │   ├── BadgeService.php
│   │   ├── MealPlanService.php
│   │   ├── ShoppingListService.php   # generazione + aggregazione ingredienti
│   │   ├── UnitConverter.php
│   │   └── NotificationService.php
│   ├── commands/                # UserController (token), SeedController, CronController
│   ├── migrations/
│   ├── tests/                   # Codeception: unit/, functional/ (test API), _data/fixtures
│   └── web/                     # index.php, .htaccess
└── frontend/                    # SvelteKit (da creare in M0)
    ├── src/routes/ ...   (vedi 05-frontend.md)
    ├── src/lib/ ...
    └── static/ (icone PWA, suoni)
```

## 4. Backend Yii2 — linee guida

- Template **basic** già configurato come sola API: `request.parsers` JSON, `response.format = json`, `enablePrettyUrl`, `baseUrl = ''`.
- Rotte: modulo `v1`, `yii\rest\UrlRule` con `'prefix' => 'api'` in `config/web.php` → `/api/v1/<risorsa plurale>`. Endpoint non-CRUD (azioni come `submit`, `approve`, `generate`) come regole esplicite (`'POST api/v1/occurrences/<id:\d+>/submit' => 'v1/occurrence/submit'`), ricordando la regola `OPTIONS` corrispondente per la preflight.
- Controller: estendono `app\controllers\ApiActiveController` (CRUD) o `app\controllers\ApiController` (azioni custom). Entrambi hanno già `Cors` + `HttpBearerAuth` (con `OPTIONS` escluso) e il serializer envelope. Se `modelClass` estende `FamilyScopedRecord`, `ApiActiveController` usa già `findFamilyModel` / `prepareFamilyDataProvider` (scope famiglia, 404 per le altre) e `checkAccess` richiede l'appartenenza a una famiglia (`403 no_family` altrimenti); i controlli di ruolo vanno aggiunti sovrascrivendo `checkAccess` (`familyContext->requireRole(...)`).
- Multi-tenancy: componente `familyContext` (`app\components\FamilyContext`) = membro/famiglia dell'utente autenticato; `FamilyScopedQuery::forFamily(?id)`; base `app\models\FamilyScopedRecord` (assegna `family_id` alla creazione, ne impedisce il cambio).
- **Logica nei service**, non nei controller né nei model. Controller = input → form → service → serializzazione.
- Serializzazione con `fields()`/`extraFields()` sui model; `?expand=` per relazioni.
- Paginazione: `ActiveDataProvider`; i dati di paginazione finiscono in `meta.pagination` (`totalCount`, `pageCount`, `currentPage`, `perPage`), oltre agli header `X-Pagination-*` standard di Yii.
- Metadati extra di risposta (es. `points_awarded`, `level_up`) via `$this->responseMeta` (`EnvelopeMetaTrait`) o direttamente nel payload `data`, secondo `04-api.md`.
- Errori: `ApiException(code, message, httpStatus)` lanciata dai service → `meta.errors[{code, message, field?}]`. Gli errori di validazione dei model diventano `code: "validation_failed"` con `field`.
- Transazioni DB per ogni operazione che tocca punti, premi o genera liste.
- Rate limiting (`yii\filters\RateLimiter`) su pairing e PIN.

## 5. Autenticazione e autorizzazione

### Fase attuale (riuso del backend esistente)
- `HttpBearerAuth` contro `user.access_token` (token casuale da 48 caratteri, univoco). Header: `Authorization: Bearer <token>`.
- **Nessuna registrazione/login via API**: gli utenti (adulti) e i loro token si creano da console:
  `php yii user/create <username>` / `php yii user/regenerate-token <username>`.
- Il legame utente → famiglia passa da `family_member` (un utente = una famiglia); la famiglia corrente si ricava dall'utente autenticato. Console: `php yii user/create <username> ["<nome visualizzato>"]`, `php yii family/create "<nome famiglia>" <username>` (l'utente diventa admin), `php yii family/add-member <family_id> <username> [admin|coparent]`, `php yii family/list`.
- Il frontend salva il token in `localStorage` dopo che l'utente lo incolla nella schermata di accesso (o lo legge da un link di attivazione). Non c'è refresh: un 401 riporta alla schermata di accesso.

### Figlio (dispositivo collegato)
Tre tipi di Bearer token, distinti dal prefisso e risolti da `app\components\ApiAuth`:

| Token | Prefisso | Salvato come | Attore (`familyContext->getRole()`) | Può fare |
|---|---|---|---|---|
| utente | nessuno | in chiaro (`user.access_token`, legacy) | `admin` / `coparent` | tutto ciò che è da genitore |
| dispositivo | `d_` | SHA-256 (`device.token_hash`) | `device` | `GET /me`, `GET /family`, `GET /devices/profiles`, `POST /devices/select-child` |
| sessione figlio | `c_` | SHA-256 (`child_session.token_hash`) | `child` | le azioni da figlio (M3+) |

1. Genitore: `POST /api/v1/devices/pairing-code` → codice 6 caratteri (alfabeto senza 0/O/1/I), scade in 15 min, monouso.
2. Dispositivo: `POST /api/v1/devices/pair {code, device_name}` → crea `device` e restituisce il `device_token` (mostrato una sola volta).
3. Dispositivo: `GET /api/v1/devices/profiles` (Bearer = device token) → lista avatar dei figli.
4. Dispositivo: `POST /api/v1/devices/select-child {child_id, pin?}` (Bearer = device token) → verifica PIN (max 5 errori per figlio e dispositivo ogni 10 min → 429) e restituisce un `child_token` (`child_session`). Le chiamate da figlio usano quel token; "cambia profilo" = il client scarta il `child_token` e torna al device token. *(Scelto al posto dell'header `X-Child-Id` ipotizzato prima: con un header il PIN si potrebbe aggirare.)*
5. Il genitore può revocare un dispositivo in qualsiasi momento (`DELETE /devices/{id}`): device token e relative sessioni figlio smettono subito di funzionare. Anche archiviare un figlio invalida le sue sessioni.

Autorizzazione nei controller: `ApiActiveController` è **solo genitori** di default; le azioni consentite ai figli vanno elencate in `$childActions`. Nei controller custom si usa `familyContext->requireParent()` / `requireRole(...)`.


### Evoluzione futura (non pianificata ora)
Registrazione/login email+password, verifica email, reset password, JWT access (15 min) + refresh token ruotato con reuse detection. Se e quando si fa, va aggiunto come milestone dedicata in roadmap.

### Permessi (RBAC semplificato)
| Azione | admin | coparent | child |
|---|---|---|---|
| Gestire famiglia/membri/dispositivi | ✅ | ✅ (no elimina famiglia) | ❌ |
| CRUD compiti, premi | ✅ | ✅ | ❌ |
| Completare propria istanza | — | — | ✅ (solo proprie) |
| Approvare compiti/premi | ✅ | ✅ | ❌ |
| Richiedere premio | — | — | ✅ |
| Menù: modifica | ✅ | ✅ | ❌ |
| Menù: vota / proponi | ✅ | ✅ | ✅ |
| Lista spesa: modifica | ✅ | ✅ | ❌ |
| Lista spesa: spunta | ✅ | ✅ | se `child_profile.can_check_shopping` |

## 6. Logiche di dominio chiave

### Generazione istanze routine
- `task` con `recurrence_type` (`none|daily|weekly|interval`) + `recurrence_days` (bitmask lun=1…dom=64) + `interval_days`.
- `php yii cron/daily` gira ogni ora (idempotente): crea le `task_occurrence` di oggi (data Europe/Rome) per tutte le famiglie (indice unico `task_id, child_id, due_date`).
- Anche on-demand: alla prima `GET /api/v1/me/today` se il cron non ha ancora generato.
- Istanze non completate a fine giornata → `status=missed` (serve per streak e statistiche).

### Punti
- `PointsService::award(child, points (± ), xp, type, sourceType, sourceId, note, createdBy)` (spesa = punti negativi) e `::adjust(...)` per bonus/malus: transazione, `SELECT ... FOR UPDATE` sul profilo, insert `point_transaction`, aggiorna `points_balance`/`xp_total`/`level`. Chi accredita chiama poi `BadgeService::evaluate()`.
- Livelli: per passare da n a n+1 servono 100×n XP (livello L parte da 50·L·(L−1) XP).
- Serie (`StreakService`, dal cron per il giorno precedente, idempotente con `child_profile.streak_date`): giorno perfetto = tutte le istanze ricorrenti `approved` o `submitted`; giorni senza routine non contano; bonus punti+XP a 3/7/14/30 giorni (10/25/50/100).
- Saldo mai negativo (errore 422 `insufficient_points`).

### Lista spesa da menù
1. Prendi tutti gli `meal_entry` della settimana con `recipe_id`.
2. Per ogni ricetta: fattore = `meal_slot.servings / recipe.servings`.
3. Per ogni `recipe_ingredient`: quantità × fattore, normalizza unità con `UnitConverter` (g↔kg, ml↔l; `pz`, `q.b.` non convertibili).
4. Raggruppa per `ingredient_id + unità_base`; somma.
5. Escludi ingredienti in `pantry_exclusion` per quella lista.
6. Upsert in `shopping_item` con `source=generated` e `source_week_id` = settimana (chiave: ingrediente + unità base): rigenerare **non** tocca gli articoli manuali né quelli già spuntati; aggiorna le quantità dei generati non spuntati e rimuove quelli non più necessari. Solo i piatti `planned` (non le proposte).
7. Arrotonda in modo leggibile (es. 1.250 kg → "1,25 kg"; 3,4 pz → 4 pz).

## 7. Privacy e sicurezza (GDPR, minori)
- Profilo figlio: solo nome/soprannome, avatar, anno-mese di nascita opzionale. Niente foto reali obbligatorie.
- Il genitore è il titolare del consenso; checkbox informativa alla registrazione.
- Export dati famiglia (JSON) e cancellazione definitiva (`DELETE /api/v1/family` con conferma: nome della famiglia digitato) → hard delete immediato a cascata (anche account genitori e foto ricette), `FamilyDataService`. Pagina `settings/privacy` con informativa.
- Nessun analytics di terze parti; log applicativi senza payload personali (`logVars => []`: niente `$_SERVER`/`$_POST` nei log, quindi né token né dati dei bambini).
- OWASP: query sempre parametrizzate (ActiveRecord), validazione input sui form, upload immagini con verifica MIME e ri-codifica.
- Header di sicurezza: ogni risposta API ha `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `CSP: default-src 'none'; frame-ancestors 'none'`, `Cache-Control: no-store` (`ResponseEnvelope`); `/uploads/` servito con `nosniff` + CSP `sandbox` (`backend/web/.htaccess`). Frontend: CSP in `<meta>` generata da SvelteKit (`kit.csp`, mode `hash`) e `frontend/static/.htaccess` (fallback SPA, HSTS su HTTPS, cache immutabile per `/_app/immutable/`).
- CORS: solo le origini in `params['corsOrigins']` (in dev i server Vite 5173/4173); in produzione app e API stanno sulla stessa origine.
- Rate limit: pairing (10/10 min per IP), PIN (5 errori/10 min per figlio+dispositivo), token non validi (20/10 min per IP → 429).
- Permessi: `SecurityCest` verifica ogni endpoint da genitore contro token figlio (403) e anonimo (401), e gli endpoint da figlio contro il genitore (403).


## 8. Ambienti e configurazione

Dev: `docker compose up -d` dalla root.

| Servizio | Container | Host | Note |
|---|---|---|---|
| API (Apache + PHP 8.3) | `hq_httpd` | `http://localhost:8080` | `backend/` montato in `/app`, DocumentRoot `/app/web` |
| MySQL 8.0 | `hq_mysql` | `localhost:3306` | root / `database`, DB `homequest` (+ `homequest_test` per i test), `TZ=Europe/Rome`, nessun volume montato: `docker compose down` (o la ricreazione del container) azzera il DB → rilanciare `migrate` (e `seed/demo`) |
| Frontend | — (host) | `http://localhost:5173` | `npm run dev`, proxy `/api` → `:8080` |

Configurazione backend: file PHP in `backend/config/` (come nel progetto originale; in produzione in più `backend/.env`). I segreti stanno in `config/params-local.php` (non committato, letto da `params.php`): chiavi VAPID (`php yii push/generate-keys` stampa il file da copiare) e in futuro il DSN SMTP. Senza chiavi VAPID le push sono semplicemente disattivate (`/push/vapid-key` → `null`). Le credenziali di produzione non vanno committate: stanno in `backend/.env` sul server, escluso dalla sincronizzazione insieme a `params-local.php`.

Frontend: `PUBLIC_API_BASE=/api/v1`.

Email: `yiisoft/yii2-symfonymailer`; senza `params['mailerDsn']` i messaggi finiscono in `runtime/mail` (nessun container SMTP in dev). Il riepilogo settimanale (`cron/weekly`, lunedì mattina) va solo alle famiglie con `settings.weekly_email = true` e ai genitori con email (`php yii user/set-email <username> <email>`). Il testo delle email è in italiano nei template `backend/mail/` (eccezione alla regola i18n: le email non passano dal frontend).

Fuso orario: unico, **Europe/Rome** (`timeZone` in `config/web.php`, `date_default_timezone_set`, `TZ` di MySQL). Non esiste un fuso per famiglia.

## 9. CI e deploy (MVP)
- CI: `.gitlab-ci.yml` nella root, stage `test`: `backend` (immagine `yiisoftware/yii2-php:8.3-apache` + servizio `mysql:8.0` con alias `hq_mysql`: migrate su `homequest_test`, codecept, phpstan) e `frontend` (immagine `mcr.microsoft.com/playwright:v<versione di @playwright/test>-noble`: lint, check, vitest, e2e). Aggiornando Playwright va aggiornato anche il tag dell'immagine.
- Deploy: stage `deploy`, job **manuale** `build-web` da `main` (build del frontend) → `deploy` (composer `--no-dev`, rsync di `backend/` e della build in `/var/www-homequest/public` sulla VPS Ubuntu, variabile `DEPLOY_PATH`). La CI copia solo file: migration a mano. Server, cron, backup in **`docs/08-deploy.md`**, Apache e cartelle in **`docs/09-apache-e-cicd.md`**.
- Produzione **senza Docker**, su un'unica origine `https://homequest.deplot.xyz`: Apache2 sull'host serve l'app da `public/`, `/api/*` a `backend/web/index.php` via PHP-FPM, `/uploads/*` statici; MySQL locale.
- Configurazione di produzione in `backend/.env` (mai committato, modello `backend/.env.example`, caricato da `config/env.php`): `HQ_ENV=prod`, `DB_*`, `COOKIE_KEY`, `VAPID_*`, `MAILER_*`, `TRUSTED_PROXIES` (vuoto: nessun proxy); in sviluppo non c'è `.env` e valgono i default di `config/*.php` e `params-local.php`.
