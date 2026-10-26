# TourFlow API v1

Documentazione per l'integrazione del frontend (Next.js).

- **Base URL:** `{APP_URL}/api/v1`. In produzione è `https://queentouradmin.deviommi.it/api/v1`, in locale `http://127.0.0.1:8000/api/v1`.
- **Formato:** JSON. Inviare sempre `Accept: application/json`.
- **Date:** `YYYY-MM-DD`. **Importi:** numeri in euro.
- **Nomi dei campi:**
  - **risposte** di catalogo, carrello e pratiche in **camelCase**;
  - **richieste** (body) in **snake_case**;
  - autenticazione (`/auth/*`) in snake_case sia in richiesta sia in risposta.
- **Autenticazione:** token Bearer, solo per le agenzie.

## Indice

1. [Concetti](#1-concetti)
2. [Autenticazione agenzie](#2-autenticazione-agenzie)
3. [Catalogo](#3-catalogo)
4. [Carrello: prezzo e disponibilità](#4-carrello-prezzo-e-disponibilità)
5. [Pratiche: preventivo, opzione, conferma](#5-pratiche-preventivo-opzione-conferma)
6. [Errori](#6-errori)
7. [Flusso consigliato lato frontend](#7-flusso-consigliato-lato-frontend)

---

## 1. Concetti

### Chi usa il sito

| Utente | Account | Prezzi | Opzione |
|---|---|---|---|
| **Cliente (ospite)** | nessuno | prezzo pieno | blocca per **6 ore** |
| **Agenzia** | registrazione e login | **prezzo pieno**, più il dettaglio della propria fee (`agencyFee`) | blocca per **72 ore** |

Le durate sono configurabili dal pannello e vengono restituite da `GET /settings`.

Tutti vedono sempre il **prezzo pieno al pubblico**. L'agenzia ha una **fee %** (predefinita o personalizzata): sul sito può mostrare al cliente il prezzo pieno e, su richiesta, aprire il dettaglio con la sua fee e il netto. La fee si applica all'**intera pratica**.

### Prodotti

| `type` | Come si prenota (`bookingModes`) | Prezzo |
|---|---|---|
| `Soggiorno` | `hotel_only`: solo soggiorno con date libere<br>`flight_hotel`: volo + hotel su un volo a date fisse | a persona **a notte**, per camera |
| `Escursione` | `excursion`: in una data | a persona |
| `Transfer` | `transfer`: in una data | a servizio (prezzo unico per il gruppo) |

- **Camere:** fino a **4 per soggiorno**, ognuna con tipologia (Standard, Deluxe vista mare…) e fino a **4 occupanti**.
- **Età** (alla data del servizio): infant **0–1**, bambino **2–17**, adulto **18+**. Le date di nascita sono sempre obbligatorie.
- **Disponibilità:** camere per notte e posti volo limitati. Li occupano solo le pratiche in **opzione** (finché non scade) o **confermate**. Carrello e preventivo **non** bloccano nulla.

### Ciclo di vita della pratica

```
carrello (solo frontend)
   │  POST /bookings  action = quote | option | confirm
   ▼
Preventivo ──► Opzione (6h / 72h) ──► Confermata ──► …
     │              │ scade ──► Scaduta
     └──────────────┴──► Annullata
```

Dopo la conferma:
- **cliente:** la pratica è `confirmed` (definitiva); il tour operator la gestisce dal pannello;
- **agenzia:** la pratica è `proforma_sent`. Viene generata la proforma con le istruzioni per il bonifico dell'acconto (30%). Quando l'agenzia **apre il link della proforma**, la pratica passa a `proforma_viewed` e la prenotazione è definitiva.

Gli stati successivi (`deposit_received`, `paid`, `cancelled`) li imposta il tour operator.

| `status` | Etichetta |
|---|---|
| `quote` | Preventivo |
| `option` | Opzione |
| `confirmed` | Confermata |
| `proforma_sent` | Proforma inviata |
| `proforma_viewed` | Proforma visualizzata |
| `deposit_received` | Acconto ricevuto |
| `paid` | Saldata |
| `expired` | Scaduta |
| `cancelled` | Annullata |

**Blocca prezzo** (49 € a pratica, facoltativo): senza, quando un preventivo o un'opzione viene confermato il prezzo si **ricalcola** con il listino del momento; con il blocca prezzo resta quello iniziale.

---

## 2. Autenticazione agenzie

### `POST /auth/register`

```json
{
  "name": "Mario Rossi",
  "email": "mario@viaggisole.it",
  "phone": "+39 333 1111111",
  "password": "password123",
  "password_confirmation": "password123",
  "agency": {
    "company_name": "Viaggi Sole Srl",
    "vat_number": "09876543210",
    "address": "Corso Italia 25",
    "city": "Milano",
    "zip_code": "20122",
    "province": "MI",
    "phone": "+39 02 9876543",
    "email": "info@viaggisole.it",
    "tax_code": "09876543210",
    "country": "IT",
    "pec": "viaggisole@pec.it",
    "sdi_code": "M5UXCR1",
    "contact_person": "Mario Rossi",
    "license_number": "AUT-MI-2019-123"
  }
}
```

- **Obbligatori:** `name`, `email`, `password` (+ conferma, minimo 8 caratteri), `agency.company_name`, `agency.vat_number` (univoca), `agency.address`, `agency.city`, `agency.zip_code`, **`agency.province`**, **`agency.phone`**, **`agency.email`**.
- **Facoltativi:** tutti gli altri campi.
- L'agenzia è attiva subito. Risponde **201**, con la stessa risposta del login.

### `POST /auth/login`

```json
{ "email": "mario@viaggisole.it", "password": "password123" }
```

```json
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 2, "name": "Mario Rossi", "email": "mario@viaggisole.it", "phone": "...", "role": "agency",
    "agency": { "id": 1, "company_name": "Viaggi Sole Srl", "vat_number": "...", "fee_percent": 10, "...": "..." }
  }
}
```

### Altre rotte

| Rotta | Funzione |
|---|---|
| `GET /auth/me` 🔒 | Utente con agenzia: `{ "data": { ... } }` |
| `PUT /auth/me` 🔒 | Aggiornamento parziale (es. `{ "agency": { "city": "Bergamo" } }`). Per cambiare password: `current_password`, `password`, `password_confirmation` |
| `POST /auth/logout` 🔒 | Revoca il token. Risponde 204 |

> 🔒 = richiede `Authorization: Bearer <token>`. Quando l'agenzia è loggata, il token va inviato su **tutte** le chiamate, catalogo compreso.

---

## 3. Catalogo

### `GET /packages`: elenco

Ogni elemento rispetta il contratto `TravelPackage` del frontend, con in più alcuni campi del backend.

```json
{
  "data": [
    {
      "slug": "hurghada-all-inclusive",
      "title": "Hurghada All Inclusive",
      "destination": "Hurghada",
      "country": "Egitto",
      "location": "Hurghada · Mar Rosso",
      "image": "https://.../storage/packages/hurghada.jpg",
      "type": "Soggiorno",
      "price": 490,
      "days": 8,
      "nights": 7,
      "period": "Tutto l'anno",
      "months": ["Gennaio", "...", "Dicembre"],
      "departures": ["Aeroporto di Hurghada"],
      "board": "All Inclusive",
      "stay": "Resort 5 stelle",
      "badge": "Più richiesto",
      "description": "Sette notti in resort sul Mar Rosso...",
      "longDescription": "Paragrafo 1...\n\nParagrafo 2...",
      "highlights": ["Resort sul mare", "All Inclusive"],
      "included": ["7 pernottamenti"],
      "excluded": ["Volo A/R dall'Italia"],
      "itinerary": [{ "day": "Giorno 1", "title": "Benvenuti a Hurghada", "description": "..." }],

      "id": 1,
      "typeKey": "stay",
      "priceUnit": "per_person",
      "pricePerNight": 70,
      "currency": "EUR",
      "agencyFee": { "percent": 10, "amount": 49, "netPrice": 441 },
      "bookingModes": ["hotel_only", "flight_hotel"],
      "minNights": 3,
      "featured": true,
      "destinationSlug": "hurghada",
      "validFrom": "2026-10-01",
      "validTo": "2027-09-30"
    }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "total": 7, "...": "..." }
}
```

**Campi principali**

| Campo | Note |
|---|---|
| `price` | Prezzo "a partire da", a persona. Per i soggiorni è `pricePerNight × nights`, dove `nights` sono le notti consigliate. Per escursioni e transfer è il prezzo del servizio. Vale `null` se non ci sono tariffe. |
| `pricePerNight` | Solo per i soggiorni: la quota a notte più bassa. |
| `agencyFee` | **Presente solo con il token di un'agenzia**: percentuale, importo della fee sul `price` e netto. |
| `bookingModes` | Modalità di prenotazione disponibili per il prodotto. |
| `minNights` | Soggiorno minimo per il "solo soggiorno". |
| `badge`, `board`, `stay` | Possono valere `null`: in quel caso si nascondono. |
| `rating`, `reviews` | **Non forniti.** |

**Parametri query** (tutti facoltativi):

| Parametro | Esempio | Descrizione |
|---|---|---|
| `search` | `luxor` | titolo, località, descrizione, destinazione, paese, hotel |
| `destination` | `hurghada` | slug o nome |
| `type` | `Escursione` | anche `stay`, `transfer`, `excursion` |
| `days`, `min_days`, `max_days` | `8` | durata in giorni (notti + 1) |
| `departure` | `Aeroporto di Hurghada` | punto d'incontro (valore da `/packages/filters`) |
| `board` | `All Inclusive` | trattamento (valore da `/packages/filters`) |
| `min_price`, `max_price` | `50` | sul prezzo "a partire da" |
| `date` | `2026-11-14` | prenotabili in quella data |
| `featured` | `1` | solo in evidenza |
| `sort` | `price_asc` | `recommended` (default), `price_asc`, `price_desc`, `duration`, `newest` |
| `per_page` | `12` | da 1 a 100 |

### `GET /packages/filters`

Restituisce le opzioni per i filtri: `destinations`, `types`, `departures`, `boards`, `days`, `priceRange`.

### `GET /packages/{slug}`: dettaglio

Contiene tutti i campi dell'elenco, più i seguenti:

```json
{
  "gallery": ["https://..."],
  "hotel": {
    "id": 1, "name": "Red Sea Paradise Resort", "stars": 5, "address": "...", "description": "...",
    "amenities": ["Spiaggia privata"], "images": ["https://..."],
    "roomTypes": [
      { "id": 1, "name": "Standard", "description": "...", "maxOccupancy": 4, "supplementPerNight": 0 },
      { "id": 3, "name": "Deluxe vista mare", "description": "...", "maxOccupancy": 4, "supplementPerNight": 30 }
    ]
  },
  "flights": [
    {
      "id": 12, "departureAirport": "NAP", "departureAirportName": "Napoli", "departureCity": "Napoli",
      "outboundDate": "2026-10-18", "returnDate": "2026-10-25", "nights": 7,
      "carrier": "...", "outboundDetails": "Partenza ore 08:30", "returnDetails": "...",
      "adultPrice": 290, "childPrice": 218, "infantPrice": 50, "seatsAvailable": 30
    }
  ],
  "transfer": { "included": false, "price": 15 },
  "registrationFee": 0,
  "ageRanges": { "infant": { "min": 0, "max": 1 }, "child": { "min": 2, "max": 17 }, "adult": { "min": 18, "max": null } },
  "cancellationPolicy": "...",
  "notes": null,
  "periods": [
    { "id": 1, "name": "Autunno", "startDate": "2026-10-01", "endDate": "2026-12-19",
      "adultPrice": 70, "extraBedAdultPrice": 56, "childPrice": 35, "infantPrice": null, "singleSupplement": 21 }
  ]
}
```

- **`hotel.roomTypes`:** le tipologie selezionabili. `supplementPerNight` è a persona a notte (infant esclusi).
- **`flights`:** solo per i soggiorni vendibili come volo + hotel. Sono i voli futuri della destinazione, con prezzi A/R a persona e posti liberi. Aeroporti: `NAP` Napoli, `FCO` Roma, `MXP` Milano, `BRI` Bari.
- **`periods`:**
  - soggiorni: prezzi **a persona a notte**;
  - escursioni: a persona;
  - transfer: `adultPrice` è il prezzo del servizio.

  Un prezzo `null` significa: quota base (3° letto, bambino), oppure gratis (infant), oppure nessun supplemento (singola).
- **Disponibilità reale delle camere:** si ottiene dal carrello (sezione 4).

### Altri endpoint

| Rotta | Funzione |
|---|---|
| `GET /insurances` | Assicurazioni facoltative per la pratica: `[{ "id", "name", "type", "typeLabel", "description", "pricePerPerson" }]`. Il prezzo è a persona pagante (infant esclusi). |
| `GET /legal-pages` | Testi legali: `[{ "id", "title", "slug", "content" (HTML), "requiresAcceptance", "updatedAt" }]`. Quelli con `requiresAcceptance` vanno mostrati e accettati prima di opzione o conferma. |
| `GET /legal-pages/{slug}` | Un singolo testo legale. |
| `GET /settings` | Contatti del tour operator e regole: `infant_max_age`, `child_max_age`, `option_hours_agency`, `option_hours_guest`, `price_lock_amount`, `deposit_percent`, `quote_validity_days`. |
| `GET /destinations`, `GET /destinations/{slug}` | Destinazioni con `packagesCount`. |

---

## 4. Carrello: prezzo e disponibilità

Il **carrello lo gestisce il frontend**: il backend non salva nulla finché non si crea la pratica. A ogni modifica del carrello si chiama:

### `POST /cart/quote`

```json
{
  "passengers": [
    { "birth_date": "1985-03-10" },
    { "birth_date": "1987-07-22" },
    { "birth_date": "2018-05-01" },
    { "birth_date": "2026-01-15" }
  ],
  "items": [
    {
      "package_id": 1,
      "mode": "hotel_only",
      "check_in": "2026-11-14",
      "check_out": "2026-11-21",
      "rooms": [
        { "room_type_id": 1, "passengers": [0, 1, 2, 3] }
      ]
    },
    {
      "package_id": 1,
      "mode": "flight_hotel",
      "flight_id": 12,
      "rooms": [
        { "room_type_id": 3, "passengers": [0, 1] },
        { "room_type_id": 1, "passengers": [2, 3] }
      ]
    },
    { "package_id": 2, "date": "2026-11-16", "passengers": [0, 1, 2, 3] },
    { "package_id": 4, "date": "2026-11-14", "passengers": [0, 1, 2, 3] }
  ],
  "insurance_ids": [1],
  "price_lock": true
}
```

**`passengers`** (da 1 a 16 elementi) è la lista unica dei passeggeri della pratica. Ogni passeggero si identifica con la sua **posizione nell'array**, partendo da 0. Il primo è il capogruppo. Per il preventivo basta `birth_date`; per opzione e conferma servono anche `first_name`, `last_name` e `gender`.

**`items`** (da 1 a 10 elementi), un elemento per prodotto:
- **solo soggiorno:** `mode: "hotel_only"`, `check_in`, `check_out`, `rooms`;
- **volo + hotel:** `mode: "flight_hotel"`, `flight_id`, `rooms`. Date e notti sono quelle del volo;
- **escursione o transfer:** `date`, `passengers`. `mode` si può omettere.

**`rooms`**, da 1 a 4 camere:
- ogni camera ha `room_type_id` e i passeggeri assegnati: almeno 1 adulto, al massimo `maxOccupancy`;
- ogni passeggero deve comparire in almeno un prodotto, e non può stare in due camere dello stesso soggiorno.

**`insurance_ids`** e **`price_lock`** sono facoltativi.

**Risposta**

```json
{
  "data": {
    "currency": "EUR",
    "passengers": [{ "position": 0, "birthDate": "1985-03-10", "age": 41, "ageCategory": "adult" }],
    "items": [
      {
        "index": 0, "packageId": 1, "packageSlug": "hurghada-all-inclusive", "title": "Hurghada All Inclusive",
        "type": "stay", "mode": "hotel_only", "startDate": "2026-11-14", "endDate": "2026-11-21", "nights": 7,
        "hotel": { "id": 1, "name": "Red Sea Paradise Resort" },
        "flight": null,
        "rooms": [
          {
            "index": 0, "roomTypeId": 1, "roomType": "Standard", "passengers": [0, 1, 2, 3],
            "lines": [
              { "passenger": 0, "label": "Quota Adulto (7 notti)", "amount": 490 },
              { "passenger": 1, "label": "Quota Adulto (7 notti)", "amount": 490 },
              { "passenger": 2, "label": "Quota Bambino (7 notti)", "amount": 245 },
              { "passenger": 3, "label": "Quota Infant (7 notti)", "amount": 0 }
            ],
            "total": 1225
          }
        ],
        "passengers": [0, 1, 2, 3],
        "lines": [{ "passenger": 0, "label": "Trasferimenti", "amount": 15 }],
        "total": 1270,
        "available": true
      }
    ],
    "insurances": [{ "id": 1, "name": "Medico-bagaglio", "type": "medical_luggage", "unitPrice": 29, "quantity": 3, "total": 87 }],
    "priceLock": { "selected": true, "amount": 49 },
    "totals": {
      "items": 1270, "insurances": 87, "priceLock": 49, "total": 1406,
      "feePercent": 10, "feeAmount": 140.6, "amountDue": 1265.4,
      "depositPercent": 30, "depositAmount": 379.62
    },
    "available": true,
    "availabilityProblems": {}
  }
}
```

- **`totals.total`:** il prezzo pieno della pratica.
- **`feePercent` e `feeAmount`:** valorizzati solo per le agenzie; per i clienti `feePercent` è `null`.
- **`amountDue`:** quanto va pagato al tour operator (per le agenzie è al netto della fee). Su questo importo si calcola l'acconto.
- **`available`:** se è `false`, `availabilityProblems` indica cosa manca e dove. Ad esempio `{ "items.0.rooms.1": "Camera \"Deluxe vista mare\" non disponibile per la notte del 20/11/2026." }` oppure `{ "items.1.flight": "Posti volo insufficienti: disponibili 2." }`.
- **Righe con `passenger: null`:** voci di camera, ad esempio `"Supplemento Deluxe vista mare (2 pers. × 7 notti)"` o `"Supplemento singola (7 notti)"`.

---

## 5. Pratiche: preventivo, opzione, conferma

### `POST /bookings`

Il body è quello del carrello più i campi seguenti:

```json
{
  "...carrello (passengers, items, insurance_ids, price_lock)...": "",
  "action": "quote",
  "contact": { "first_name": "Luca", "last_name": "Bianchi", "email": "luca@example.com", "phone": "+39 333 2222222" },
  "terms_accepted": true,
  "notes": "Camera vicino alla piscina se possibile",
  "agency_reference": "PR-001"
}
```

| `action` | Effetto | Richiede |
|---|---|---|
| `quote` | **Preventivo**: PDF scaricabile, valido 7 giorni. Non blocca nulla. | contatto, date di nascita |
| `option` | **Opzione**: blocca camere e posti per 6 ore (cliente) o 72 ore (agenzia), poi scade da sola. | anche `terms_accepted: true` e nome, cognome e sesso di ogni passeggero |
| `confirm` | **Conferma**: blocca la disponibilità. Cliente: pratica definitiva. Agenzia: proforma con bonifico. | come l'opzione |

Facoltativi per ogni passeggero: `tax_code`, `nationality`, `document_type` (`identity_card` / `passport`), `document_number`, `document_expiry`.

`agency_reference` (riferimento pratica interno dell'agenzia) viene salvato solo per le agenzie.

Il **prezzo lo calcola sempre il server**. Se manca disponibilità per opzione o conferma risponde **422**, con gli stessi errori di `availabilityProblems`.

**Risposta 201: la pratica**

```json
{
  "data": {
    "id": 1,
    "code": "QT-26-AB12CD",
    "accessToken": "Xy9...48 caratteri",
    "status": "option",
    "statusLabel": "Opzione",
    "isAgencyBooking": false,
    "contact": { "firstName": "Luca", "lastName": "Bianchi", "email": "...", "phone": "..." },
    "agencyReference": null,
    "notes": "...",
    "passengers": [
      { "position": 0, "isLead": true, "firstName": "Luca", "lastName": "Bianchi", "birthDate": "1985-03-10",
        "gender": "M", "taxCode": null, "nationality": null, "documentType": null, "documentNumber": null,
        "documentExpiry": null, "ageCategory": "adult" }
    ],
    "items": [
      {
        "id": 1, "packageId": 1, "packageSlug": "...", "title": "...", "mode": "flight_hotel", "modeLabel": "Volo + hotel",
        "startDate": "2026-10-18", "endDate": "2026-10-25", "nights": 7,
        "hotel": { "id": 1, "name": "..." }, "flight": { "...come in /packages/{slug}...": "" }, "seats": 2,
        "passengers": [0, 1],
        "rooms": [{ "roomTypeId": 3, "roomType": "Deluxe vista mare", "checkIn": "...", "checkOut": "...", "passengers": [0, 1], "total": 1400 }],
        "breakdown": { "...stessa struttura dell'item del carrello...": "" },
        "total": 2010
      }
    ],
    "insurances": [{ "insuranceId": 1, "name": "Medico-bagaglio", "unitPrice": 29, "quantity": 2, "total": 58 }],
    "priceLock": { "selected": true, "amount": 49 },
    "totals": { "items": 2010, "insurances": 58, "priceLock": 49, "total": 2117, "feePercent": null, "feeAmount": 0, "amountDue": 2117, "depositPercent": 30, "depositAmount": 635.1 },
    "currency": "EUR",
    "quoteValidUntil": null,
    "optionExpiresAt": "2026-10-26T16:51:05+00:00",
    "confirmedAt": null,
    "proformaSentAt": null,
    "proformaViewedAt": null,
    "cancelledAt": null,
    "termsAcceptedAt": "2026-10-26T10:51:05+00:00",
    "actions": { "canOption": false, "canConfirm": true, "canCancel": true },
    "documents": {
      "quotePdfUrl": "https://.../api/v1/bookings/QT-26-AB12CD/quote.pdf?token=...",
      "proformaUrl": null
    },
    "createdAt": "2026-10-26T10:51:05+00:00"
  }
}
```

### Accesso alla pratica

Una pratica la può vedere:
- l'**agenzia** che l'ha creata (token Bearer);
- chi ha il **link personale**, cioè l'`accessToken`. Si passa come `?token=...` oppure con l'header `X-Booking-Token`.

Al cliente ospite va salvato o mostrato il link `.../pratica/{code}?token={accessToken}`, così può ritrovare preventivo e opzione. Senza accesso la risposta è **404**.

| Rotta | Funzione |
|---|---|
| `GET /bookings/{code}` | Dettaglio |
| `POST /bookings/{code}/option` | Preventivo → opzione |
| `POST /bookings/{code}/confirm` | Preventivo o opzione attiva → conferma |
| `POST /bookings/{code}/cancel` | Annulla un preventivo o un'opzione |
| `GET /bookings/{code}/quote.pdf?token=...` | PDF del preventivo (`documents.quotePdfUrl`) |
| `GET /bookings` 🔒 | Pratiche dell'agenzia loggata, paginate, con filtro `status` |

Il body di **option** e **confirm** è:

```json
{ "terms_accepted": true, "passengers": [ { "first_name": "...", "last_name": "...", "birth_date": "...", "gender": "M" } ] }
```

- `passengers` è facoltativo: serve per completare i dati mancanti di un preventivo. Deve avere lo stesso numero di passeggeri e le stesse date di nascita.
- Se la pratica non ha il "blocca prezzo", il prezzo viene **ricalcolato** con il listino attuale.
- Le azioni disponibili sono indicate in `actions`.

### Proforma (agenzie)

Dopo la conferma di un'agenzia, `documents.proformaUrl` contiene un **link firmato** al PDF della proforma, con l'importo dovuto, l'acconto del 30% e le coordinate bancarie.

- **La prima apertura del link** rende la prenotazione definitiva (`proforma_viewed`): va mostrato come pulsante "Apri proforma" nell'area agenzia.
- Le aperture fatte dal pannello admin non contano.

---

## 6. Errori

| Codice | Quando |
|---|---|
| 401 | Token mancante o non valido su una rotta 🔒 |
| 403 | Link della proforma manomesso |
| 404 | Risorsa inesistente o non accessibile |
| 422 | Validazione, disponibilità, stato non compatibile con l'azione |
| 429 | Troppe richieste |

```json
{
  "message": "Il campo email referente è obbligatorio. (e altri 2 errori)",
  "errors": {
    "contact.email": ["Il campo email referente è obbligatorio."],
    "items.0.rooms.1.passengers": ["È necessario almeno un adulto."],
    "items.0.rooms.0": ["Camera \"Deluxe vista mare\" non disponibile per la notte del 20/11/2026."]
  }
}
```

Le chiavi indicano il punto esatto del carrello (`items.{n}.rooms.{n}...`) e i messaggi sono in italiano.

---

## 7. Flusso consigliato lato frontend

1. **Catalogo:** `GET /packages` e `GET /packages/filters`.
2. **Dettaglio:** `GET /packages/{slug}`. Per i soggiorni, scelta tra "Solo soggiorno" (date) e "Volo + hotel" (aeroporto e volo da `flights`). Poi numero di camere (max 4), tipologia (`hotel.roomTypes`) e occupanti con data di nascita.
3. **Aggiungi al carrello** (stato locale). Si possono aggiungere più prodotti, comprese escursioni e transfer.
4. **Carrello:** a ogni modifica `POST /cart/quote`. Si mostrano righe, totali e disponibilità; le agenzie vedono anche fee e netto. Qui si scelgono le assicurazioni (`GET /insurances`) e il blocca prezzo.
5. **Dati:** anagrafica dei passeggeri e referente. Testi legali da `GET /legal-pages`, con le caselle di accettazione.
6. **Scelta finale:** "Richiedi preventivo" / "Blocca in opzione (6h / 72h)" / "Conferma" → `POST /bookings`.
7. **Pagina della pratica:** stato, scadenza dell'opzione con conto alla rovescia, PDF del preventivo, pulsanti `option`, `confirm` e `cancel` in base ad `actions`. Per le agenzie anche il pulsante "Apri proforma".
8. **Area agenzia:** `GET /bookings`.

### Account demo (seed)

| Ruolo | Email | Password |
|---|---|---|
| Agenzia | `agenzia@tourflow.test` | `password` |
