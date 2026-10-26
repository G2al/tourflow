@include('filament.help._style')
<div class="tf-help">
    <h3>Trasferimenti</h3>
    <ul>
        <li><strong>Inclusi</strong>: il trasferimento aeroporto-hotel è già nel prezzo del soggiorno.</li>
        <li><strong>Non inclusi</strong>: inserisci un prezzo a persona che viene aggiunto da solo a ogni passeggero pagante (infant esclusi).</li>
        <li>Nel <strong>pacchetto completo</strong> sono sempre inclusi: questa sezione non compare.</li>
    </ul>
    <div class="tf-example">
        Trasferimenti non inclusi a 15 € a persona, 2 adulti + 1 bambino → +45 € alla pratica.
    </div>
    <p>Se invece vuoi vendere un transfer da solo (es. a chi prenota solo l'hotel), crea un pacchetto di tipo <strong>Transfer</strong>.</p>

    <h3>Quota d'iscrizione</h3>
    <p>È una cifra fissa a persona per la gestione della pratica, di solito tra 30 e 50 €. È una prassi diffusa tra i tour operator italiani per coprire i costi amministrativi.</p>
    <ul>
        <li><strong>Spenta</strong> (predefinito): non si applica.</li>
        <li><strong>Accesa</strong>: inserisci l'importo; si aggiunge a ogni passeggero pagante del soggiorno (infant esclusi).</li>
    </ul>
    <div class="tf-example">
        Soggiorno 490 € + quota d'iscrizione 40 € = <strong>530 € a persona</strong>.
    </div>

    <h3>Impostazioni comuni a tutti i pacchetti</h3>
    <p>Fasce d'età, durata delle opzioni, "Blocca prezzo", acconto e fee delle agenzie si impostano una volta sola in <em>Impostazioni</em>. Le assicurazioni in <em>Catalogo → Assicurazioni</em>.</p>
</div>
