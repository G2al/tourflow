# TourFlow API v1

Documentazione per l'integrazione del frontend.

- **Base URL:** `{APP_URL}/api/v1` (in locale `http://127.0.0.1:8000/api/v1`)
- **Formato:** JSON. Inviare sempre `Accept: application/json`.
- **Date:** `YYYY-MM-DD`. **Importi:** numeri in euro (`EUR`).
- **CORS:** abilitato per `/api/*`. L'autenticazione è a token Bearer (niente cookie).
- **Nomi dei campi:**
  - catalogo (pacchetti, destinazioni, filtri): **camelCase**, secondo il contratto `TravelPackage` del frontend;
  - autenticazione, preventivo e prenotazioni: per ora **snake_case**. Verranno allineati nello step dedicato alla prenotazione.

## Indice

1. [Modello prezzi: ospite e agenzia](#1-modello-prezzi-ospite-e-agenzia)
2. [Autenticazione agenzie](#2-autenticazione-agenzie)
3. [Catalogo](#3-catalogo)
4. [Preventivo](#4-preventivo)
5. [Prenotazione e WhatsApp](#5-prenotazione-e-whatsapp)
6. [Area agenzia: le mie prenotazioni](#6-area-agenzia-le-mie-prenotazioni)
7. [Errori](#7-errori)
8. [Flusso consigliato lato frontend](#8-flusso-consigliato-lato-frontend)

---

## 1. Modello prezzi: ospite e agenzia

Le rotte del catalogo sono pubbliche. Se la richiesta contiene il token di un'agenzia, gli importi sono **netti**, altrimenti **lordi**.

| Chi chiama | `priceType` | Campi prezzo |
|---|---|---|
| Ospite (nessun token) | `gross` | `price`: prezzo al pubblico |
| Agenzia (token Bearer) | `net` | `price`: netto agenzia<br>`priceGross`: prezzo al pubblico |

Il campo "principale" (`price`, `adultPrice`…) è sempre quello da mostrare all'utente corrente. I campi `*Gross` compaiono **solo** per le agenzie, ad esempio per mostrare il prezzo al pubblico e il margine.

Il token va inviato su **tutte** le chiamate quando l'agenzia è loggata:

```
Authorization: Bearer 1|abc123...
```

---

## 2. Autenticazione agenzie

Non esistono account per i clienti privati: prenotano come ospiti. Solo le agenzie si registrano e possono operare subito, senza approvazione.

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
    "tax_code": "09876543210",
    "address": "Corso Italia 25",
    "city": "Milano",
    "zip_code": "20122",
    "province": "MI",
    "country": "IT",
    "phone": "+39 02 9876543",
    "email": "info@viaggisole.it",
    "pec": "viaggisole@pec.it",
    "sdi_code": "M5UXCR1",
    "contact_person": "Mario Rossi",
    "license_number": "AUT-MI-2019-123"
  }
}
```

Obbligatori: `name`, `email`, `password` (+ conferma, minimo 8 caratteri), `agency.company_name`, `agency.vat_number` (univoca), `agency.address`, `agency.city`, `agency.zip_code`. Gli altri campi sono facoltativi.

**201** → stessa risposta del login.

### `POST /auth/login`

```json
{ "email": "mario@viaggisole.it", "password": "password123" }
```

**200**

```json
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 2,
    "name": "Mario Rossi",
    "email": "mario@viaggisole.it",
    "phone": "+39 333 1111111",
    "role": "agency",
    "agency": { "id": 1, "company_name": "Viaggi Sole Srl", "vat_number": "09876543210", "...": "..." },
    "created_at": "2026-10-09T17:00:00+00:00"
  }
}
```

Con credenziali errate o un account disattivato risponde **422** con un errore su `email`.

### `GET /auth/me` 🔒

Restituisce l'utente con i dati dell'agenzia: `{ "data": { ...user } }`.

### `PUT /auth/me` 🔒

Aggiornamento parziale: si inviano solo i campi da modificare, ad esempio `{ "phone": "...", "agency": { "city": "Bergamo" } }`. Per cambiare la password: `current_password`, `password` e `password_confirmation`.

### `POST /auth/logout` 🔒

Revoca il token corrente. Risponde **204**.

> 🔒 = richiede `Authorization: Bearer <token>`

---

## 3. Catalogo

Il catalogo contiene tre tipi di prodotto, tutti prenotabili singolarmente:

| `type` | `typeKey` | Esempio | Prezzo |
|---|---|---|---|
| `Soggiorno` | `stay` | Hurghada All Inclusive, 7 notti | per persona |
| `Escursione` | `excursion` | Orange Bay in barca, in giornata | per persona |
| `Transfer` | `transfer` | Transfer privato aeroporto | **a servizio** (prezzo unico per il gruppo) |

Il tour operator vende **solo servizi a terra**: il volo non è mai incluso.

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
      "image": "https://.../packages/hurghada.jpg",
      "type": "Soggiorno",
      "price": 490,
      "days": 8,
      "nights": 7,
      "period": "Tutto l'anno",
      "months": ["Gennaio", "Febbraio", "...", "Dicembre"],
      "departures": ["Aeroporto di Hurghada"],
      "board": "All Inclusive",
      "stay": "Resort 5 stelle",
      "badge": "Più richiesto",
      "description": "Sette notti in resort sul Mar Rosso...",
      "longDescription": "Una settimana a Hurghada...\n\nSelezioniamo il resort...",
      "highlights": ["Resort sul mare", "All Inclusive", "Assistenza locale", "Formula flessibile"],
      "included": ["7 pernottamenti", "Trattamento All Inclusive"],
      "excluded": ["Volo A/R dall'Italia", "Escursioni facoltative"],
      "itinerary": [
        { "day": "Giorno 1", "title": "Benvenuti a Hurghada", "description": "Arrivo autonomo..." }
      ],

      "id": 1,
      "typeKey": "stay",
      "priceUnit": "per_person",
      "priceType": "gross",
      "currency": "EUR",
      "featured": true,
      "destinationSlug": "hurghada",
      "validFrom": "2026-10-01",
      "validTo": "2027-09-30"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 12, "total": 7, "...": "..." }
}
```

**Significato e garanzie dei campi**

| Campo | Note |
|---|---|
| `price` | Prezzo "a partire da": il più basso tra i periodi tariffari futuri. È per persona, oppure a servizio se `priceUnit` = `per_service`. Vale `null` se il pacchetto non ha ancora tariffe. |
| `days` | Sempre `nights + 1`: escursioni e transfer hanno `nights: 0` e `days: 1`. |
| `period` | Testo inserito dall'admin. Se non c'è, viene calcolato dai periodi (`"Tutto l'anno"`, `"Novembre - Aprile"`). |
| `months` | Calcolati in automatico dai periodi futuri, in ordine Gennaio → Dicembre. |
| `departures` | Punti d'incontro / partenza. |
| `location` | Se l'admin non la compila, coincide con `destination`. |
| `badge`, `board`, `stay` | Possono valere `null`: in quel caso l'elemento va **nascosto**. |
| `description` | Testo breve, senza HTML. |
| `longDescription` | Testo semplice senza HTML. I paragrafi sono separati da una riga vuota (`\n\n`). |
| `highlights`, `included`, `excluded`, `itinerary`, `departures`, `months` | Sempre array, al massimo vuoti, mai `null`. |
| `rating`, `reviews` | **Non forniti**: le recensioni non sono gestite e il frontend deve nascondere stelle e numero recensioni. |

**Parametri query** (tutti facoltativi):

| Parametro | Esempio | Descrizione |
|---|---|---|
| `search` | `luxor` | cerca in titolo, località, descrizione breve, destinazione, paese e hotel |
| `destination` | `hurghada` o `Hurghada` | slug o nome della destinazione |
| `type` | `Escursione` | `Soggiorno`, `Transfer`, `Escursione` (accetta anche `stay`, `transfer`, `excursion`) |
| `days` | `8` | durata esatta in giorni |
| `min_days` / `max_days` | `2` / `8` | intervallo di durata |
| `departure` | `Aeroporto di Hurghada` | punto d'incontro (valore esatto, preso da `/packages/filters`) |
| `board` | `All Inclusive` | trattamento/formula (valore esatto, preso da `/packages/filters`) |
| `min_price` / `max_price` | `50` / `500` | filtro sul prezzo "a partire da" |
| `date` | `2026-11-14` | solo prodotti prenotabili in quella data |
| `featured` | `1` | solo prodotti in evidenza |
| `sort` | `price_asc` | `recommended` (default), `price_asc`, `price_desc`, `duration`, `newest` |
| `per_page` | `12` | da 1 a 100, default 12 |
| `page` | `2` | pagina |

Note sugli ordinamenti:
- `recommended` mette prima i prodotti in evidenza e poi segue l'ordine scelto nel pannello.
- `rating` viene accettato ma si comporta come `recommended`, perché le recensioni non sono gestite.

### `GET /packages/filters`: opzioni per i filtri

Restituisce i valori realmente presenti nel catalogo pubblicato, da usare per popolare tendine e chip dei filtri.

```json
{
  "data": {
    "destinations": [{ "name": "Hurghada", "slug": "hurghada", "country": "Egitto" }],
    "types": ["Soggiorno", "Transfer", "Escursione"],
    "departures": ["Aeroporto di Hurghada", "Hotel Hurghada", "Porto di Hurghada"],
    "boards": ["All Inclusive", "Mezza pensione", "Pranzo incluso", "Servizio privato"],
    "days": [1, 8],
    "priceRange": { "min": 30, "max": 590 }
  }
}
```

### `GET /packages/{slug}`: dettaglio

Contiene tutti i campi dell'elenco, più i seguenti:

```json
{
  "data": {
    "...tutti i campi dell'elenco...": "",
    "gallery": ["https://...", "https://..."],
    "hotel": {
      "id": 1, "name": "Red Sea Paradise Resort", "slug": "red-sea-paradise-resort", "stars": 5,
      "address": "Sahl Hasheesh, Hurghada", "description": "...",
      "amenities": ["Spiaggia privata", "Piscine"], "images": ["https://..."]
    },
    "transfer": { "included": false, "price": 15 },
    "registrationFee": 0,
    "ageRanges": {
      "infant": { "min": 0, "max": 1 },
      "child": { "min": 2, "max": 11 },
      "adult": { "min": 12, "max": null }
    },
    "cancellationPolicy": "Penali per persona in caso di annullamento:\n- fino a 30 giorni...",
    "notes": null,
    "periods": [
      {
        "id": 1, "name": "Autunno", "startDate": "2026-10-01", "endDate": "2026-12-19",
        "adultPrice": 490, "extraBedAdultPrice": 415, "childPrice": 245,
        "infantPrice": null, "singleSupplement": 145
      }
    ],
    "excursions": [
      { "id": 1, "name": "Safari in quad al tramonto", "description": "...", "duration": "4 ore",
        "image": null, "adultPrice": 35, "childPrice": 20 }
    ],
    "insurances": [
      { "id": 1, "name": "Medico-bagaglio Base", "type": "medical_luggage", "typeLabel": "Medico-bagaglio",
        "description": "...", "price": 35 }
    ]
  }
}
```

Note:
- `hotel` vale `null` per escursioni e transfer, e anche per i soggiorni senza hotel specificato.
- `transfer`: per i soggiorni indica se il trasferimento è incluso; se non lo è, `price` è il costo per persona.
- `periods` è il listino dettagliato, con solo i periodi non scaduti. Per i prodotti `per_service` conta solo `adultPrice`, che è il prezzo del servizio.
- `excursions` sono escursioni **facoltative** da aggiungere a un soggiorno in fase di prenotazione. Sono diverse dai prodotti di tipo `Escursione` del catalogo.
- Per le agenzie ogni prezzo ha anche la variante `*Gross`.

### `GET /destinations`

```json
{
  "data": [
    {
      "id": 1, "name": "Hurghada", "slug": "hurghada", "country": "Egitto",
      "description": "Mar Rosso, resort sul mare...", "image": "https://...", "packagesCount": 4
    }
  ]
}
```

`GET /destinations/{slug}` restituisce una singola destinazione nello stesso formato.

### `GET /settings`

Dati pubblici del tour operator (contatti, numero WhatsApp), da usare per footer e pulsanti di contatto.

```json
{
  "data": {
    "company_name": "TourFlow Viaggi",
    "whatsapp_number": "393331234567",
    "contact_email": "info@...",
    "contact_phone": "+39 06 1234567",
    "address": "Via Roma 1, 00100 Roma (RM)",
    "vat_number": "01234567890"
  }
}
```

### Immagini

Le immagini caricate dal pannello hanno URL assoluti sul dominio del backend (`{APP_URL}/storage/...`). Con Next.js quel dominio va autorizzato in `next.config.ts` → `images.remotePatterns`. In locale è `127.0.0.1:8000`. I dati demo usano `picsum.photos`.

---

## 4. Preventivo

### `POST /packages/{slug}/quote`

Calcola il prezzo senza salvare nulla. Va chiamato a ogni modifica del form (data, partecipanti, extra) per mostrare il totale aggiornato.

```json
{
  "departure_date": "2026-11-14",
  "participants": [
    { "birth_date": "1985-03-10" },
    { "birth_date": "1987-07-22" },
    { "birth_date": "2018-05-01" },
    { "birth_date": "2026-01-15" }
  ],
  "excursion_ids": [1],
  "insurance_ids": [1]
}
```

**200**

```json
{
  "data": {
    "price_type": "gross",
    "price_unit": "per_person",
    "currency": "EUR",
    "departure_date": "2026-11-14",
    "return_date": "2026-11-21",
    "period": { "id": 1, "name": "Autunno", "start_date": "2026-10-01", "end_date": "2026-12-19" },
    "service": null,
    "participants": [
      {
        "index": 0, "birth_date": "1985-03-10", "age": 41, "age_category": "adult",
        "items": [
          { "label": "Quota base adulto", "amount": 490 },
          { "label": "Trasferimenti", "amount": 15 }
        ],
        "total": 505
      },
      { "index": 2, "age": 8, "age_category": "child", "items": ["..."], "total": 260 },
      { "index": 3, "age": 0, "age_category": "infant", "items": [{ "label": "Quota infant", "amount": 0 }], "total": 0 }
    ],
    "extras": [
      { "type": "excursion", "id": 1, "name": "Safari in quad al tramonto", "quantity": 3, "total": 90 },
      { "type": "insurance", "id": 1, "name": "Medico-bagaglio Base", "quantity": 3, "total": 105 }
    ],
    "totals": { "service": 0, "participants": 1270, "extras": 195, "total": 1465 }
  }
}
```

Per i prodotti **a servizio** (transfer), `service` vale `{ "label": "Prezzo del servizio", "amount": 30 }`, i partecipanti hanno `total: 0` e `totals.total` coincide con il prezzo del servizio.

**Regole di calcolo** (una sola camera, massimo 10 partecipanti):

- L'età si calcola **alla data di partenza**. Le fasce sono quelle in `ageRanges` del pacchetto.
- Prodotti a servizio: un prezzo unico per tutto il gruppo.
- I primi 2 adulti pagano la quota base (adulto in camera doppia).
- Dal 3° adulto si applica la quota 3°/4° letto. Se non è prevista, si usa la quota base.
- Bambino: si applica la sua quota. Se non è prevista, si usa la quota base.
- Infant: si applica la sua quota. Se non è prevista, è gratis.
- Un adulto da solo, senza bambini, paga anche il supplemento singola.
- Trasferimenti non inclusi e quota d'iscrizione si sommano per ogni partecipante, **infant esclusi**.
- Escursioni facoltative: prezzo adulto per ogni adulto, prezzo bambino per ogni bambino. Gli infant non pagano.
- Assicurazioni: prezzo per persona, infant esclusi.
- Serve almeno un adulto. La data deve essere nella validità del pacchetto e coperta da un periodo tariffario.

---

## 5. Prenotazione e WhatsApp

### `POST /bookings`

Si può chiamare come ospite o come agenzia (con token). Non c'è pagamento online: la prenotazione viene salvata **in attesa** e la risposta contiene il link WhatsApp per proseguire con il tour operator.

```json
{
  "package_id": 1,
  "departure_date": "2026-11-14",
  "contact": {
    "first_name": "Luca",
    "last_name": "Bianchi",
    "email": "luca@example.com",
    "phone": "+39 333 2222222"
  },
  "participants": [
    {
      "first_name": "Luca", "last_name": "Bianchi", "birth_date": "1985-03-10", "gender": "M",
      "tax_code": "BNCLCU85C10H501X", "nationality": "Italiana",
      "document_type": "passport", "document_number": "YA1234567", "document_expiry": "2030-05-01"
    },
    { "first_name": "Anna", "last_name": "Verdi", "birth_date": "1987-07-22", "gender": "F" }
  ],
  "excursion_ids": [1],
  "insurance_ids": [1],
  "notes": "Camera vista mare se possibile",
  "agency_reference": "PR-001"
}
```

| Campo | Obbligatorio | Note |
|---|---|---|
| `package_id` | sì | `id` del pacchetto |
| `departure_date` | sì | per escursioni e transfer è la data del servizio |
| `contact.*` | sì | referente della prenotazione |
| `participants[]` | sì | da 1 a 10. **Il primo partecipante è il capogruppo** |
| `participants[].first_name`, `last_name`, `birth_date`, `gender` | sì | `gender`: `M` / `F` |
| `participants[].tax_code`, `nationality` | no | |
| `participants[].document_type` | no | `identity_card` / `passport` |
| `participants[].document_number`, `document_expiry` | no | |
| `excursion_ids`, `insurance_ids` | no | id presi dal dettaglio del pacchetto |
| `notes` | no | massimo 2000 caratteri |
| `agency_reference` | no | riferimento pratica interno dell'agenzia (salvato solo per le agenzie) |

**201**

```json
{
  "data": {
    "id": 1,
    "code": "TF-26-CAK6V7",
    "status": "pending",
    "status_label": "In attesa",
    "departure_date": "2026-11-14",
    "return_date": "2026-11-21",
    "package": { "id": 1, "title": "Hurghada All Inclusive", "slug": "hurghada-all-inclusive", "nights": 7, "destination": "Hurghada", "hotel": "Red Sea Paradise Resort" },
    "contact": { "first_name": "Luca", "last_name": "Bianchi", "email": "...", "phone": "..." },
    "agency_reference": null,
    "notes": "...",
    "participants": [
      { "id": 1, "is_lead": true, "first_name": "Luca", "last_name": "Bianchi", "birth_date": "1985-03-10",
        "gender": "M", "tax_code": "...", "nationality": "...", "document_type": "passport",
        "document_number": "...", "document_expiry": "2030-05-01", "age_category": "adult", "price": 505 }
    ],
    "extras": [{ "type": "excursion", "id": 1, "name": "Safari in quad al tramonto", "quantity": 2, "total": 70 }],
    "price_type": "gross",
    "currency": "EUR",
    "total": 1465,
    "price_breakdown": { "...stessa struttura del preventivo...": "" },
    "whatsapp_url": "https://wa.me/393331234567?text=Ciao%21%20Vorrei%20confermare...",
    "created_at": "2026-10-13T17:00:00+00:00"
  }
}
```

- Il prezzo viene **sempre ricalcolato dal server**: il frontend non invia importi.
- Per le agenzie, `price_type` vale `net` e c'è anche `total_gross`, il totale al pubblico.
- `whatsapp_url` apre una chat con il tour operator con un messaggio già compilato: codice, pacchetto, date, partecipanti, extra, totale e, se presente, l'agenzia. Vale `null` solo se il numero WhatsApp non è configurato nel pannello.
- Limite: 10 richieste al minuto per IP.

---

## 6. Area agenzia: le mie prenotazioni

### `GET /bookings` 🔒

Prenotazioni fatte dall'agenzia loggata, paginate (`per_page` default 15). Filtro facoltativo: `status` = `pending` | `confirmed` | `cancelled`.

### `GET /bookings/{code}` 🔒

Dettaglio di una prenotazione dell'agenzia, nello stesso formato della creazione. Una prenotazione di altri restituisce **404**.

Gli stati sono gestiti dall'admin nel pannello: `pending` (In attesa), `confirmed` (Confermata), `cancelled` (Annullata).

---

## 7. Errori

| Codice | Quando | Corpo |
|---|---|---|
| 401 | token mancante o non valido su una rotta 🔒 | `{ "message": "Unauthenticated." }` |
| 404 | risorsa inesistente o non pubblicata | `{ "message": "..." }` |
| 422 | errore di validazione | vedi sotto |
| 429 | troppe richieste | `{ "message": "Too Many Attempts." }` |

Esempio di 422 (i messaggi sono in italiano e si possono mostrare direttamente sotto i campi):

```json
{
  "message": "Il campo email referente è obbligatorio. (e altri 2 errori)",
  "errors": {
    "contact.email": ["Il campo email referente è obbligatorio."],
    "participants.0.birth_date": ["Il campo data di nascita è obbligatorio."],
    "departure_date": ["Il pacchetto è valido dal 01/10/2026 al 30/09/2027."]
  }
}
```

Errori di business restituiti da preventivo e prenotazione:

- `departure_date`: data nel passato, fuori validità o senza tariffa.
- `participants`: nessun adulto nel gruppo.
- `excursions` / `insurances`: extra non disponibile per il pacchetto.
- `type` (elenco pacchetti): tipo non valido.

---

## 8. Flusso consigliato lato frontend

1. **Catalogo**: `GET /packages` con i filtri, e `GET /packages/filters` per popolare le opzioni dei filtri.
2. **Dettaglio**: `GET /packages/{slug}`.
3. **Form di prenotazione** (step successivo): per ogni partecipante si raccolgono i dati anagrafici. A ogni modifica si chiama `POST /packages/{slug}/quote` e si mostra il riepilogo.
4. **Invio**: `POST /bookings`. Poi si mostra la conferma con `code` e il pulsante **"Conferma su WhatsApp"** che apre `whatsapp_url`.
5. **Agenzie**: login/registrazione, salvataggio del token e invio di `Authorization` su tutte le chiamate (i prezzi diventano netti in automatico). Pagina "Le mie prenotazioni" con `GET /bookings`.

### Account demo (dati di seed)

| Ruolo | Email | Password |
|---|---|---|
| Agenzia | `agenzia@tourflow.test` | `password` |
