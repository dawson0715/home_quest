# 05 — Frontend SvelteKit

## Principi
- **SPA** con `adapter-static` + `fallback: 'index.html'`, `ssr = false` nel `+layout.ts` root.
- Svelte 5 **runes** (`$state`, `$derived`, `$effect`, `$props`); niente store legacy salvo necessità.
- Progetto in `frontend/` nella root del repo, creato con `sv create` (SvelteKit 3, Vite 8, Svelte 5, Tailwind 4, Vitest 4, Playwright). Config SvelteKit dentro `vite.config.ts` (niente `svelte.config.js`); alias `#lib` (subpath import di Node: `$lib` non esiste più) con **percorso completo ed estensione**, es. `import { t } from '#lib/i18n/index.ts'`, `'#lib/stores/toast.svelte.ts'`; i componenti `.svelte` si importano già con estensione. Vitest: progetto `server` (node, `*.spec.ts`) e `client` (browser Chromium, `*.svelte.spec.ts`). E2E in `src/e2e/*.e2e.ts`. Prima volta: `npx playwright install chromium`.
- in dev proxy Vite `/api` → `http://localhost:8080` (container `hq_httpd`).
- TypeScript strict; tipi API in `src/lib/api/types.ts` allineati a `docs/04-api.md`. I messaggi d'errore mostrati all'utente vengono da `it.json` in base a `meta.errors[].code`, non dal `message` del backend.
- Tailwind 4; design token (colori, raggi, spaziature) in `src/app.css` con `@theme`.
- Mobile-first; due "esperienze" distinte: **genitore** (densa, produttiva) e **figlio** (grande, colorata, poche parole).
- Accessibilità: contrasto AA, target touch ≥ 44px (figlio ≥ 64px), `prefers-reduced-motion` rispettato.

## Rotte

```
src/routes/
├── +layout.svelte / +layout.ts           # ssr=false, init i18n, carica sessione
├── (auth)/
│   ├── login/                            # genitore: incolla l'access token (creato da console), verificato con GET /me
│   └── device/                           # collegamento dispositivo figlio (codice, anche da ?code= del QR)
├── (child)/                              # guard: dispositivo collegato (deviceSession)
│   ├── profiles/                         # scelta avatar + PinPad (azzera la sessione figlio)
│   ├── today/                            # home figlio (richiede sessione figlio): TaskCard per fascia oraria, checklist a bolle, Celebration
│   ├── shop/                             # negozio premi + obiettivo + richieste (non `rewards/`: collide con il genitore)
│   ├── trophies/                         # livello, badge, streak
│   ├── kid-menu/                         # menù da oggi a fine settimana: 👍/👎 e proposta di un piatto (non `menu/`: collide)
│   └── kid-shopping/                     # solo se can_check_shopping (non `shopping/`: collide)
└── (parent)/                             # guard: role admin|coparent
    ├── dashboard/                        # oggi: da approvare, progressi figli, pasti di oggi
    ├── children/ · children/[id]/        # [id]: profilo, PIN, PointsPanel (bonus/penalità + storico punti)
    ├── tasks/ · tasks/new/ (?template=id dalla libreria) · tasks/[id]/   # assegnatari = tutti i giocatori (anche genitori), opzione "Libero"
    ├── my-game/                          # "Il mio gioco": compiti di oggi del genitore, punti, premi
    ├── approvals/                        # premi richiesti / da consegnare + compiti in attesa
    ├── rewards/                          # gestione premi (modale RewardForm)
    ├── menu/[[week]]/                    # planner: griglia 7×pasti (desktop) / un giorno per tab (mobile), SlotEditor, copia settimana, modelli, proposte dei figli, 👩‍🍳 compito collegato
    ├── recipes/ · recipes/new/ · recipes/[id]/   # RecipeForm con righe ingredienti (autocomplete), foto (multipart)
    ├── shopping/[[id]]/                  # liste (tab), QuickAdd + preferiti, reparti nell'ordine famiglia, "ce l'ho già", genera dal menù, riordino reparti
    ├── stats/
    ├── settings/family/                  # nome, impostazioni, membri, notifiche
    ├── settings/devices/                 # codice + QR di collegamento, elenco e revoca
    ├── stats/                            # 7/30 giorni: completati/saltati, %, punti, serie, barre per giorno
    └── settings/ (famiglia, membri, notifiche, privacy)
```

**Attenzione**: i gruppi `(child)` e `(parent)` non cambiano l'URL; due pagine con lo stesso percorso nei due gruppi rompono il manifest di SvelteKit (e con lui `svelte-check` e i test Vitest). Le pagine del figlio usano percorsi propri (`shop`, `trophies`, `kid-menu`, …). Il layout `(child)` ha una barra di navigazione in basso (Oggi, Premi, Trofei) visibile solo con sessione figlio.

Guard in `(child)/+layout.ts` e `(parent)/+layout.ts` (load universale, `await session.ready()`): se non autenticato o ruolo sbagliato → `redirect`. La root `/` reindirizza a `/dashboard` (genitore), `/today` (figlio), `/profiles` (dispositivo) o `/login`.

Link e navigazione: sempre `resolve()` di `$app/paths` con l'**ID della rotta** (gruppi inclusi), es. `resolve('/(parent)/dashboard')`, `resolve('/(auth)/login')` — richiesto dai tipi di SvelteKit 3 e dalla regola ESLint `svelte/no-navigation-without-resolve`.

## Layout genitore (stile Android / Material 3)
- **Schermi compatti (< `lg`)**: top app bar con ☰ e titolo della sezione; **navigation drawer modale** (`<dialog>`: focus trap, Esc, tap sul velo per chiudere) con tutte le voci; **bottom navigation bar** con le tre sezioni principali **Compiti, Menù, Spesa** (indicatore a pillola sulla voce attiva). Il contenuto ha `pb-28` per non finire sotto la barra.
- **Schermi larghi (≥ `lg`)**: drawer permanente a sinistra, niente bottom bar né ☰.
- Voci del drawer: Home, Compiti, Menù, Spesa, Da approvare, Il mio gioco · Ricette, Premi, Figli, Statistiche, Impostazioni · Esci. Le etichette sono le stesse usate dagli e2e.
- Area figlio: bottom bar con le stesse pillole Material (Oggi, Menù, Spesa se abilitata, Premi, Trofei).

## Lib

```
src/lib/
├── api/
│   ├── client.ts        # fetch wrapper: base URL `/api/v1`, Bearer, unwrap envelope {data, meta}, `meta.errors[].code` → ApiError; 401 → logout e redirect a /login
│   ├── types.ts
│   └── endpoints/       # auth.ts, tasks.ts, rewards.ts, meals.ts, shopping.ts ...
├── stores/
│   ├── session.svelte.ts   # token (localStorage `hq.token`, accessi protetti da try/catch) + `/me`; `ready()` idempotente, `login(token)`, `clear()`; collega il client (Bearer, 401 → clear). Un utente senza famiglia non può entrare (`no_family`)
│   ├── device.svelte.ts    # dispositivo dei bimbi: `hq.device` (d_…) + `hq.child` (c_…) in localStorage; pair, profiles, selectChild, switchProfile, forget. Le chiamate da dispositivo/figlio passano il token esplicito (`api.get(path, { token })`), mai quello del genitore
│   ├── toast.svelte.ts
│   └── shopping.svelte.ts  # classe ShoppingStore (una per pagina; token figlio opzionale): ETag/304, polling solo con pagina visibile, aggiornamenti ottimistici, coda offline in localStorage (`hq.shopping.queue`) inviata con /sync (client_uuid)
├── components/
│   ├── ui/              # Button, Input, Modal, Sheet, Tabs, Badge, EmptyState, Avatar, IconPicker
│   ├── child/           # TaskCard (grande), ChecklistBubbles, PointsCounter (animato), LevelBar, Celebration (confetti), RewardCard
│   ├── parent/          # TaskForm, RecurrencePicker, ApprovalRow, ChildSummaryCard
│   ├── meals/           # WeekGrid, MealSlot, RecipePicker, RecipeForm, IngredientRow, VoteButtons
│   └── shopping/        # ShoppingItemRow, CategorySection, QuickAdd, GenerateFromMenuDialog
├── i18n/ it.json, index.ts   # t(key, params), errorMessage(code); niente librerie
└── utils/ dates.ts (fuso Europe/Rome, settimana), units.ts (formattazione quantità), haptics.ts, sound.ts
```

## Flussi UX importanti

### Figlio completa un compito
1. Tap sulla card → se checklist, bolle grandi da toccare una a una.
2. Ultima bolla / tap "Fatto!" → `POST submit`.
3. Ottimismo: la card passa a "fatto" subito; se errore, rollback + toast.
4. Se `approved`: animazione monete che volano al contatore + suono; se `level_up` o `new_badges` → schermata `Celebration`.
5. Se `submitted` (in attesa): card con clessidra "Aspetta mamma/papà".

### Planner menù (genitore)
- Desktop: griglia 7 colonne × pasti attivi. Mobile: un giorno alla volta con swipe, pasti in verticale.
- Tap su slot → bottom sheet: cerca ricetta (autocomplete), testo libero, porzioni, rimuovi.
- Indicatori voti 👍/👎 e proposte dei figli (badge "proposta di Luca" con Accetta/Rifiuta).
- Barra azioni: settimana prec./succ., "Copia settimana scorsa", "Usa modello", **"Genera lista spesa"**.

### Lista spesa
- Raggruppata per reparto nell'ordine della famiglia; articoli spuntati in fondo, barrati.
- QuickAdd in alto con suggerimenti da preferiti e ingredienti.
- Polling ogni 5 s con `If-None-Match` solo quando la pagina è visibile (`document.visibilityState`).
- **Offline**: operazioni in coda (`localStorage`), applicate in locale subito, inviate con `/sync` al ritorno online (evento `online` o polling successivo); le aggiunte hanno `client_uuid` (idempotenza).
- Swipe a sinistra = elimina, tap = spunta, long-press = modifica.

## PWA
- Niente `@vite-pwa/sveltekit`: service worker nativo di SvelteKit 3 in `src/service-worker/index.ts` (con il suo `tsconfig.json` che estende `$app/tsconfig/service-worker`, controllato da `npm run check` con `tsc`). Moduli: `$app/manifest`, `$app/env`, `$app/service-worker`. Precache app shell, fallback a `index.html` per le navigazioni offline, rete-prima con copia in cache per `/api/v1/shopping-lists/*`, solo rete per il resto delle API e `/uploads`.
- Manifest `static/manifest.webmanifest`, icone in `static/app-icons/` (non `icons/`: su Debian/Ubuntu `/icons/` è un Alias globale di Apache) (any + maskable, 192/512).
- Payload push `{type, params, url}` tradotto nel service worker con `t('notifications.<type>.title|body')`; il clic apre `url`. Gli e2e bloccano i service worker (`serviceWorkers: 'block'`), altrimenti le fetch del SW sfuggirebbero ai mock.
- Push: richiesta permesso solo dopo un'azione esplicita (`PushToggle` in Impostazioni e nella pagina Oggi del figlio), mai all'avvio.

## Test
- Vitest: utils (date, unità), client API (envelope, errori), store shopping (coda offline).
- Component test (`vitest-browser-svelte`, file `*.svelte.spec.ts`): Button, TaskCard, RecurrencePicker, WeekGrid.
- Playwright E2E (`src/e2e/*.e2e.ts`) contro un finto backend in memoria (`src/e2e/mock-api.ts`, da tenere allineato a `04-api.md`): il job CI frontend non ha l'API.
- E2E live (`src/e2e-live/`, `playwright.live.config.ts`, `npm run test:e2e:live`): stesso browser contro il backend vero via dev server; richiede `HQ_PARENT_TOKEN` (token di `demo-anna` da `php yii seed/demo`). Non gira in CI.
- Flussi E2E: login con token (utente creato da `seed/demo`) → crea figlio → collega dispositivo → crea routine → figlio completa → genitore approva; menù → genera lista → spunta.
