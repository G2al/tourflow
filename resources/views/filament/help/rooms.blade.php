@include('filament.help._style')
<div class="tf-help">
    <p>Qui definisci le <strong>camere</strong> che vendi in questo hotel e <strong>quante ne hai acquistate</strong>.</p>

    <h3>Tipologia di camera</h3>
    <ul>
        <li><strong>Nome</strong>: come la vede il cliente (es. "Standard", "Deluxe vista mare").</li>
        <li><strong>Supplemento a persona a notte</strong>: quanto costa in più rispetto al prezzo base del pacchetto. Metti 0 per la camera base. Gli infant non pagano il supplemento.</li>
        <li><strong>Occupazione massima</strong>: quante persone possono dormirci (massimo 4).</li>
    </ul>

    <h3>Allotment (camere acquistate)</h3>
    <p>È il numero di camere disponibili <strong>per ogni notte</strong> di un periodo. Ogni prenotazione occupa una camera per ciascuna notte del soggiorno; quando le camere finiscono il sito non le vende più.</p>
    <table>
        <tr><th>Tipologia</th><th>Dal</th><th>Al (ultima notte)</th><th>Camere</th></tr>
        <tr><td>Deluxe vista mare</td><td>01/11</td><td>30/11</td><td>4</td></tr>
    </table>
    <div class="tf-example">
        <strong>Esempio</strong>: hai 4 Deluxe dal 1 al 30 novembre. Un cliente prenota dal 10 al 17: dal 10 al 16 restano 3 Deluxe libere, le altre notti restano 4.
    </div>

    <div class="tf-example">
        <strong>Esempio di prezzo</strong>: pacchetto a 70 € a notte, Deluxe con supplemento 30 €. 2 adulti per 7 notti:<br>
        (70 + 30) × 7 × 2 = <strong>1.400 €</strong>.
    </div>

    <div class="tf-warning">
        La colonna <strong>"Libere stanotte"</strong> ti mostra a colpo d'occhio quante camere restano oggi. Opzioni e conferme occupano camere; preventivi e carrelli no.
    </div>
</div>
