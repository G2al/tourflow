@include('filament.help._style')
<div class="tf-help">
    <p>La prima scelta decide <strong>cosa vendi</strong> con questo pacchetto. In base alla scelta il modulo mostra solo i campi che servono.</p>

    <table>
        <tr><th>Scelta</th><th>Cosa vendi</th><th>Come prenota il cliente</th><th>Prezzo</th></tr>
        <tr><td><strong>Solo soggiorno</strong></td><td>Solo l'hotel</td><td>Sceglie liberamente check-in e check-out. Se attivi "Permetti di aggiungere il volo" può anche scegliere un volo.</td><td>A persona a notte</td></tr>
        <tr><td><strong>Volo + hotel</strong></td><td>Hotel + volo</td><td>Sceglie un volo tra quelli abbinati: le notti in hotel sono quelle del volo.</td><td>Hotel a notte + volo</td></tr>
        <tr><td><strong>Pacchetto completo</strong></td><td>Volo + hotel + transfer</td><td>Come volo + hotel; il transfer è sempre incluso.</td><td>Hotel a notte + volo</td></tr>
        <tr><td><strong>Transfer</strong></td><td>Un trasferimento</td><td>Sceglie la data e indica chi viaggia.</td><td>Unico per tutto il gruppo</td></tr>
        <tr><td><strong>Escursione</strong></td><td>Un'escursione in giornata</td><td>Sceglie la data e indica chi partecipa.</td><td>A persona</td></tr>
    </table>

    <div class="tf-example">
        <strong>Esempi</strong>
        <ul>
            <li>Vuoi vendere un resort a Hurghada a chi arriva con il proprio volo → <strong>Solo soggiorno</strong>.</li>
            <li>Hai comprato posti su un charter Napoli → Sharm e vuoi venderli con un hotel → <strong>Volo + hotel</strong>.</li>
            <li>Vuoi un prezzo "tutto compreso" con volo, hotel e transfer → <strong>Pacchetto completo</strong>.</li>
            <li>Transfer privato aeroporto → hotel → <strong>Transfer</strong>. Gita a Luxor → <strong>Escursione</strong>.</li>
        </ul>
    </div>

    <h3>Il titolo</h3>
    <p>Si compila da solo (es. "Soggiorno a Hurghada · Red Sea Paradise Resort") quando scegli cosa vendi, la destinazione e l'hotel. Puoi sempre modificarlo.</p>
</div>
