# 04 — API REST (v1)

Base URL: `/api/v1` (modulo Yii `v1`, `UrlRule` con prefisso `api`; risorse al plurale). JSON in/out. Autenticazione: `Authorization: Bearer <access_token>` salvo dove indicato. Tutti gli ID di risorse appartengono alla famiglia del token: se no → **404** (mai 403, per non rivelare l'esistenza).

Tutti i path delle tabelle sotto sono relativi a `/api/v1`.

## Envelope di risposta
Ogni risposta, di successo o di errore, ha la forma:
```json
{ "data": <oggetto | array | null>, "meta": { } }
```
- Successo: `data` = risorsa o lista; `meta` = `{}` oppure info aggiuntive (es. `pagination`, `points_awarded`).
- Liste paginate: `?page=1&per-page=20`; in `meta.pagination` → `{ "totalCount": 42, "pageCount": 3, "currentPage": 1, "perPage": 20 }` (anche header `X-Pagination-*`).

## Formato errori
```json
{
  "data": null,
  "meta": {
    "errors": [
      { "code": "validation_failed", "field": "title", "message": "Title cannot be blank." },
      { "code": "validation_failed", "field": "points", "message": "Points must be an integer." }
    ]
  }
}
```
- `code` sempre presente (stringa stabile, da usare nel frontend per tradurre il messaggio in italiano); `field` solo per errori di validazione; `message` è indicativo, non mostrarlo così com'è all'utente.
- Codici HTTP: 400 richiesta malformata, 401 token mancante/non valido, 403 ruolo non autorizzato, 404, 409 conflitto (es. versione lista), 422 validazione/regola di dominio, 429 rate limit.
- Codici generici (derivati dallo status): `bad_request`, `unauthorized`, `forbidden`, `not_found`, `conflict`, `rate_limited`, `server_error`.
- `no_family` (403): utente autenticato ma non collegato a nessuna famiglia.
- Codici dominio ricorrenti: `validation_failed`, `insufficient_points`, `invalid_transition`, `reward_limit_reached`, `pairing_code_invalid`, `pin_invalid`.

> Implementazione: codici in `app\components\ErrorCode`; eccezioni di dominio con `ApiException(code, message, status=422, field?)`; rendering in `ApiErrorHandler` (eccezioni), `rest\Serializer` (validazione e risposte vuote con status ≥ 400), `ResponseEnvelope` (rete di sicurezza in `beforeSend`). In `YII_DEBUG` per gli errori interni c'è anche `meta.debug` (tipo, file, riga).

Legenda ruoli: **P** = genitore (admin/coparent), **A** = solo admin, **C** = figlio.

## Sistema
| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /health | pubblico | `{status: "ok"\|"degraded", db: bool, time}`; 503 se il DB non risponde |

## Auth (fase attuale)
Nessun endpoint di registrazione/login: utenti e token si creano da console (`php yii user/create <username>`, vedi `02-architettura.md` §5). Login via email/password e JWT sono rimandati.

| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /me | P/C | `{user: {id, username, name, email}, family: Family\|null, role: "admin"\|"coparent"\|null, player: Child\|null}` (`player` = profilo di gioco del genitore). `family`/`role` null se l'utente non è in nessuna famiglia. Usato dal frontend per validare il token inserito. |
| PATCH | /me | P | `{name?, email?\|null, avatar?, color?}` → come `GET /me`. Aggiornamento parziale dei dati del genitore: `name` (max 40) va sia in `user.name` sia nel suo profilo di gioco, `avatar`/`color` nel profilo di gioco, `email` (unica, `""`/null la rimuove) serve al riepilogo settimanale. Con device/child token → 403 `forbidden`. |

## Famiglia, membri, dispositivi
| Metodo | Path | Ruolo |
|---|---|---|
| GET | /family | P → `Family` = `{id, name, settings}` (settings effettivi: default + override) |
| PATCH | /family | P `{name?, settings?}`: `settings` è **unito** a quelli esistenti; chiavi ammesse `allow_penalties` (bool), `weekly_email` (bool, riepilogo settimanale ai genitori con email), `meal_types` (sottoinsieme non vuoto di breakfast/lunch/snack/dinner), `week_starts_on` (1\|7), `shopping_poll_seconds` (2–60), `ai_suggestions` (bool, suggerimenti menù con IA); chiavi sconosciute → 422 |
| DELETE | /family | A `{confirm_name}` (= nome famiglia, altrimenti 422) → 204: cancellazione **immediata** e definitiva di tutti i dati, degli account dei genitori e delle foto |
| GET | /family/export | A → tutti i dati della famiglia in JSON (header `Content-Disposition: attachment`), senza token, hash di PIN o chiavi |
| GET | /family/members | P → `[{id, user_id, username, name, role}]` (non paginato) |
| DELETE | /family/members/{id} | A |
| GET | /children | P → `Child[]` attivi ordinati per `sort_order, id` (solo bambini); `?archived=1` → solo archiviati; `?players=1` → tutti i giocatori (bambini + profili di gioco dei genitori, `is_adult: true`) per assegnatari e statistiche |
| POST | /children | P `{name, avatar, color, birth_year_month?, can_check_shopping?, sort_order?}` → 201 `Child` |
| GET/PATCH | /children/{id} | P |
| DELETE | /children/{id} | P → 204, archivia (`archived_at`); invalida le sessioni figlio |
| PUT | /children/{id}/pin | P `{pin: "1234"\|null}` → `Child` |

`Child` = `{id, name, avatar, color, birth_year_month, has_pin, points_balance, xp_total, level, current_streak, best_streak, can_check_shopping, sort_order, archived, is_adult}`. I profili di gioco dei genitori si possono vedere, modificare e ricevere bonus (`/transactions`, `/adjust`) ma non archiviare né avere un PIN (404). Punti/XP/livello/streak non sono scrivibili via API. `avatar` ∈ `ChildProfile::AVATARS`, `color` = `#RRGGBB`, `birth_year_month` = `AAAA-MM`.
| POST | /devices/pairing-code | P → 201 `{code, expires_at}` (6 caratteri, alfabeto senza 0/O/1/I, 15 min, monouso) |
| POST | /devices/pair | pubblico `{code, device_name}` → 201 `{device_token, device}`. `code` ignora maiuscole, spazi e trattini. Codice errato/scaduto/usato → 422 `pairing_code_invalid`; 10 tentativi per IP ogni 10 min → 429 `rate_limited` |
| GET | /devices/profiles | Bearer = device token → `[{id, name, avatar, color, has_pin}]` (figli non archiviati) |
| POST | /devices/select-child | Bearer = device token, `{child_id, pin?}` → 201 `{child_token, child: Child}`. PIN errato → 422 `pin_invalid` (field `pin`); 5 errori per figlio+dispositivo in 10 min → 429. Figlio di altra famiglia/archiviato → 404 |
| GET | /devices | P → `[{id, name, last_seen_at, created_at, revoked}]` (solo attivi, più recenti prima) |
| DELETE | /devices/{id} | P → 204, revoca (device token e sessioni figlio non funzionano più) |

`GET /me` con device token → `{device: {id, name}, family, role: "device"}`; con child token → `{child: Child, family, role: "child"}`. Con un device/child token gli endpoint da genitore rispondono 403 `forbidden`.

## Compiti
| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /tasks | P | `?child_id=&active=1\|0` → `Task[]` (non paginato) |
| POST | /tasks | P | vedi payload sotto → 201 `Task`; genera subito le istanze di oggi |
| GET/PATCH | /tasks/{id} | P | PATCH parziale; `assignee_ids` e `checklist` se presenti sostituiscono (voci con `id` = aggiornate, senza = nuove, mancanti = eliminate) |
| DELETE | /tasks/{id} | P | 204: eliminato se senza storico, altrimenti `is_active=false` |
| GET | /task-templates | P | `?age=7` → `[{id, title_key, icon, min_age, max_age, default_points, category, time_of_day, recurrence_type}]` |
| GET | /occurrences | P | `?date=2026-11-16&child_id=&status=submitted[,rejected]` → `Occurrence[]` con `child` (`{id,name,avatar,color,has_pin}`); senza `date` = oggi, salvo se c'è `status` (tutte le date). `limit` (1–500, default 500); `sort=recent` ordina per `submitted_at` decrescente (home "Ultimi completati"), altrimenti `due_date` desc + `submitted_at` asc |
| GET | /me/today | C/P | istanze di oggi del giocatore (figlio, o il genitore col suo profilo di gioco), raggruppate per `time_of_day`; i compiti singoli restano finché aperti; le istanze `taken` non compaiono |
| PATCH | /occurrences/{id}/checklist | C/P (solo proprie) | `{item_id, done}` → `Occurrence` |
| POST | /occurrences/{id}/submit | C/P (solo proprie, di oggi) | compito libero già completato da un altro → 409 `task_taken` (o 422 `invalid_transition` se la tua istanza è già `taken`); checklist incompleta → 422 `checklist_incomplete`; risposta `{occurrence, points_awarded, level_up, balance, new_badges}`: `approved` subito se il compito non richiede approvazione |
| POST | /occurrences/{id}/approve | P | `{note?}` → come submit; solo da `submitted`, altrimenti 422 `invalid_transition` |
| POST | /occurrences/{id}/reject | P | `{note?}` (max 255) → `Occurrence` `rejected` (il figlio può riprovare in giornata) |
| POST | /occurrences/bulk-approve | P | `{ids:[]}` (max 200) → `{approved: [ids], skipped: [ids]}` |

`Occurrence` = `{id, task_id, child_id, due_date, status, title, icon, points, time_of_day, requires_approval, checklist: [{id, label, icon, done}], submitted_at, reviewed_at, review_note, points_awarded}`. Le transizioni bloccano la riga (`FOR UPDATE`): un doppio submit/approve non accredita due volte. Istanze di altri giorni → 422 `invalid_transition`.

`Task` = `{id, title, description, icon, points, xp, requires_approval, is_open, recurrence_type, recurrence_days, interval_days, time_of_day, due_date, start_date, end_date, is_active, assignee_ids, checklist: [{id, label, icon}]}`. Default: `xp = points`, `requires_approval = true`, `start_date = oggi`, `time_of_day = any`. Campi di ricorrenza non pertinenti vengono azzerati. `assignee_ids`: almeno un giocatore non archiviato della famiglia (bambini o genitori); può essere vuoto solo se `is_open` (= tutti i giocatori, anche quelli aggiunti in seguito). Compito libero: al submit del primo giocatore le istanze degli altri diventano `taken`; se il genitore rifiuta tornano `pending`. Checklist max 30 voci.

`Task` ha anche `linked_meal_slot_id` (compito "aiuta a cucinare" collegato a uno slot del menù della famiglia; vale per il badge `chef_helper`).

Payload `POST /tasks`:
```json
{
  "title": "Routine del mattino", "icon": "🌅", "points": 10, "xp": 10,
  "requires_approval": false,
  "recurrence_type": "weekly", "recurrence_days": 31, "time_of_day": "morning",
  "start_date": "2026-11-16", "end_date": null,
  "assignee_ids": [7, 8],
  "checklist": [ {"label":"Lavarsi i denti","icon":"🪥"}, {"label":"Vestirsi","icon":"👕"} ]
}
```

## Punti, premi, badge
| Metodo | Path | Ruolo |
|---|---|---|
| GET | /children/{id}/transactions | P → `PointTransaction[]` più recenti prima, paginato (`?page=&per-page=`, default 20) |
| GET | /me/transactions | C → idem, i propri |
| POST | /children/{id}/adjust | P `{type:"bonus"\|"penalty", points (1–1000), xp?, note (obbligatoria)}` → 201 `{child, transaction, level_up}`. Penalty: punti negativi, niente XP, solo se `settings.allow_penalties` (altrimenti 422 `penalties_disabled`); mai sotto zero (`insufficient_points`). Bonus: `xp` default = punti |
| GET | /rewards | P → `Reward[]` (`?active=1\|0`); C, o P con `?view=shop` (il genitore che gioca) → solo attivi come `ShopReward` (`+ affordable, available, is_goal`) |
| POST | /rewards | P `{title, icon, cost (1–10000), limit_per_period?, limit_period? (day\|week\|month, entrambi o nessuno), stock?, is_active?, sort_order?}` → 201 |
| PATCH | /rewards/{id} | P |
| DELETE | /rewards/{id} | P → 204, eliminato se mai richiesto, altrimenti disattivato |
| POST | /rewards/{id}/redeem | C/P (giocatore) → 201 `Redemption` `requested`. Controlli: attivo (`reward_unavailable`), stock e limite per periodo (`reward_limit_reached`, periodo corrente in Europe/Rome, settimana da lunedì), saldo meno richieste in attesa ≥ costo (`insufficient_points`) |
| GET | /redemptions | P `?status=requested[,approved]`; C vede le proprie → `Redemption[]` (max 200) |
| POST | /redemptions/{id}/approve | P `{note?}` → `{redemption, new_badges}`: scala i punti (ledger `reward`), decrementa lo stock, rimuove l'obiettivo di risparmio se era quel premio |
| POST | /redemptions/{id}/reject | P `{note?}` → nessun addebito |
| POST | /redemptions/{id}/fulfill | P → da `approved` a `fulfilled` (premio consegnato) |
| PUT | /me/savings-goal | C `{reward_id\|null}` → `{savings_goal}` (anche in `GET /me/today`: `{reward_id, title, icon, cost, progress 0–1}`) |
| GET | /badges | C: i propri; P: `?child_id=` obbligatorio → `[{code, icon, earned_at\|null}]` (catalogo completo, nome = i18n `badges.<code>`) |

`Redemption` = `{id, reward_id, child_id, title, icon, cost, status, requested_at, reviewed_at, note, child}`. `PointTransaction` = `{id, child_id, points, xp, type, source_type, source_id, note, created_at}`. Le risposte di submit/approve compiti includono `new_badges: [{code, icon}]`.
| GET | /stats | P `?from=&to=&child_id=` (default ultimi 7 giorni, max 92) → `{from, to, children: [{child, tasks: {approved, missed, rejected, submitted, pending}, completion_rate (approved/(approved+missed), null se nessuna), points_earned, points_spent, current_streak, best_streak, per_day: [{date, approved, missed}]}]}` |

## Menù settimanale
| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /meal-plans/{week_start} | P/C | `week_start` = primo giorno della settimana (`settings.week_starts_on`), altrimenti 422. Crea la settimana se assente → `MealPlan` |
| PATCH | /meal-plans/{week_start} | P | `{notes}` → `MealPlan` |
| PUT | /meal-plans/{week_start}/slots/{day}/{meal_type} | P | `{servings? (1–30), entries:[{id?, recipe_id?\|free_text?}]}` (max 6) → `MealSlot`. Sostituisce i piatti `planned` (con `id` = mantenuti, voti inclusi); le proposte dei figli restano |
| POST | /meal-plans/{week_start}/copy-from | P | `{source_week_start \| template_id}` → `MealPlan`; sostituisce i piatti `planned` della settimana |
| POST | /meal-plans/{week_start}/save-as-template | P | `{name}` → 201 `{id, name}` |
| POST | /meal-plans/{week_start}/suggest | P | `{note? (max 200)}` → `{source: "ai"\|"local", fallback_reason: null\|"disabled"\|"not_configured"\|"rate_limited"\|"error", suggestions: [{day_index, date, meal_type, recipe_id, recipe: RecipeSummary\|null, free_text, title, reason (testo IA)\|null, reason_code: "favorite"\|"kids_favorite"\|"liked"\|"not_recent"\|"quick"\|"habit"\|null}]}`. Solo per gli slot dei pasti attivi **senza** piatti `planned`; **non salva nulla** (il client usa PUT slot). L'IA non disponibile non è un errore: si ripiega sull'algoritmo locale |
| GET | /meal-templates | P | `[{id, name, dishes}]` |
| DELETE | /meal-templates/{id} | P | 204 |
| POST | /meal-entries/{id}/vote | P/C | `{value: 1\|-1\|0}` (0 = rimuovi) → `MealEntry` |
| POST | /meal-plans/{week_start}/proposals | C | `{day, meal_type, recipe_id?\|free_text}` → 201 `MealEntry` `proposed`; una proposta in attesa per figlio e pasto (409 `conflict`) |
| POST | /meal-entries/{id}/accept · /reject | P | da `proposed` a `planned`/`rejected` → `MealEntry` (i `rejected` non compaiono nel piano) |
| GET | /recipes | P/C | `?q=&tag=&favorite=1` → `RecipeSummary[]` (preferiti prima, max 500) |
| POST | /recipes | P | `{title, servings?, prep_minutes?, instructions?, tags?, is_favorite?, ingredients?: [{ingredient_id?\|name?, quantity?, unit, note?}]}` → 201 `Recipe`. Un `name` sconosciuto crea un ingrediente della famiglia (reparto "altro") |
| GET/PATCH/DELETE | /recipes/{id} | P | PATCH: `ingredients` se presente sostituisce le righe. DELETE: i piatti già pianificati restano come testo libero |
| POST | /recipes/{id}/image | P | multipart, campo `image`, max 5 MB, jpeg/png/webp → `Recipe`; ri-codificata in JPEG max 1600 px (via EXIF/GPS). Errori: 422 `image_invalid` |
| DELETE | /recipes/{id}/image | P | → `Recipe` senza immagine |
| GET | /ingredients | P | `?q=` autocomplete (globali + famiglia, prima i match per prefisso, max 20) → `[{id, name, default_unit, shopping_category_id, is_custom}]` |
| POST | /ingredients | P | `{name, default_unit, shopping_category_id?}` ingrediente della famiglia → 201 |
| GET | /shopping-categories | P | reparti (globali + famiglia) → `[{id, code, name, icon, sort_order}]` |

`MealPlan` = `{id, week_start, notes, meal_types, slots: MealSlot[]}` con uno slot per giorno × pasto attivo (`id` null finché non salvato). `MealSlot` = `{id, day_index, date, meal_type, servings, entries}`. `MealEntry` = `{id, recipe_id, recipe: RecipeSummary\|null, free_text, title, status, proposed_by: {id,name,avatar,color,has_pin}\|null, votes: {up, down, mine}}`. `RecipeSummary` = `{id, title, image_url, tags, is_favorite, prep_minutes}`; `Recipe` aggiunge `servings, instructions, ingredients: [{id, ingredient_id, name, quantity, unit, note}]`. Le immagini sono servite da `/uploads/recipes/…` (stessa origine; in dev il proxy Vite inoltra `/uploads`).

## Lista della spesa
| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /shopping-lists | P/C* | `[{id, name, is_default, version, items_count, open_count}]`; crea la lista "Spesa" (default) se la famiglia non ne ha |
| POST | /shopping-lists | P | `{name}` → 201 `ShoppingList` |
| GET | /shopping-lists/{id} | P/C* | `ShoppingList`; header `ETag: "v{version}"`; con `If-None-Match` uguale → **304** senza corpo |
| PATCH | /shopping-lists/{id} | P | `{name}` |
| DELETE | /shopping-lists/{id} | P | 204; la lista di default non si elimina (422) |
| POST | /shopping-lists/{id}/generate | P | `{week_start}` → lista + `meta: {added, updated, removed}` (vedi `02-architettura.md` §6) |
| POST | /shopping-lists/{id}/items | P | `{label?, ingredient_id?, quantity?, unit?, category_id?, client_uuid?}` → 201 lista. Reparto: dato, o dell'ingrediente, o del preferito con la stessa etichetta, o "altro". Stesso `client_uuid` = nessun duplicato |
| PATCH | /shopping-items/{id} | P | `{label?, quantity?, unit?, category_id?}` → lista |
| POST | /shopping-items/{id}/check · /uncheck | P/C* | → lista (registra chi ha spuntato) |
| DELETE | /shopping-items/{id} | P | → lista |
| POST | /shopping-lists/{id}/clear-checked | P | → lista + `meta.removed` |
| POST | /shopping-lists/{id}/sync | P/C* | batch offline (max 200): `{ops:[{op:"add"\|"check"\|"uncheck"\|"delete", client_uuid?, item_id?, data?, at}]}` applicate in ordine; `add` idempotente su `client_uuid`, operazioni su articoli inesistenti ignorate; i figli possono solo check/uncheck → lista + `meta: {applied, skipped}` |
| PUT/DELETE | /shopping-lists/{id}/pantry/{ingredient_id} | P | esclusione "ce l'ho" (PUT rimuove anche gli articoli generati non spuntati di quell'ingrediente) → lista |
| GET | /shopping-categories | P | nell'ordine della famiglia |
| PUT | /shopping-categories/order | P | `{ids:[...]}` → `{ids}` (ordine risultante) |
| GET | /favorite-items | P | top 30 per uso → `[{id, label, ingredient_id, category_id, use_count}]` (aggiornati a ogni aggiunta manuale) |

`ShoppingList` = `{id, name, is_default, version, linked_week_id, items: ShoppingItem[], pantry: [ingredient_id]}`; articoli ordinati: non spuntati prima, poi per reparto (ordine famiglia), poi inserimento. `ShoppingItem` = `{id, ingredient_id, label, quantity, unit, category_id, source, checked, checked_at, client_uuid}`. Ogni modifica incrementa `version`.

\* C solo se `can_check_shopping`.

## Notifiche
| Metodo | Path | Ruolo | Note |
|---|---|---|---|
| GET | /push/vapid-key | pubblico | `{public_key}` (`null` = push non configurato sul server) |
| POST | /push/subscribe | P/C | `{endpoint (https), keys:{p256dh, auth}}` → 201. Lo stesso endpoint ri-registrato da un altro attore (es. un altro figlio sul tablet) cambia proprietario |
| DELETE | /push/subscribe | P/C | `{endpoint}` → 204 |

Payload push (JSON): `{type, params, url}`; il service worker compone il testo da `it.json` → `notifications.<type>`. Tipi: `task_submitted` `{name, title}` e `reward_requested` `{name, title}` e `dish_proposed` `{name, title}` (ai genitori), `routine_reminder` `{name, count, time_of_day}` (al figlio, `cron/minute` alle 07:30 / 16:30 / 19:00 per le routine ancora aperte di quella fascia, una volta al giorno). Le iscrizioni scadute (404/410) vengono cancellate.

## Esempio risposta `GET /me/today`
```json
{
  "date": "2026-11-16",
  "child": { "id": 7, "name": "Luca", "points_balance": 140, "level": 3, "xp_total": 520, "xp_level_start": 300, "xp_next_level": 600, "current_streak": 4, "…": "altri campi di Child" },
  "groups": {
    "morning": [ { "id": 991, "task_id": 12, "title": "Routine del mattino", "icon": "🌅", "points": 10, "status": "pending",
                   "checklist": [ {"id":1,"label":"Lavarsi i denti","icon":"🪥","done":true} ] } ],
    "afternoon": [], "evening": [], "any": []
  },
  "savings_goal": { "reward_id": 3, "title": "Scegli la cena", "cost": 200, "progress": 0.7 }
}
```
