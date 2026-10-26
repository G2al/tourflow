@include('filament.help._style')
<div class="tf-help">
    <p>Lo stesso prodotto non costa uguale tutto l'anno: dividi l'anno in <strong>stagioni</strong> e per ognuna inserisci i suoi prezzi. Quando il cliente prenota, il sistema guarda <strong>ogni notte</strong> (o la data dell'escursione/transfer) e usa il prezzo della stagione in cui cade.</p>

    <h3>Soggiorni: prezzi a persona a notte</h3>
    <table>
        <tr><th>Stagione</th><th>Dal</th><th>Al</th><th>Adulto</th><th>3°/4° letto</th><th>Bambino</th><th>Infant</th><th>Singola</th></tr>
        <tr><td>Bassa stagione</td><td>01/11</td><td>19/12</td><td>70 €</td><td>56 €</td><td>35 €</td><td>vuoto = gratis</td><td>+21 €</td></tr>
        <tr><td>Natale</td><td>20/12</td><td>06/01</td><td>98 €</td><td>78 €</td><td>49 €</td><td>vuoto = gratis</td><td>+29 €</td></tr>
    </table>

    <div class="tf-example">
        <strong>Esempio di calcolo</strong><br>
        Soggiorno dal 17 al 22 dicembre, 2 adulti in camera doppia:<br>
        3 notti di bassa stagione (17, 18, 19) a 70 € + 2 notti di Natale (20, 21) a 98 € = 406 € a persona → <strong>812 €</strong> in totale.
    </div>

    <h3>Cosa significa ogni prezzo</h3>
    <ul>
        <li><strong>Adulto</strong>: prezzo base, un adulto in camera doppia, a notte.</li>
        <li><strong>3°/4° letto adulto</strong>: terzo e quarto adulto nella stessa camera. Se vuoto pagano come l'adulto.</li>
        <li><strong>Bambino</strong> (2-17 anni): se vuoto paga come l'adulto.</li>
        <li><strong>Infant</strong> (0-1 anni): se vuoto è gratis.</li>
        <li><strong>Supplemento singola</strong>: quanto paga in più un adulto da solo in camera.</li>
    </ul>
    <p>Il supplemento della tipologia di camera (es. Deluxe vista mare) si imposta nella scheda dell'hotel e si aggiunge da solo.</p>

    <h3>Escursioni e transfer</h3>
    <ul>
        <li><strong>Escursione</strong>: prezzo a persona (adulto, bambino, infant) per la data dell'escursione.</li>
        <li><strong>Transfer</strong>: un solo prezzo per tutto il gruppo (es. 30 € a tratta, che siano 1 o 4 persone).</li>
    </ul>

    <div class="tf-warning">
        <strong>Attenzione</strong>: ogni notte prenotabile deve rientrare in una stagione. Se lasci un "buco" tra due stagioni, in quelle date non si può prenotare.
    </div>
</div>
