# 07 — Convenzioni

## Generali
- Codice, identificatori, commenti e messaggi di commit in **inglese**; documentazione e testi UI in **italiano**.
- Niente codice morto o commentato; niente TODO senza riferimento a un task della roadmap.
- Non aggiungere dipendenze non elencate in `02-architettura.md` senza motivarlo nel commit e aggiornare la doc.

## PHP / Yii2
- PSR-12; `declare(strict_types=1);` in ogni file **nuovo** (il codice ereditato da Chicco non lo ha: aggiungerlo quando si tocca un file); tipi su parametri, ritorni e proprietà.
- Namespace: `app\modules\v1\controllers` (controller routati), `app\controllers` (solo basi), `app\models`, `app\forms`, `app\services`, `app\components`, `app\rest`, `app\commands`.
- Model ActiveRecord: `tableName()`, `rules()`, relazioni `getXxx()`, `fields()` per l'output; nessuna logica di dominio.
- Service: classi senza stato, dipendenze via costruttore o `Yii::$container`; metodi pubblici che lanciano `app\components\ApiException` con `code` di dominio (default 422), resa come `meta.errors[{code, message}]`.
- Controller: azioni corte; validazione tramite form model; risposta = model/array/DataProvider.
- Migration: una per modifica logica, nome descrittivo (`m261116_100000_create_task_tables`); sempre `safeUp/safeDown` reversibili; FK e indici espliciti.
- PHPStan livello 6 senza baseline nuove.

## TypeScript / Svelte
- Prettier: 2 spazi, single quote, punto e virgola, niente virgola finale, printWidth 100 (`prettier.config.js`, con plugin Tailwind per l'ordine delle classi).
- Componenti in `PascalCase.svelte`; un componente = una responsabilità; props tipizzate con `$props<...>()`.
- Nessuna chiamata `fetch` diretta nei componenti: solo tramite `src/lib/api/endpoints/*`.
- Stringhe UI solo via `t('chiave')` (`#lib/i18n/index.ts`); chiavi gerarchiche in `snake_case` separate da punti (es. `child.today.empty`). Errori API: `errorMessage(code)`, ogni codice di `ErrorCode` del backend deve avere la chiave `errors.<code>` (verificato da `i18n.spec.ts`).
- Componenti UI: solo token semantici di `app.css` (`bg-brand`, `text-ink`, `border-line`…), mai colori Tailwind grezzi.
- Date: sempre tramite `utils/dates.ts` (fuso famiglia).

## Git
- Branch: `main` protetto; feature branch `feat/M3.2-occurrence-generator`, `fix/...`.
- Conventional Commits: `feat(tasks): generate daily occurrences`, `fix(shopping): keep checked items on regenerate`.
- PR piccole, descrizione con task roadmap e come testare.

## Test
- Backend: unit test per ogni service; test API per ogni endpoint (felice + errori + **isolamento famiglia** + **ruolo non autorizzato**).
- Frontend: unit per utils/store; component test per componenti con logica; E2E per i flussi critici.
- Fixture con Faker; nessun test dipende dalla data reale (iniettare un `Clock`, fuso Europe/Rome).
- Test API nella suite Codeception `functional` (moduli Yii2 + REST, URL base `/api/v1/`, ogni test in transazione), su DB `homequest_test`; eseguirli dentro il container `hq_httpd`. Helper in `tests/_support/FunctionalTester.php`: `amAuthenticatedAs()`, `seeErrorEnvelope()`.

## Definizione di "fatto"
1. Funzionalità implementata come da doc.
2. Test scritti e verdi (`codecept run`, `npm run test`).
3. `phpstan`, `npm run check`, `npm run lint` puliti.
4. Doc aggiornate (API, DB, roadmap spuntata).
5. Testi UI in `it.json`; schermata usabile da mobile.
