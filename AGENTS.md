# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

This repo holds two independent projects for **Chicco**, a newborn-tracking app (feedings,
breast-milk pumping, weight, diapers) for a small family audience:

- `backend/` — REST API, Yii2 (PHP 8.1) + MySQL. See `backend/AGENTS.md` for the authoritative,
  detailed brief (stack, conventions, entity naming, Docker, common tasks) — read it before making
  backend changes, it is kept up to date and this file only summarizes it.
- `android/` — native Android client, Kotlin + Jetpack Compose (Material 3), consuming the backend
  at `https://chicco.deplot.xyz/api/v1`.

The original build prompts (`backend/prompt-backend-yii2.md`, `android/prompt-android.md`) describe
the product spec in Italian and are useful for *why* a feature exists, but the running code and
`backend/AGENTS.md` are authoritative for *what currently exists*. Table/column/enum names and all
code/comments are in English even though the spec is in Italian — see the entity naming map in
`backend/AGENTS.md`.

Neither directory is currently a git repository — there's no top-level VCS state to rely on.

---

## Backend (`backend/`)

Full details in `backend/AGENTS.md`. Key points:

- Yii2 basic template, PHP 8.1, MySQL (db name `chicco`, timezone `Europe/Rome`). Schema is managed
  exclusively via Yii2 migrations (`migrations/`) — never hand-written SQL.
- REST controllers live under the `v1` module (`modules/v1/controllers/`), routed at `/api/v1/...`
  via `yii\rest\UrlRule` in `config/web.php`. Shared base classes (`ApiActiveController`,
  `ApiController`, `EnvelopeMetaTrait`) live in `controllers/` but are not themselves routed.
- Business logic (daily milk need, chronological `ml_remaining_after` cascade, breast-milk stock
  balance) lives in `services/`, not in controllers or models:
  `FeedingCalculationService`, `PumpingStockService`, `FeedingWarningService`, `TodaySummaryService`.
- Every REST response (success, validation error, 404, unauthenticated) is wrapped in a
  `{"data": ..., "meta": ...}` envelope via `app\rest\Serializer` plus the `beforeSend` handler in
  `config/web.php`.
- Auth is `HttpBearerAuth` against the `user` table's `access_token` column — there is no
  signup/login flow; tokens are provisioned via the console command in `commands/UserController.php`.
- OpenAPI spec is hand-maintained at `web/openapi.json` (served at `/openapi.json`, with Swagger UI
  at `/docs`) — update it by hand whenever endpoints/schemas change, it is not generated.

### Running things

Run PHP/Composer/Yii commands inside the `chicco_httpd` Docker container (`docker/docker-compose.yml`)
so the PHP version/extensions match production:

```bash
docker exec -w /app chicco_httpd php yii migrate                              # apply migrations
docker exec -w /app chicco_httpd php yii migrate/create create_xxx_table      # new migration
docker exec -w /app chicco_httpd php yii user/create <username>              # provision a bearer token
docker exec -w /app chicco_httpd php yii user/regenerate-token <username>
docker exec -w /app chicco_httpd composer <command>
```

Tests use Codeception (`unit`, `functional`, `acceptance` suites):

```bash
vendor/bin/codecept run                    # unit + functional
vendor/bin/codecept run unit               # single suite
vendor/bin/codecept run unit ClassNameCest  # single test
```

Deployment is via `deploy.sh` (rsync over SSH to `remote.deplot.xyz`, mirrors the GitLab CI `deploy`
stage) — treat it as a manual, confirmation-gated operation, not something to run unprompted.

---

## Android (`android/`)

- MVVM: `ViewModel` + `StateFlow`, state hoisting into `@Composable` screens — no business logic in
  Composables. Package root: `xyz.deplot.chicco`.
- DI via Hilt (`di/NetworkModule.kt`, `di/RepositoryModule.kt`). Every feature has a repository
  interface (`data/repository/*Repository.kt`) with a real Retrofit-backed implementation
  (`data/repository/remote/`) bound in `RepositoryModule`; parallel `Mock*Repository` implementations
  backed by `FakeChiccoBackend` live under `data/repository/mock/` and remain available as a fallback
  if the real backend becomes unreachable — swapping is a one-line `@Binds` change per repository.
- Networking: Retrofit + `kotlinx.serialization` + OkHttp, base URL
  `https://chicco.deplot.xyz/api/v1/` (`di/NetworkModule.kt`). `AuthInterceptor` attaches the bearer
  token from `EncryptedTokenStorage` (`androidx.security.crypto`) to every request.
  `SafeApiCall`/`ChiccoResult` wrap network calls so screens get a typed success/error result instead
  of exceptions.
- Screens (one package per feature under `ui/`): `oggi` (today dashboard, backed by
  `GET /riepilogo-oggi`), `poppate` (feedings), `tiraggio` (pumping), `peso` (weight), `pannolini`
  (diapers) — plus shared nav chrome in `ui/navigation/` (`ChiccoNavHost.kt`, bottom bar + quick-add
  sheet) and reusable input widgets in `ui/components/` (`QuarterHourTimeField`, `NumberStepperField`,
  `StatCard`).
- Data models under `data/model/` must mirror the backend's field names/enums exactly (`type`:
  `formula`/`breast_milk`/`breast`; `side`: `left`/`right`/`both`; diaper `type`: `pee`/`poop`/`mixed`)
  — check `backend/AGENTS.md`'s entity naming map and the relevant model/controller in `backend/` when
  in doubt, not just the Kotlin data class.
- Custom theme roles (`ui/theme/CustomColors.kt`) extend Material 3's `ColorScheme` rather than using
  hardcoded colors in Composables — e.g. a `tertiary` role for pumping/water and a custom `warm`
  (orange) role for diapers.
- Time-of-day inputs across all forms are constrained to quarter hours (0/15/30/45 minutes) via
  `QuarterHourTimeField`/`TimeUtils.kt`, mirroring the DB-level CHECK constraint on the backend —
  don't introduce a generic time picker that allows other minute values.

### Building

Standard Gradle wrapper from `android/`:

```bash
./gradlew assembleDebug
./gradlew test                          # JVM unit tests
./gradlew connectedAndroidTest          # instrumented tests (needs device/emulator)
./gradlew testDebugUnitTest --tests "xyz.deplot.chicco.SomeTest"   # single test
```

Kotlin 2.2.10, AGP 9.2.1, compileSdk 36, minSdk 24. Compose BOM, Hilt, KSP, and kotlinx-serialization
plugins are all wired through the version catalog (`android/gradle/libs.versions.toml`) — add new
dependencies there, not as inline coordinates in `app/build.gradle.kts`.
