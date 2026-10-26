<?php

return [

    /*
    | Sito pubblico (frontend): usato per i link alle pratiche nelle email.
    | La pagina della pratica è {frontend_url}/pratica/{code}?token={token}.
    */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    | Indirizzo che riceve le notifiche per il tour operator (nuove opzioni,
    | conferme, proforma aperte, nuove agenzie).
    */
    'notification_email' => env('BOOKING_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),

    /*
    | Promemoria prima della scadenza delle opzioni (ore).
    */
    'option_reminders' => [
        'agency' => [24, 1],
        'guest' => [1],
    ],

];
