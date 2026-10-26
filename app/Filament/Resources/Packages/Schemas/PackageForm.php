<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Enums\BoardType;
use App\Enums\PackageOffer;
use App\Enums\PackageType;
use App\Filament\Support\HelpAction;
use App\Filament\Support\PriceFields;
use App\Models\Destination;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Package;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        self::generalTab(),
                        self::contentTab(),
                        self::photosTab(),
                        self::periodsTab(),
                        self::feesTab(),
                        self::conditionsTab(),
                    ]),
            ]);
    }

    private static function generalTab(): Tab
    {
        return Tab::make('Generale')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Cosa stai vendendo?')
                    ->afterHeader([HelpAction::make('offer', 'Cosa stai vendendo?')])
                    ->schema([
                        ToggleButtons::make('offer')
                            ->hiddenLabel()
                            ->options(PackageOffer::class)
                            ->default(PackageOffer::HotelOnly)
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshTitle($get, $set))
                            ->helperText(fn (Get $get) => self::offer($get)?->getDescription()),
                    ]),

                Section::make('Dati principali')
                    ->columns(2)
                    ->schema([
                        Select::make('destination_id')
                            ->label('Destinazione')
                            ->helperText('Il luogo del viaggio (es. Hurghada).')
                            ->relationship('destination', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('hotel_id', null);
                                self::refreshTitle($get, $set);
                            }),
                        Select::make('hotel_id')
                            ->label('Hotel')
                            ->helperText("Tipologie di camera e disponibilità si gestiscono nella scheda dell'hotel.")
                            ->relationship(
                                'hotel',
                                'name',
                                fn (Builder $query, Get $get) => $query->where('destination_id', $get('destination_id')),
                            )
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => self::type($get) === PackageType::Stay)
                            ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
                            ->disabled(fn (Get $get) => blank($get('destination_id')))
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshTitle($get, $set)),
                        TextInput::make('title')
                            ->label('Titolo')
                            ->helperText('Si compila da solo in base a cosa vendi, destinazione e hotel. Puoi modificarlo.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Hidden::make('auto_title')->dehydrated(false),
                        TextInput::make('accommodation')
                            ->label('Sistemazione / servizio')
                            ->helperText('Che tipo di struttura o servizio è.')
                            ->placeholder('Es. Resort 5 stelle, Auto privata con autista, Barca con ponte panoramico')
                            ->maxLength(255),
                        TextInput::make('board')
                            ->label('Trattamento / formula')
                            ->helperText('Cosa è compreso nei pasti o nel servizio.')
                            ->datalist(BoardType::suggestions())
                            ->placeholder('Es. All Inclusive, Mezza pensione, Pranzo incluso')
                            ->maxLength(255),
                        TextInput::make('badge')
                            ->label('Etichetta sulla foto')
                            ->helperText('Facoltativa: compare sopra la foto nella card.')
                            ->placeholder('Es. Più richiesto, Premium, Ultimi posti')
                            ->maxLength(50),
                        TagsInput::make('departures')
                            ->label("Punti d'incontro / partenza")
                            ->helperText('Dove inizia il servizio. Scrivi e premi Invio per aggiungere.')
                            ->placeholder('Es. Aeroporto di Hurghada, Hotel, Porto di Hurghada'),
                    ]),

                Section::make('Soggiorno e voli')
                    ->afterHeader([HelpAction::make('flights', 'Voli e soggiorno')])
                    ->visible(fn (Get $get) => self::offer($get)?->isStay() ?? false)
                    ->columns(2)
                    ->schema([
                        TextInput::make('min_nights')
                            ->label('Soggiorno minimo (facoltativo)')
                            ->helperText('Notti minime per chi sceglie le date liberamente. Lascia vuoto per nessun minimo.')
                            ->placeholder('Nessun minimo')
                            ->numeric()
                            ->minValue(1)
                            ->afterStateHydrated(fn (TextInput $component, $state) => $component->state((int) $state > 1 ? $state : null))
                            ->dehydrateStateUsing(fn ($state) => max(1, (int) $state))
                            ->visible(fn (Get $get) => self::offer($get) === PackageOffer::HotelOnly),
                        Toggle::make('flight_hotel_enabled')
                            ->label('Permetti di aggiungere il volo')
                            ->helperText('Il cliente potrà scegliere se venire con il proprio volo o aggiungere uno dei voli abbinati qui sotto.')
                            ->live()
                            ->inline(false)
                            ->visible(fn (Get $get) => self::offer($get) === PackageOffer::HotelOnly),
                        CheckboxList::make('flights')
                            ->label('Voli abbinati')
                            ->helperText('Spunta i voli da proporre con questo hotel. Le notti in hotel sono quelle del volo scelto. I voli si creano in Catalogo → Voli.')
                            ->relationship(
                                'flights',
                                'outbound_date',
                                fn (Builder $query, Get $get) => $query
                                    ->where('flights.destination_id', $get('destination_id'))
                                    ->whereDate('flights.outbound_date', '>', today()),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Flight $record) => $record->label()
                                ." · {$record->nights()} notti · € ".number_format((float) $record->adult_price, 0, ',', '.')." · {$record->seats} posti")
                            ->required(fn (Get $get) => self::offer($get)?->includesFlight() ?? false)
                            ->visible(fn (Get $get) => self::offer($get)?->includesFlight()
                                || (self::offer($get) === PackageOffer::HotelOnly && $get('flight_hotel_enabled')))
                            ->bulkToggleable()
                            ->searchable()
                            ->noSearchResultsMessage('Nessun volo futuro per questa destinazione: crealo in Catalogo → Voli.')
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Validità')
                    ->description('Dopo la data "Prenotabile al" il pacchetto non viene più mostrato sul sito.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label('Prenotabile dal')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('valid_to')
                            ->label('Prenotabile al')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('valid_from')
                            ->required(),
                    ]),

                Grid::make(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Pubblicato sul sito')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('In evidenza')
                            ->helperText('Mostrato tra i primi nel catalogo.'),
                        TextInput::make('sort_order')
                            ->label('Ordine nel catalogo')
                            ->helperText('Numero più basso = più in alto.')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    private static function contentTab(): Tab
    {
        return Tab::make('Contenuti')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Testi del sito')
                    ->afterHeader([HelpAction::make('content', 'Come scrivere i contenuti')])
                    ->schema([
                        Textarea::make('short_description')
                            ->label('Descrizione breve')
                            ->helperText('Una o due frasi per la card del catalogo: cosa offre e perché sceglierlo.')
                            ->placeholder('Es. 7 notti sul Mar Rosso in resort 5 stelle All Inclusive.')
                            ->rows(2)
                            ->maxLength(500),
                        Textarea::make('description')
                            ->label('Descrizione completa')
                            ->helperText('Il testo della pagina del pacchetto. Vai a capo per creare un nuovo paragrafo.')
                            ->placeholder('Es. Una settimana a Hurghada pensata per chi cerca mare, comfort e relax…')
                            ->rows(8),
                        Repeater::make('highlights')
                            ->label('Punti forti')
                            ->helperText('I 3-4 motivi principali per scegliere questo pacchetto: le cose più belle del posto o della struttura.')
                            ->simple(TextInput::make('value')->validationAttribute('voce')->placeholder('Es. Barriera corallina a 50 metri')->required()->maxLength(255))
                            ->addActionLabel('Aggiungi punto forte')
                            ->defaultItems(0),
                        Repeater::make('itinerary')
                            ->label('Programma')
                            ->helperText('Le tappe del viaggio: cosa succede e cosa si fa. Utile per pacchetti ed escursioni.')
                            ->schema([
                                TextInput::make('day')
                                    ->label('Quando')
                                    ->placeholder('Es. Giorno 1, Giorni 2–3, Mattina')
                                    ->required(),
                                TextInput::make('title')
                                    ->label('Titolo')
                                    ->placeholder('Es. Arrivo a Hurghada')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Descrizione')
                                    ->placeholder('Es. Transfer in hotel e cena di benvenuto.')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => trim(($state['day'] ?? '').' · '.($state['title'] ?? ''), ' ·'))
                            ->addActionLabel('Aggiungi tappa')
                            ->defaultItems(0),
                        Grid::make(2)
                            ->schema([
                                Repeater::make('included')
                                    ->label('La quota comprende')
                                    ->helperText('Tutto ciò che è incluso nel prezzo, una voce per riga.')
                                    ->simple(TextInput::make('value')->validationAttribute('voce')->placeholder('Es. 7 pernottamenti')->required()->maxLength(255))
                                    ->addActionLabel('Aggiungi voce')
                                    ->defaultItems(0),
                                Repeater::make('excluded')
                                    ->label('La quota non comprende')
                                    ->helperText('Tutto ciò che il cliente paga a parte, una voce per riga.')
                                    ->simple(TextInput::make('value')->validationAttribute('voce')->placeholder('Es. Mance')->required()->maxLength(255))
                                    ->addActionLabel('Aggiungi voce')
                                    ->defaultItems(0),
                            ]),
                    ]),
            ]);
    }

    private static function photosTab(): Tab
    {
        return Tab::make('Foto')
            ->icon('heroicon-o-photo')
            ->schema([
                FileUpload::make('cover_image')
                    ->label('Immagine principale')
                    ->helperText('Usata nella card del catalogo e in cima alla pagina.')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('packages'),
                FileUpload::make('gallery')
                    ->label('Galleria')
                    ->helperText('Altre foto mostrate nella pagina. Trascinale per cambiare l\'ordine.')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->panelLayout('grid')
                    ->disk('public')
                    ->directory('packages/gallery'),
            ]);
    }

    private static function periodsTab(): Tab
    {
        return Tab::make('Prezzi per stagione')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Section::make('Prezzi per stagione')
                    ->description(fn (Get $get) => match (true) {
                        self::type($get) === PackageType::Stay => 'Dividi l\'anno in stagioni con prezzi diversi. Prezzi dell\'HOTEL a persona a notte (il volo ha il suo prezzo). Ogni notte prenotabile deve rientrare in una stagione.',
                        self::isPerService($get) => 'Un prezzo unico per tutto il gruppo, per stagione.',
                        default => 'Prezzi a persona, per stagione.',
                    })
                    ->afterHeader([HelpAction::make('prices', 'Prezzi per stagione')])
                    ->schema([
                        Repeater::make('periods')
                            ->hiddenLabel()
                            ->relationship()
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed(fn (string $operation) => $operation === 'edit')
                            ->cloneable()
                            ->addActionLabel('Aggiungi stagione')
                            ->itemLabel(fn (array $state) => self::periodLabel($state))
                            ->columns(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome stagione')
                                    ->placeholder('Es. Bassa stagione, Natale'),
                                DatePicker::make('start_date')
                                    ->label('Dal')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('Al')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('start_date')
                                    ->required(),
                                PriceFields::money('adult_price', 'Prezzo', required: true)
                                    ->label(fn (Get $get) => match (true) {
                                        self::isPerService($get, '../../') => 'Prezzo del servizio (tutto il gruppo)',
                                        self::type($get, '../../') === PackageType::Stay => 'Adulto in doppia, a notte',
                                        default => 'Adulto (18+ anni)',
                                    }),
                                PriceFields::money('extra_bed_adult_price', '3°/4° letto adulto, a notte')
                                    ->helperText('Vuoto = come l\'adulto.')
                                    ->visible(fn (Get $get) => self::type($get, '../../') === PackageType::Stay),
                                PriceFields::money('child_price', 'Bambino')
                                    ->label(fn (Get $get) => self::type($get, '../../') === PackageType::Stay ? 'Bambino (2-17 anni), a notte' : 'Bambino (2-17 anni)')
                                    ->helperText('Vuoto = come l\'adulto.')
                                    ->visible(fn (Get $get) => ! self::isPerService($get, '../../')),
                                PriceFields::money('infant_price', 'Infant')
                                    ->label(fn (Get $get) => self::type($get, '../../') === PackageType::Stay ? 'Infant (0-1 anni), a notte' : 'Infant (0-1 anni)')
                                    ->helperText('Vuoto = gratis.')
                                    ->visible(fn (Get $get) => ! self::isPerService($get, '../../')),
                                PriceFields::money('single_supplement', 'Supplemento singola, a notte')
                                    ->helperText('Quanto paga in più un adulto da solo in camera.')
                                    ->visible(fn (Get $get) => self::type($get, '../../') === PackageType::Stay),
                            ]),
                    ]),
            ]);
    }

    private static function feesTab(): Tab
    {
        return Tab::make('Quote accessorie')
            ->icon('heroicon-o-receipt-percent')
            ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
            ->schema([
                Section::make('Trasferimenti')
                    ->afterHeader([HelpAction::make('fees', 'Trasferimenti e quota d\'iscrizione')])
                    ->visible(fn (Get $get) => self::offer($get) !== PackageOffer::FullPackage)
                    ->schema([
                        Toggle::make('transfer_included')
                            ->label('Trasferimenti inclusi nel prezzo')
                            ->default(true)
                            ->live(),
                        PriceFields::money('transfer_price', 'Prezzo trasferimenti per persona', required: true)
                            ->helperText('Aggiunto a ogni passeggero pagante (infant esclusi).')
                            ->visible(fn (Get $get) => ! $get('transfer_included')),
                    ]),
                Section::make("Quota d'iscrizione")
                    ->description('Quota fissa a persona per la gestione della pratica (di solito 30-50 €). È una prassi diffusa tra i tour operator italiani per coprire i costi amministrativi.')
                    ->schema([
                        Toggle::make('has_registration_fee')
                            ->label("Applica la quota d'iscrizione")
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Toggle $component, $record) => $component->state((float) ($record?->registration_fee ?? 0) > 0))
                            ->afterStateUpdated(fn (Set $set, bool $state) => $state ?: $set('registration_fee', 0))
                            ->live(),
                        PriceFields::money('registration_fee', "Quota d'iscrizione per persona", required: true)
                            ->helperText('Si aggiunge a ogni passeggero pagante del soggiorno (infant esclusi).')
                            ->default(0)
                            ->visible(fn (Get $get) => (bool) $get('has_registration_fee'))
                            ->dehydratedWhenHidden(),
                    ]),
            ]);
    }

    private static function conditionsTab(): Tab
    {
        return Tab::make('Condizioni')
            ->icon('heroicon-o-scale')
            ->schema([
                Textarea::make('cancellation_policy')
                    ->label('Condizioni di cancellazione')
                    ->helperText('Le penali se il cliente annulla. Compaiono nella pagina del pacchetto.')
                    ->placeholder("Es.\n- fino a 30 giorni prima: 10%\n- da 29 a 15 giorni: 30%\n- da 14 giorni alla partenza: 100%")
                    ->rows(6),
                Textarea::make('notes')
                    ->label('Note')
                    ->helperText('Informazioni utili in più per il cliente.')
                    ->placeholder('Es. Passaporto con validità residua di 6 mesi. Tassa di soggiorno da pagare in loco.')
                    ->rows(3),
            ]);
    }

    /**
     * Aggiorna il titolo automatico, a meno che sia stato modificato a mano.
     */
    private static function refreshTitle(Get $get, Set $set): void
    {
        $offer = self::offer($get);

        if (! $offer) {
            return;
        }

        $title = Package::autoTitle(
            $offer,
            Destination::find($get('destination_id'))?->name,
            Hotel::find($get('hotel_id'))?->name,
        );

        if (blank($get('title')) || $get('title') === $get('auto_title')) {
            $set('title', $title);
        }

        $set('auto_title', $title);
    }

    private static function offer(Get $get, string $path = ''): ?PackageOffer
    {
        $state = $get($path.'offer');

        return $state instanceof PackageOffer ? $state : PackageOffer::tryFrom((string) $state);
    }

    private static function type(Get $get, string $path = ''): ?PackageType
    {
        return self::offer($get, $path)?->packageType();
    }

    private static function isPerService(Get $get, string $path = ''): bool
    {
        return self::offer($get, $path) === PackageOffer::Transfer;
    }

    /**
     * Riepilogo della stagione nella riga chiusa: "Natale (20/12 - 06/01) · 98 €".
     *
     * @param  array<string, mixed>  $state
     */
    private static function periodLabel(array $state): string
    {
        $label = filled($state['name'] ?? null) ? $state['name'] : 'Stagione';

        if (filled($state['start_date'] ?? null) && filled($state['end_date'] ?? null)) {
            $label .= ' ('.date('d/m/Y', strtotime($state['start_date'])).' - '.date('d/m/Y', strtotime($state['end_date'])).')';
        }

        if (filled($state['adult_price'] ?? null)) {
            $label .= ' · '.number_format((float) $state['adult_price'], 0, ',', '.').' €';
        }

        return $label;
    }
}
