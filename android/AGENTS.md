# Chicco Android

Native Android client (Kotlin + Jetpack Compose, Material 3) for **Chicco**, tracking a newborn's
feedings, breast-milk pumping, weight, and diapers. Consumes the Yii2 REST backend in the sibling
`backend/` project (see `backend/AGENTS.md`) at `https://chicco.deplot.xyz/api/v1`.

The functional spec lives in `prompt-android.md` and stays in Italian. Code, comments, and UI
strings that aren't user-facing Italian labels follow the spec's screen names in Italian too — the
package/route names (`oggi`, `poppate`, `tiraggio`, `peso`, `pannolini`) intentionally match the
Italian domain vocabulary used on the backend side, not translated English names.

## Stack & conventions

- Kotlin 2.2.10, Jetpack Compose (Material 3), single module `app/`, package root
  `xyz.deplot.chicco`, `applicationId` same.
- **MVVM**: `ViewModel` + `StateFlow`, state hoisting into `@Composable` screens — no business logic
  inside Composables.
- **Hilt** for DI (`di/NetworkModule.kt`, `di/RepositoryModule.kt`), **Retrofit** +
  `kotlinx.serialization` (not Moshi/Gson) for HTTP, **Navigation Compose** for routing.
- Every feature is behind a repository interface in `data/repository/*Repository.kt`, with a real
  implementation in `data/repository/remote/` currently bound via `@Binds` in
  `RepositoryModule.kt`. Parallel `Mock*Repository` implementations backed by
  `data/repository/mock/FakeChiccoBackend.kt` still exist as a fallback for working on the UI
  offline or if the real backend is unreachable — swapping is a one-line `@Binds` change per
  repository, don't delete the mocks when touching a repository.
- Single-child assumption: there is no child-switcher UI, so every `child_id`-scoped call uses
  `DEFAULT_CHILD_ID` from `data/repository/Constants.kt`. Don't add multi-child plumbing unless
  asked — it would need backend support too.
- Network errors are normalized through `safeApiCall` (`data/remote/SafeApiCall.kt`), which wraps
  Retrofit suspend calls into a `ChiccoResult<T>` (`data/ChiccoResult.kt`) distinguishing
  `NO_CONNECTION` / `TIMEOUT` / `SERVER` / `UNAUTHORIZED` / `UNKNOWN`. New repository methods should
  go through `safeApiCall` rather than letting exceptions escape to the ViewModel.
- Auth: `AuthInterceptor` attaches the bearer token to every Retrofit request from
  `EncryptedTokenStorage` (`androidx.security.crypto`, `data/auth/`). There's no signup flow — the
  user pastes a token provisioned server-side (`ui/auth/TokenEntryScreen.kt`); `SessionViewModel`
  just tracks logged-in/out state and is checked in `MainActivity.ChiccoApp()` to decide between
  `TokenEntryScreen` and the main scaffold.
- Data models under `data/model/` must mirror the backend's field names/enums exactly — see the
  entity naming map in `backend/AGENTS.md`. `type` on feedings: `formula`/`breast_milk`/`breast`;
  `side`: `left`/`right`/`both`; diaper `type`: `pee`/`poop`/`mixed`. When a field looks off, check
  the backend model/controller, not just the Kotlin data class.
- Theme roles extend Material 3's `ColorScheme` via `ui/theme/CustomColors.kt`
  (`LocalCustomColors`), not hardcoded `Color(...)` in Composables — e.g. `tertiary` for
  pumping/water, a custom `warm` (orange) role for diapers.
- All time-of-day inputs are constrained to quarter hours (0/15/30/45) via the shared
  `ui/components/QuarterHourTimeField.kt` + `util/TimeUtils.kt` — this mirrors a DB-level CHECK
  constraint on the backend. Don't swap in a generic time picker that allows other minute values.

## Screens (`ui/`)

One package per bottom-nav destination, each with a `*Screen.kt` (Composable) + `*ViewModel.kt`:

- `oggi` — home/dashboard, backed by `GET /riepilogo-oggi` (`TodaySummaryRepository`).
- `poppate` — feedings: form (type segmented button, quarter-hour time, ml stepper or
  side/duration for breast) + today's list.
- `tiraggio` — pumping: stock hero card + form + today's sessions.
- `peso` — weight: trend + form + history with delta vs. previous entry.
- `pannolini` — diapers: quick tiles (pee/poop/mixed) + today's count + list.

Shared nav chrome lives in `ui/navigation/` (`ChiccoNavHost.kt`: bottom bar, per-screen FAB/quick-add
bottom sheet, `ChiccoDestination.kt` for the route enum). Reusable input widgets live in
`ui/components/` (`QuarterHourTimeField`, `NumberStepperField`, `StatCard`, `NetworkAwareContent`
for the offline/error state UI).

**Not yet implemented** despite being in `prompt-android.md`: the feeding reminder alarm
(`AlarmManager` + `SCHEDULE_EXACT_ALARM` + notification channel). There's no alarm-related code and
no matching permission in `AndroidManifest.xml` yet.

## Common tasks

```bash
./gradlew assembleDebug                                             # build debug APK
./gradlew test                                                      # JVM unit tests
./gradlew connectedAndroidTest                                      # instrumented tests (device/emulator)
./gradlew testDebugUnitTest --tests "xyz.deplot.chicco.SomeTest"     # single test
```

Dependency versions are centralized in `gradle/libs.versions.toml` — add new deps there, not as
inline coordinates in `app/build.gradle.kts`. Current baseline: Kotlin 2.2.10, AGP 9.2.1,
compileSdk 36, minSdk 24, Compose BOM 2025.12.00, Hilt 2.60.1.

## Directory layout

```
data/model/          Data classes mirroring backend REST resources (Child, Feeding, Pumping, Weight, Diaper, TodaySummary)
data/remote/          ChiccoApiService (Retrofit interface), ApiEnvelope, AuthInterceptor, SafeApiCall
data/repository/       Repository interfaces + Constants (DEFAULT_CHILD_ID)
data/repository/remote/ Real Retrofit-backed implementations (bound by default)
data/repository/mock/  Fake implementations backed by FakeChiccoBackend (fallback / offline UI work)
data/auth/             TokenStorage interface + EncryptedTokenStorage impl
di/                    Hilt modules: NetworkModule (Retrofit/OkHttp/Json), RepositoryModule (bindings)
ui/<feature>/          Screen + ViewModel per bottom-nav destination (oggi, poppate, tiraggio, peso, pannolini)
ui/auth/               TokenEntryScreen/ViewModel, SessionViewModel (logged-in/out gate)
ui/navigation/         ChiccoNavHost, ChiccoDestination, bottom bar + quick-add sheet
ui/components/         Shared input/state widgets used across feature screens
ui/theme/              Material 3 theme, CustomColors (tertiary/warm roles), Shapes, Type
util/                  TimeUtils (quarter-hour helpers)
```
