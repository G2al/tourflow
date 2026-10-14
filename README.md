# TourFlow

Backend gestionale per tour operator: catalogo di soggiorni, escursioni e transfer, prezzi lordi (pubblico) e netti (agenzie), richieste di prenotazione con prosecuzione su WhatsApp.

- **Pannello admin:** Filament 5 su `/admin`
- **API per il frontend:** `/api/v1`, autenticazione agenzie con Sanctum. Documentazione in [docs/API.md](docs/API.md)
- **Stack:** PHP 8.2+, Laravel 12, MySQL

## Avvio in locale

```bash
composer install
cp .env.example .env
php artisan key:generate
# configura DB_* nel file .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Il seeder crea un catalogo demo, un'agenzia di prova (`agenzia@tourflow.test` / `password`) e, se non esiste già un amministratore, l'utente `admin@tourflow.test` / `password`.

## Test

```bash
php artisan test
```

I test usano SQLite in memoria e non toccano il database di sviluppo.
