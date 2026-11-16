# 06 — Roadmap di implementazione

Ogni milestone termina con: test verdi, demo funzionante con `seed/demo`, doc aggiornate. Lavorare **un task alla volta**, in ordine; ogni task = un commit (o una PR piccola).

## M0 — Fondamenta (adattamento del backend esistente)
- [x] M0.1 `docker-compose.yml` nella root: `hq_httpd` (Apache + PHP 8.3, `backend/` → `/app`) + `hq_mysql` (MySQL 8.0, DB `homequest`); `config/db.php` e `test_db.php` puntati a `hq_mysql`; rimossi OpenAPI/Swagger
- [x] M0.2 Pulizia dominio Chicco: rimuovere model/controller/service/migration di `child`, `weight`, `feeding`, `pumping`, `diaper`, `today-summary` (+ `TimeOfDayTrait`, `DailyNeedUnavailableException`), le relative regole in `config/web.php`, `commands/HelloController`, i test del template (Contact/Login/About/Alert). Tenere: migration `user`, `checklist_item` (fino a M6, poi sostituita), basi controller, `rest/Serializer`. Ricreare il DB di sviluppo da zero
- [x] M0.3 Errori con `code`: `components/ApiException`, mapping status → code generico in `beforeSend`, `validation_failed` + `field` nel `Serializer`; endpoint `GET /api/v1/health` (pubblico)
- [x] M0.4 Codeception (unit + functional API) su DB `homequest_test` e PHPStan livello 6 (`phpstan/phpstan` 2, `yii2-extensions/phpstan`); primi test su `/health`, su envelope di errore e su 401
- [x] M0.5 `frontend/` nella root: SvelteKit + TS + Tailwind 4 + adapter-static + proxy Vite `/api` → `:8080`; ESLint/Prettier; Vitest
- [x] M0.6 i18n (`it.json`), layout base, componenti UI minimi (Button, Input, Modal, Toast)
- [x] M0.7 CI (lint + test backend e frontend) — `.gitlab-ci.yml` nella root (job `backend` con MySQL di servizio, job `frontend` su immagine Playwright). `backend/.gitlab-ci.yml` (deploy Chicco) non è letto da GitLab nel monorepo; va rimosso/riscritto in M8.4

## M1 — Famiglia (auth esistente)
- [x] M1.1 Migration: `family`, `family_member` (`user_id` INT con segno), `user.name`
- [x] M1.2 `FamilyScopedQuery` + override di `findModel`/`prepareDataProvider` nei controller base + test che garantisce isolamento tra due famiglie
- [x] M1.3 Console: `family/create "<nome>" <username>`, `family/add-member <family_id> <username> <role>`; `GET /api/v1/me`, `GET/PATCH /api/v1/family`, `GET /api/v1/family/members`
- [x] M1.4 Frontend: login con token (verifica via `/me`), `client.ts` (envelope + errori per `code`), store sessione, guard

## M2 — Figli e dispositivi
- [x] M2.1 Migration child_profile, device, pairing_code; CRUD figli + PIN
- [x] M2.2 Pairing code, pair, profiles, select-child (PIN, rate limit) → `child_token`, elenco/revoca dispositivi
- [x] M2.3 Frontend genitore: gestione figli, generazione codice + QR
- [x] M2.4 Frontend figlio: `/device`, `/profiles` con avatar e tastierino PIN

## M3 — Compiti e routine
- [x] M3.1 Migration task, checklist, assignee, occurrence, task_template (+ seed); anticipata `point_transaction` (+ `PointsService::award` con livelli) perché M3.4 accredita punti
- [x] M3.2 CRUD compiti con ricorrenza; `OccurrenceGenerator` + test sui casi (weekly bitmask, interval, cambio ora legale Europe/Rome, idempotenza)
- [x] M3.3 `cron/daily` (genera + marca missed)
- [x] M3.4 Endpoint `me/today`, checklist, submit, approve/reject, bulk-approve (macchina a stati testata)
- [x] M3.5 Frontend genitore: lista compiti, TaskForm, RecurrencePicker, libreria template, pagina approvazioni
- [x] M3.6 Frontend figlio: `/today` con TaskCard e checklist

## M4 — Gamification e premi
- [x] M4.1 Migration reward, reward_redemption, savings_goal, badge, child_badge (+ seed badge)
- [x] M4.2 `PointsService`: spesa/riscatti, bonus/malus (il lock `FOR UPDATE` serializza gli accrediti; i test coprono doppio submit/approve e saldo mai negativo)
- [x] M4.3 `StreakService`, `BadgeService` (valutazione regole JSON)
- [x] M4.4 Endpoint premi, riscatto, approvazione, limiti per periodo, adjust bonus/penalty
- [x] M4.5 Frontend figlio: contatore animato, Celebration, negozio, obiettivo, trofei
- [x] M4.6 Frontend genitore: gestione premi, storico punti, bonus

## M5 — Ricettario e menù settimanale
- [x] M5.1 Migration ingredient (+ seed ~170, e `shopping_category` + seed anticipati da M6), recipe, recipe_ingredient, meal_plan_week, meal_slot, meal_entry, meal_vote
- [x] M5.2 CRUD ricette + upload immagine + autocomplete ingredienti
- [x] M5.3 Endpoint meal-plans (get/crea, put slot, copy-from, template)
- [x] M5.4 Voti e proposte dei figli
- [x] M5.5 Frontend: ricettario (lista, form con righe ingredienti), planner WeekGrid responsive
- [x] M5.6 Frontend figlio: menù con voti e proposta (rotta `(child)/kid-menu`, vedi `05-frontend.md`)
- [x] M5.7 Compito collegato a uno slot pasto ("aiuta a cucinare"): bottone 👩‍🍳 nel planner → `tasks/new?meal_slot=&date=&title=`

## M6 — Lista della spesa
- [x] M6.1 Migration shopping_list, shopping_item, pantry_exclusion, favorite_item
- [x] M6.2 CRUD liste/articoli, check/uncheck, versioning + ETag/304
- [x] M6.3 `UnitConverter` + `ShoppingListService::generateFromWeek` con test (scalatura porzioni, somma, unità miste, rigenerazione che preserva manuali/spuntati, esclusioni)
- [x] M6.4 Endpoint `/sync` idempotente
- [x] M6.5 Frontend: lista per reparti, QuickAdd, preferiti, polling, coda offline
- [x] M6.6 "Genera dal menù" dal planner e dalla lista; riordino reparti
- [x] M6.7 Permesso spunta per figli (`can_check_shopping`, pagina `(child)/kid-shopping`)

## M7 — Notifiche, statistiche, PWA
- [x] M7.1 Web push (VAPID), subscribe, `NotificationService`, `cron/minute` per promemoria routine (iscrizione dal browser con il service worker in M7.4)
- [x] M7.2 Email riepilogo settimanale (opt-in `weekly_email`, email del genitore da console)
- [x] M7.3 Endpoint `/stats` + pagina statistiche
- [x] M7.4 PWA: manifest, icone, service worker (`src/service-worker/`: app shell, fallback SPA offline, rete-prima per la spesa, push tradotte da it.json), bottoni notifiche (impostazioni genitore, oggi figlio)

## M8 — Rifinitura e rilascio
- [x] M8.1 Privacy: export dati, cancellazione famiglia, informativa, pagina impostazioni privacy
- [x] M8.2 Hardening: rate limit, header sicurezza, revisione permessi (test per ogni endpoint × ruolo)
- [x] M8.3 E2E Playwright dei flussi principali (20 scenari sul finto backend in CI + `npm run test:e2e:live` contro il backend vero con i dati di `seed/demo`)
- [ ] M8.4 Deploy produzione: riconfigurare `backend/.gitlab-ci.yml` (oggi punta al server Chicco, PHP 8.1) e `.htaccess`, build frontend; backup, monitoraggio errori
- [x] M8.5 Onboarding guidato per nuova famiglia (crea figlio → prima routine → primo premio)

## Prompt suggeriti per Claude Code
- «Leggi CLAUDE.md e docs/06-roadmap.md. Implementa il task M0.1. Fermati e mostrami il risultato prima di procedere.»
- «Implementa M3.2 seguendo docs/03-database.md e docs/04-api.md. Scrivi prima i test del generatore di istanze.»
- «Rivedi tutti i controller in backend/modules/v1/controllers e verifica che ogni query sia filtrata per family_id. Elenca eventuali problemi.»
- «Spunta nella roadmap i task completati e aggiorna la documentazione se hai deviato dal piano.»
