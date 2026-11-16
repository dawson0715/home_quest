# 03 — Database (MySQL 8)

Convenzioni: tabelle al singolare in `snake_case`; PK `id INT UNSIGNED AUTO_INCREMENT`; `created_at`/`updated_at` **INT (unix timestamp)** gestiti da `TimestampBehavior` (come nel backend esistente); date di calendario `DATE`, altri istanti di dominio `DATETIME` in ora **Europe/Rome** (fuso unico dell'app); FK con `ON DELETE CASCADE` verso `family` (la cancellazione della famiglia rimuove tutto); engine InnoDB; `utf8mb4_unicode_ci`.

## Diagramma (sintesi)

```
user ─< family_member >─ family ─< child_profile
                            │            │
                            │            ├─< task_assignee >─ task ─< task_checklist_item
                            │            ├─< task_occurrence (task, child, due_date)
                            │            ├─< point_transaction
                            │            ├─< reward_redemption >─ reward
                            │            └─< child_badge >─ badge
                            ├─< device
                            ├─< recipe ─< recipe_ingredient >─ ingredient
                            ├─< meal_plan_week ─< meal_slot ─< meal_entry (recipe | testo)
                            │                                  └─< meal_vote
                            └─< shopping_list ─< shopping_item
```

## 1. Identità e famiglia

### `user` (solo adulti) — **tabella esistente**
Già creata dalla migration `m260726_170006_create_user_table` (resta: è l'unica migration Chicco che si conserva in M0).

| colonna | tipo | note |
|---|---|---|
| id | INT PK | |
| username | VARCHAR(64) UNIQUE | |
| auth_key | VARCHAR(32) | |
| access_token | VARCHAR(64) UNIQUE | Bearer token statico (vedi `02-architettura.md` §5) |
| status | SMALLINT DEFAULT 10 | 10 attivo, 0 disattivato |
| created_at, updated_at | INT | |

Nota: `id` è `INT` con segno (`primaryKey()` di Yii), quindi le FK verso `user` (`user_id`, `created_by`, `reviewed_by`…) vanno dichiarate `INT` e non `INT UNSIGNED`.

`name VARCHAR(80) NULL` (nome visualizzato) aggiunto in M1.1 (`m261116_120000_create_family_tables`); `email VARCHAR(190) NULL UNIQUE` aggiunta in M7 (solo per il riepilogo settimanale, impostata da console). `email`, `password_hash`, `email_verified_at` solo se in futuro si introduce login/registrazione.

### `family`
| id | PK |
|---|---|
| name | VARCHAR(80) |
| settings | JSON NULL | solo le chiavi modificate; il model espone i valori effettivi (default + override) e rifiuta chiavi sconosciute. Default: `{ "allow_penalties": false, "weekly_email": false, "meal_types": ["breakfast","lunch","snack","dinner"], "week_starts_on": 1, "shopping_poll_seconds": 5 }` |
| created_at, updated_at | |

### `family_member`
| id | PK |
|---|---|
| family_id | FK family (CASCADE) |
| user_id | FK user (INT con segno, CASCADE) |
| role | ENUM('admin','coparent') |
| created_at, updated_at | INT |
| UNIQUE(user_id) | un utente appartiene a **una sola** famiglia: la famiglia corrente si ricava dal token |

### `family_invitation`
`id, family_id, email, token_hash CHAR(64), role, expires_at, accepted_at NULL, created_by FK user` — rimandata finché non esiste login via API: in fase attuale i co-genitori si aggiungono da console.

### `child_profile`
| colonna | tipo | note |
|---|---|---|
| id | PK | |
| family_id | FK | |
| user_id | INT NULL UNIQUE FK user | valorizzato = **profilo di gioco di un genitore** (creato in automatico per ogni `family_member`); NULL = bambino. Gli elenchi "figli" e i profili del tablet mostrano solo `user_id IS NULL` |
| name | VARCHAR(40) | |
| avatar | VARCHAR(40) | chiave predefinita: fox, bear, cat, dog, owl, panda, lion, rabbit, unicorn, dragon, robot, astronaut, dino, penguin, koala, frog (`ChildProfile::AVATARS`) |
| color | CHAR(7) | es. `#FF8A00` |
| birth_year_month | CHAR(7) NULL | `2018-05` |
| pin_hash | VARCHAR(255) NULL | |
| points_balance | INT UNSIGNED DEFAULT 0 | cache, aggiornata solo da PointsService |
| xp_total | INT UNSIGNED DEFAULT 0 | |
| level | SMALLINT UNSIGNED DEFAULT 1 | |
| current_streak | SMALLINT UNSIGNED DEFAULT 0 | |
| best_streak | SMALLINT UNSIGNED DEFAULT 0 | |
| streak_date | DATE NULL | ultimo giorno valutato da `StreakService` (idempotenza del cron) |
| can_check_shopping | BOOL DEFAULT 0 | |
| sort_order | SMALLINT DEFAULT 0 | |
| archived_at | DATETIME NULL | `DELETE /children/{id}` archivia |
| created_at, updated_at | INT | |

### `device`
`id, family_id, name VARCHAR(60), token_hash CHAR(64) UNIQUE, last_seen_at DATETIME NULL, revoked_at DATETIME NULL, created_at, updated_at` — `token_hash` = SHA-256 del token `d_…`; `last_seen_at` aggiornato al massimo ogni 5 minuti.

### `child_session`
`id, family_id, device_id FK, child_id FK, token_hash CHAR(64) UNIQUE, last_seen_at DATETIME NULL, created_at, updated_at` — un figlio entrato su un dispositivo (token `c_…`). Valida solo se il dispositivo non è revocato e il figlio non è archiviato.

### `pairing_code`
`id, family_id, code CHAR(6) UNIQUE, expires_at DATETIME, used_at DATETIME NULL, created_by FK user (INT), created_at, updated_at`

### `refresh_token` — *non in fase attuale (solo se si passa a JWT)*
`id, user_id NULL, child_id NULL, device_id NULL, token_hash CHAR(64) UNIQUE, family_chain CHAR(36), expires_at, revoked_at NULL, created_at` — indice su `family_chain` per la reuse detection.

### `push_subscription`
`id, family_id, user_id INT NULL, child_id NULL, endpoint TEXT, endpoint_hash CHAR(64) UNIQUE, p256dh VARCHAR(255), auth VARCHAR(255), created_at`

### `notification_log`
`id, kind VARCHAR(40), target VARCHAR(60), day DATE, created_at` — UNIQUE(kind, target, day): evita doppi promemoria.

## 2. Compiti e gamification

### `task`
| colonna | tipo | note |
|---|---|---|
| id | PK | |
| family_id | FK | |
| title | VARCHAR(120) | |
| description | TEXT NULL | |
| icon | VARCHAR(40) | emoji o chiave icona |
| points | SMALLINT UNSIGNED | punti spendibili |
| xp | SMALLINT UNSIGNED | default = points |
| requires_approval | BOOL DEFAULT 1 | |
| is_open | BOOL DEFAULT 0 | compito libero: vince il primo giocatore che lo completa; senza assegnatari = tutti i giocatori |
| recurrence_type | ENUM('none','daily','weekly','interval') | |
| recurrence_days | TINYINT UNSIGNED NULL | bitmask lun=1 … dom=64 |
| interval_days | SMALLINT NULL | |
| time_of_day | ENUM('morning','afternoon','evening','any') DEFAULT 'any' | |
| due_date | DATE NULL | per `none` |
| start_date | DATE | |
| end_date | DATE NULL | |
| linked_meal_slot_id | INT UNSIGNED NULL | es. "aiuta a cucinare la cena"; la FK verso `meal_slot` arriva in M5.7 |
| is_active | BOOL DEFAULT 1 | |
| created_by | FK user | |

### `task_checklist_item`
`id, task_id FK, label VARCHAR(120), icon, sort_order`

### `task_assignee`
`task_id FK, child_id FK, PRIMARY KEY(task_id, child_id)`

### `task_occurrence`
| colonna | tipo | note |
|---|---|---|
| id | PK | |
| family_id | FK | denormalizzato per scoping veloce |
| task_id | FK | |
| child_id | FK | |
| due_date | DATE | data Europe/Rome; per i compiti singoli (`none`) è la scadenza o, se assente, la data di inizio: una sola istanza per figlio |
| status | ENUM('pending','submitted','approved','rejected','missed','taken') | `taken` = compito libero completato da un altro giocatore |
| checklist_state | JSON NULL | `{ "itemId": true }` |
| submitted_at, reviewed_at | DATETIME NULL | |
| reviewed_by | FK user NULL | |
| review_note | VARCHAR(255) NULL | |
| points_awarded | SMALLINT UNSIGNED NULL | snapshot dei punti al momento |
| UNIQUE(task_id, child_id, due_date) | | idempotenza |
| INDEX(family_id, due_date, status) | | |

Transizioni: `pending → submitted → approved|rejected`; se `requires_approval=0`: `pending → approved` diretto. `rejected → submitted` consentito (riprova). A fine giornata `pending|rejected → missed` (compiti singoli solo se hanno una scadenza ed è passata).

Regole di ricorrenza (`app\services\RecurrenceRule`): `daily`; `weekly` con bitmask (lun=1 … dom=64); `interval` ogni N giorni da `start_date` (aritmetica su date in UTC, immune all'ora legale); `none` visibile da `start_date` fino alla scadenza. Le istanze si generano con `OccurrenceGenerator` (INSERT IGNORE, idempotente): dal cron, alla prima `GET /me/today` e al salvataggio di un compito (che rimuove anche le istanze *pending* non più previste).

### `point_transaction` (registro immutabile) — creata in M3 (`m261116_150000_create_task_tables`) perché l'approvazione accredita punti
`id, family_id, child_id, points INT (±), xp INT (≥0), type ENUM('task','bonus','penalty','reward','streak_bonus','badge','adjustment'), source_type VARCHAR(30) NULL, source_id INT NULL, note VARCHAR(255) NULL, created_by FK user NULL, created_at` — INDEX(child_id, created_at). Nessun update/delete: le correzioni sono nuove righe.

### `reward`
`id, family_id, title VARCHAR(120), icon, cost SMALLINT UNSIGNED, limit_per_period SMALLINT NULL, limit_period ENUM('day','week','month') NULL, stock SMALLINT NULL (scalato all'approvazione), is_active, sort_order, created_at, updated_at`

### `reward_redemption`
`id, family_id, reward_id, child_id, cost_snapshot, status ENUM('requested','approved','rejected','fulfilled'), requested_at DATETIME, reviewed_at, reviewed_by FK user (INT), note, created_at, updated_at` — i punti si scalano solo all'approvazione; le richieste in attesa "prenotano" il saldo.

### `savings_goal`
`id, family_id, child_id FK UNIQUE, reward_id FK, created_at`

### `badge` (catalogo globale, seed)
`id, code VARCHAR(40) UNIQUE, icon, rule JSON, sort_order` — es. `{ "type":"tasks_completed", "threshold":50 }`. Nome = i18n `badges.<code>`. Tipi di regola (`BadgeService`): `tasks_completed`, `streak` (miglior serie), `level`, `rewards_redeemed`, `time_of_day_completed` (+`time_of_day`), `meal_tasks_completed`. 16 badge in seed.

### `child_badge`
`child_id, badge_id, earned_at DATETIME, PRIMARY KEY(child_id, badge_id)`

### `task_template` (catalogo globale, seed)
`id, title_key UNIQUE, icon, min_age, max_age, default_points, category, time_of_day, recurrence_type, sort_order` — 42 voci in `m261116_150100_seed_task_templates`; il titolo è la chiave i18n `task_templates.<title_key>` del frontend.

## 3. Menù settimanale

### `ingredient`
| colonna | tipo | note |
|---|---|---|
| id | PK | |
| family_id | FK NULL | NULL = catalogo globale condiviso (~170 voci in seed, dati in italiano) |
| name | VARCHAR(80) | |
| default_unit | ENUM('g','kg','ml','l','pz','cucchiaio','cucchiaino','qb') | |
| shopping_category_id | FK shopping_category | reparto |
| UNIQUE(family_id, name) | | |

### `recipe`
`id, family_id, title VARCHAR(120), image_path NULL (relativo a `web/`, es. `uploads/recipes/<32 hex>.jpg`), servings TINYINT UNSIGNED DEFAULT 4, prep_minutes SMALLINT NULL, instructions TEXT NULL, tags JSON ("veloce","vegetariano","preferito_bimbi"), is_favorite BOOL, created_by FK user (INT) NULL, created_at, updated_at`

### `recipe_ingredient`
`id, recipe_id FK, ingredient_id FK, quantity DECIMAL(10,2) NULL, unit ENUM(...come sopra), note VARCHAR(80) NULL, sort_order`

### `meal_plan_week`
`id, family_id, week_start DATE NULL (primo giorno della settimana secondo `settings.week_starts_on`; NULL per i modelli), notes TEXT NULL, is_template BOOL DEFAULT 0, template_name VARCHAR(60) NULL, created_at, updated_at` — UNIQUE(family_id, week_start) (i NULL dei modelli non collidono).

### `meal_slot`
`id, week_id FK, day_index TINYINT (0–6), meal_type ENUM('breakfast','lunch','snack','dinner'), servings TINYINT UNSIGNED DEFAULT 4` — UNIQUE(week_id, day_index, meal_type). Creato al primo salvataggio dello slot; porzioni di default = genitori + figli attivi.

### `meal_entry`
`id, slot_id FK, recipe_id FK NULL (RESTRICT: MySQL non ammette CHECK su colonne con azioni FK; cancellando una ricetta `RecipeService` copia il titolo in `free_text`), free_text VARCHAR(120) NULL, status ENUM('planned','proposed','rejected') DEFAULT 'planned', proposed_by_child_id FK NULL, sort_order` — CHECK: almeno uno tra `recipe_id` e `free_text`.

### `meal_vote`
`entry_id FK, voter_type ENUM('user','child'), voter_id INT, value TINYINT (-1|1), PRIMARY KEY(entry_id, voter_type, voter_id)` — voto 0 = riga cancellata.

## 4. Lista della spesa

### `shopping_category` (reparti) — creata in M5 (`m261116_170000`) perché gli ingredienti appartengono a un reparto
`id, family_id NULL (NULL = predefiniti), code VARCHAR(40) NULL (i18n `shopping_categories.<code>` per i predefiniti), name VARCHAR(60) NULL (reparti della famiglia), icon, sort_order` — seed: frutta_verdura, pane_forno, latticini, carne_pesce, dispensa, surgelati, bevande, casa_igiene, altro.

### `shopping_list`
`id, family_id, name VARCHAR(60), is_default BOOL, linked_week_id FK meal_plan_week NULL (SET NULL), version INT UNSIGNED DEFAULT 0 (incrementata a ogni modifica → ETag), created_at, updated_at`

### `shopping_item`
| colonna | tipo | note |
|---|---|---|
| id | PK | |
| list_id | FK | |
| ingredient_id | FK NULL | |
| label | VARCHAR(120) | testo mostrato |
| quantity | DECIMAL(10,2) NULL | |
| unit | VARCHAR(12) NULL | |
| category_id | FK shopping_category | |
| source | ENUM('manual','generated') | |
| source_week_id | FK NULL | |
| checked_at | DATETIME NULL | |
| checked_by_type / checked_by_id | ENUM / INT NULL | utente o figlio |
| client_uuid | CHAR(36) NULL UNIQUE | per sync offline idempotente |
| sort_order | SMALLINT | |
| created_at, updated_at | INT | |

### `pantry_exclusion`
`list_id, ingredient_id, PRIMARY KEY(list_id, ingredient_id)` — "ce l'ho già in casa".

### `favorite_item`
`id, family_id, label, ingredient_id NULL, category_id, use_count INT DEFAULT 0` — UNIQUE(family_id, label).

### `family_category_order`
`family_id, category_id, sort_order, PRIMARY KEY(family_id, category_id)` — ordine dei reparti scelto dalla famiglia (`PUT /shopping-categories/order`).

## 5. Seed iniziali (migration `m..._seed_catalogs`)
- `shopping_category` predefinite.
- ~150 `ingredient` globali comuni in italiano con reparto.
- ~40 `task_template` per fasce d'età.
- ~15 `badge`.
- Comando `php yii seed/demo`: famiglia "Rossi" con 2 genitori (stampa i loro `access_token`), 2 figli, routine, premi, 10 ricette, una settimana di menù.
