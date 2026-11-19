# 01 — Documento di prodotto (non tecnico)

## 1. Visione

HomeQuest aiuta le famiglie a organizzare la vita di casa senza litigi. I genitori assegnano compiti e routine, i figli li completano come in un videogioco guadagnando punti, livelli e premi. In più la famiglia pianifica insieme i pasti della settimana e la lista della spesa si crea da sola.

**Ispirazione**: app come Levelty (routine + ricompense per bambini). **Differenza**: aggiungiamo la gestione della cucina (menù + spesa) e coinvolgiamo i figli anche lì (votare i piatti, compiti tipo "aiuta a sparecchiare", "spunta la lista al supermercato").

## 2. Utenti

| Ruolo | Chi è | Cosa fa |
|---|---|---|
| **Genitore (admin)** | Chi crea l'account famiglia | Tutto: gestisce membri, compiti, premi, menù, spesa, impostazioni |
| **Genitore (co-genitore)** | Secondo adulto aggiunto alla famiglia (fase attuale: da console; in futuro invito via email) | Come admin, ma non può eliminare la famiglia né l'abbonamento |
| **Tutti giocano** | Anche ogni genitore ha un profilo di gioco (punti, livelli, badge, premi) creato in automatico | Può ricevere compiti, completarli dalla pagina "Il mio gioco" e riscattare premi; le approvazioni le fa qualunque genitore |
| **Figlio** | Bambino/ragazzo 4–14 anni | Vede i suoi compiti, li segna come fatti, vede punti e premi, vota i piatti, spunta la spesa |

Il figlio **non ha email né password**: accede da un dispositivo collegato tramite **codice di abbinamento** generato dal genitore, poi entra scegliendo il proprio avatar e (opzionale) un PIN di 4 cifre.

## 3. Funzionalità

### 3.1 Famiglia e profili
- Account genitore e famiglia (nome). *Fase attuale*: creati da console dall'amministratore, accesso con token; registrazione self-service (email + password) in futuro.
- Invito co-genitore via email.
- Profili figli: nome, avatar, colore, data di nascita (solo anno/mese, opzionale), PIN opzionale.
- Collegamento dispositivo figlio con codice di 6 caratteri valido 15 minuti (o QR code).

### 3.2 Compiti e routine (il cuore)
- **Compito singolo**: "Riordina la camera", scadenza opzionale, punti.
- **Routine ricorrente**: ogni giorno / giorni della settimana scelti / ogni N giorni; fascia oraria (mattina, pomeriggio, sera).
- **Checklist** dentro un compito (es. routine mattina: lavarsi i denti, vestirsi, zaino).
- Assegnazione a uno o più membri della famiglia: figli **e genitori**.
- **Compito libero**: compare a tutti i giocatori scelti (o a tutti se non ne scegli nessuno); il primo che lo completa prende il bonus e agli altri sparisce. Se il genitore lo rifiuta, torna libero. Non conta per le serie (è un extra).
- **Approvazione**: il genitore può richiedere conferma prima che i punti vengano accreditati (impostabile per compito).
- Libreria di compiti suggeriti per età (template precaricati).
- Icona/emoji per ogni compito (leggibile anche da chi non sa leggere).

### 3.3 Gamification
- **Punti (monete)**: si guadagnano completando compiti, si spendono nel negozio premi.
- **XP e livelli**: l'XP non si spende mai, serve a salire di livello (curva: livello n richiede `100 × n` XP cumulativi aggiuntivi).
- **Serie (streak)**: giorni consecutivi con tutte le routine completate. Bonus a 3, 7, 14, 30 giorni.
- **Badge**: obiettivi (primo compito, 50 compiti, settimana perfetta, chef aiutante...).
- **Bonus/malus manuale** del genitore con motivazione (il malus è disattivabile dalle impostazioni; di default disattivato, filosofia di rinforzo positivo).

### 3.4 Negozio premi
- Il genitore crea premi con costo in punti (es. "30 min di tablet" 50 punti, "scegli la cena" 100 punti).
- Il figlio richiede il premio → il genitore approva → punti scalati. Se rifiuta, nessun addebito.
- Premi con disponibilità limitata (es. max 1 a settimana).
- **Obiettivo di risparmio**: il figlio può "puntare" un premio e vedere la barra di avanzamento.

### 3.5 Menù settimanale (nuovo)
- Calendario settimana (lun–dom) × pasti (colazione, pranzo, merenda, cena; i pasti visibili sono configurabili).
- Ogni slot contiene uno o più **piatti**: scelti dal ricettario di famiglia o testo libero.
- **Ricettario**: ricetta con nome, foto opzionale, porzioni, ingredienti (quantità + unità), procedimento, tag (veloce, vegetariano, preferito dai bimbi).
- Copia settimana precedente / salva settimana come modello.
- **Suggerisci menù** (solo genitori): propone piatti per i pasti ancora vuoti partendo dal ricettario, dallo storico delle ultime 8 settimane (varietà, ricette che non si fanno da tempo, abitudini di colazione/merenda) e dai voti; il genitore può aggiungere un'indicazione libera ("martedì poco tempo"), sceglie quali proposte tenere e solo allora vengono salvate. Con l'impostazione "Suggerimenti IA" (attiva di default) le scelte sono fatte da un modello di intelligenza artificiale (Google Gemini) con **dati anonimi**; disattivata, o se il servizio non risponde, le calcola l'app.
- **Coinvolgimento figli**: i figli possono votare (👍/👎) le proposte e proporre un piatto per un giorno (il genitore approva). Opzionale: punti per chi aiuta a cucinare tramite compito collegato.
- Indicazione persone a tavola per pasto (per scalare le quantità).

### 3.6 Lista della spesa (nuovo)
- **Generazione automatica** dal menù della settimana: somma gli ingredienti di tutte le ricette, scalati per porzioni, raggruppando lo stesso ingrediente e unità compatibili (g/kg, ml/l).
- Aggiunta manuale di articoli ("detersivo").
- Articoli raggruppati per **reparto** (frutta e verdura, latticini, carne/pesce, dispensa, surgelati, casa, altro) nell'ordine scelto dalla famiglia.
- Spunta in tempo reale condivisa (due genitori al supermercato vedono la stessa lista).
- "Ho già in casa": escludere un ingrediente generato.
- Articoli ricorrenti/preferiti con un tap.
- Più liste (es. "Supermercato", "Farmacia").
- Il figlio può avere il permesso di spuntare articoli (compito "aiuta a fare la spesa").

### 3.7 Notifiche
- Promemoria routine al figlio (push PWA).
- Al genitore: compito da approvare, premio richiesto, proposta piatto.
- Riepilogo settimanale via email al genitore (opzionale).

### 3.8 Statistiche (genitore)
- Compiti completati per figlio / settimana, tasso di completamento routine, punti guadagnati e spesi.

## 4. User story principali (MVP)

| ID | Come… | voglio… | così che… |
|---|---|---|---|
| US-01 | genitore | registrarmi e creare la famiglia | posso iniziare |
| US-02 | genitore | aggiungere i profili dei miei figli | assegnare loro compiti |
| US-03 | genitore | collegare il tablet di mio figlio con un codice | lui entri senza password |
| US-04 | genitore | creare una routine giornaliera con checklist | non ripetere ogni mattina le stesse cose |
| US-05 | figlio | vedere i compiti di oggi con icone grandi | sapere cosa fare |
| US-06 | figlio | segnare un compito come fatto e vedere i punti salire | sentirmi gratificato |
| US-07 | genitore | approvare o rifiutare un compito completato | verificare che sia fatto davvero |
| US-08 | genitore | creare premi con un costo | motivare i figli |
| US-09 | figlio | richiedere un premio | spendere i miei punti |
| US-10 | genitore | pianificare i pasti della settimana | sapere cosa cucinare |
| US-11 | genitore | salvare ricette con ingredienti | riusarle |
| US-12 | genitore | generare la lista della spesa dal menù | non dimenticare nulla |
| US-13 | genitore | spuntare gli articoli al supermercato in tempo reale con il partner | non comprare doppio |
| US-14 | figlio | votare i piatti della settimana | dire la mia |

## 5. Fuori dallo scope MVP (dopo)
- App native (prima PWA installabile).
- Abbonamento a pagamento / funzioni premium.
- Import ricette da URL, calcolo valori nutrizionali.
- Integrazione con supermercati online.
- Paghetta in denaro reale.

## 6. Glossario

| Termine | Significato |
|---|---|
| Compito (task) | Attività da fare, singola o ricorrente |
| Istanza (occurrence) | Il compito "di oggi" generato da una routine |
| Punti | Valuta spendibile nel negozio |
| XP | Esperienza, non spendibile, determina il livello |
| Serie (streak) | Giorni consecutivi con routine completate |
| Slot pasto | Incrocio giorno × pasto nel menù |
| Reparto | Categoria della lista spesa |

## 7. Requisiti non funzionali (in parole semplici)
- Funziona da telefono, tablet e PC; usabile da un bambino di 5 anni (icone grandi, poco testo, feedback sonoro/visivo).
- Veloce anche con connessione scarsa; senza rete menù (in sola lettura) e lista spesa restano consultabili, e la spesa si può spuntare e aggiornare: le modifiche partono al ritorno della connessione. I punti richiedono sempre la rete.
- Dati dei bambini protetti (GDPR): niente pubblicità, niente tracciamento, cancellazione completa su richiesta.
- In italiano all'avvio, predisposta per altre lingue.
