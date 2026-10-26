<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Enums\BoardType;
use App\Enums\PackageOffer;
use App\Enums\PackageType;
use App\Filament\Support\PriceFields;
use App\Models\Flight;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
            ->columns(2)
            ->schema([
                ToggleButtons::make('offer')
                    ->label('Cosa stai vendendo?')
                    ->options(PackageOffer::class)
                    ->default(PackageOffer::HotelOnly)
                    ->inline()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $offer = $state instanceof PackageOffer ? $state : PackageOffer::tryFrom((string) $state);

                        if ($offer && ! $offer->isStay()) {
                            $set('nights', 0);
                            $set('hotel_id', null);
                        } elseif ($offer) {
                            $set('nights', 7);
                        }
                    })
                    ->helperText(fn (Get $get) => self::offer($get)?->getDescription())
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('destination_id')
                    ->label('Destinazione')
                    ->relationship('destination', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('hotel_id', null)),
                TextInput::make('location')
                    ->label('Località')
                    ->placeholder('Es. Hurghada · Mar Rosso')
                    ->helperText('Mostrata sotto il titolo. Se vuota si usa la destinazione.')
                    ->maxLength(255),
                Select::make('hotel_id')
                    ->label('Hotel')
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
                    ->helperText('Tipologie di camera e disponibilità si gestiscono nella scheda dell\'hotel.'),
                TextInput::make('accommodation')
                    ->label('Sistemazione / servizio')
                    ->placeholder('Es. Resort 5 stelle, Auto privata con autista')
                    ->maxLength(255),
                TextInput::make('board')
                    ->label('Trattamento / formula')
                    ->datalist(BoardType::suggestions())
                    ->placeholder('Es. All Inclusive, Pranzo incluso')
                    ->maxLength(255),
                TextInput::make('badge')
                    ->label('Badge sulla foto')
                    ->placeholder('Es. Più richiesto, Premium')
                    ->maxLength(50),
                TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->helperText('Lascia vuoto per generarlo automaticamente dal titolo.')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TagsInput::make('departures')
                    ->label("Punti d'incontro / partenza")
                    ->placeholder('Es. Aeroporto di Hurghada (premi Invio per aggiungere)')
                    ->columnSpanFull(),
                Section::make('Soggiorno e voli')
                    ->visible(fn (Get $get) => self::offer($get)?->isStay() ?? false)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nights')
                            ->label('Notti consigliate')
                            ->numeric()
                            ->minValue(1)
                            ->default(7)
                            ->required()
                            ->dehydratedWhenHidden()
                            ->helperText('Usate per il prezzo "a partire da" mostrato nel catalogo.'),
                        TextInput::make('min_nights')
                            ->label('Soggiorno minimo (notti)')
                            ->helperText('Per le prenotazioni a date libere (senza volo).')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->visible(fn (Get $get) => self::offer($get) === PackageOffer::HotelOnly),
                        Toggle::make('flight_hotel_enabled')
                            ->label('Volo facoltativo')
                            ->helperText('Il cliente può scegliere se aggiungere uno dei voli abbinati o venire con il proprio.')
                            ->live()
                            ->visible(fn (Get $get) => self::offer($get) === PackageOffer::HotelOnly)
                            ->columnSpanFull(),
                        CheckboxList::make('flights')
                            ->label('Voli abbinati')
                            ->helperText('Voli futuri della destinazione (Catalogo → Voli). Le notti in hotel sono quelle del volo scelto.')
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
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Validità')
                    ->columns(3)
                    ->columnSpanFull()
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
                        TextInput::make('period_label')
                            ->label('Testo periodo')
                            ->placeholder("Es. Tutto l'anno")
                            ->helperText('Se vuoto viene calcolato dai periodi di prezzo.'),
                    ]),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Pubblicato')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('In evidenza'),
                        TextInput::make('sort_order')
                            ->label('Ordinamento')
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
                Textarea::make('short_description')
                    ->label('Descrizione breve')
                    ->helperText('Usata nella card del catalogo e nelle anteprime.')
                    ->rows(2)
                    ->maxLength(500),
                Textarea::make('description')
                    ->label('Descrizione completa')
                    ->helperText('Testo semplice: vai a capo per creare un nuovo paragrafo.')
                    ->rows(8),
                Repeater::make('highlights')
                    ->label('Punti forti')
                    ->helperText('Ne vengono mostrati idealmente 4.')
                    ->simple(TextInput::make('value')->validationAttribute('voce')->required()->maxLength(255))
                    ->addActionLabel('Aggiungi punto forte')
                    ->defaultItems(0),
                Repeater::make('itinerary')
                    ->label('Programma')
                    ->schema([
                        TextInput::make('day')
                            ->label('Giorno')
                            ->placeholder('Es. Giorno 1, Giorni 2–3')
                            ->required(),
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required(),
                        Textarea::make('description')
                            ->label('Descrizione')
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
                            ->simple(TextInput::make('value')->validationAttribute('voce')->required()->maxLength(255))
                            ->addActionLabel('Aggiungi voce')
                            ->defaultItems(0),
                        Repeater::make('excluded')
                            ->label('La quota non comprende')
                            ->simple(TextInput::make('value')->validationAttribute('voce')->required()->maxLength(255))
                            ->addActionLabel('Aggiungi voce')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    private static function photosTab(): Tab
    {
        return Tab::make('Foto')
            ->icon('heroicon-o-photo')
            ->schema([
                FileUpload::make('cover_image')
                    ->label('Immagine principale (card e pagina)')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('packages'),
                FileUpload::make('gallery')
                    ->label('Galleria')
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
        return Tab::make('Periodi e prezzi')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Repeater::make('periods')
                    ->label('Listino per periodi')
                    ->helperText(fn (Get $get) => self::type($get) === PackageType::Stay
                        ? 'Soggiorni: prezzi A PERSONA A NOTTE. Ogni notte del soggiorno deve essere coperta da un periodo.'
                        : 'Prezzi a persona (escursioni) o a servizio (transfer) per la data del servizio.')
                    ->relationship()
                    ->reorderable(false)
                    ->collapsible()
                    ->cloneable()
                    ->addActionLabel('Aggiungi periodo')
                    ->itemLabel(fn (array $state) => trim(($state['name'] ?? 'Periodo').' '.self::periodRange($state)))
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome periodo')
                            ->placeholder('Es. Alta stagione'),
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
                        PriceFields::money('adult_price', 'Quota base', required: true)
                            ->label(fn (Get $get) => match (true) {
                                self::isPerService($get, '../../') => 'Prezzo del servizio (per tutto il gruppo)',
                                self::type($get, '../../') === PackageType::Stay => 'Adulto in doppia, a notte',
                                default => 'Adulto',
                            }),
                        PriceFields::money('extra_bed_adult_price', '3°/4° letto adulto, a notte')
                            ->helperText('Se vuoto si applica la quota base.')
                            ->visible(fn (Get $get) => self::type($get, '../../') === PackageType::Stay),
                        PriceFields::money('child_price', 'Bambino')
                            ->label(fn (Get $get) => self::type($get, '../../') === PackageType::Stay ? 'Bambino, a notte' : 'Bambino')
                            ->helperText('Se vuoto si applica la quota base.')
                            ->visible(fn (Get $get) => ! self::isPerService($get, '../../')),
                        PriceFields::money('infant_price', 'Infant')
                            ->label(fn (Get $get) => self::type($get, '../../') === PackageType::Stay ? 'Infant, a notte' : 'Infant')
                            ->helperText("Se vuoto l'infant è gratuito.")
                            ->visible(fn (Get $get) => ! self::isPerService($get, '../../')),
                        PriceFields::money('single_supplement', 'Supplemento singola, a notte')
                            ->visible(fn (Get $get) => self::type($get, '../../') === PackageType::Stay),
                    ]),
            ]);
    }

    private static function feesTab(): Tab
    {
        return Tab::make('Quote accessorie')
            ->icon('heroicon-o-receipt-percent')
            ->schema([
                Section::make('Trasferimenti')
                    ->description('Nel "Pacchetto completo" i trasferimenti sono sempre inclusi.')
                    ->visible(fn (Get $get) => self::type($get) === PackageType::Stay && self::offer($get) !== PackageOffer::FullPackage)
                    ->schema([
                        Toggle::make('transfer_included')
                            ->label('Trasferimenti inclusi nella quota')
                            ->default(true)
                            ->live(),
                        PriceFields::money('transfer_price', 'Prezzo trasferimenti per persona', required: true)
                            ->visible(fn (Get $get) => ! $get('transfer_included')),
                    ]),
                Section::make("Quota d'iscrizione")
                    ->description('Per ogni passeggero pagante (infant esclusi) dei soggiorni.')
                    ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
                    ->schema([
                        PriceFields::money('registration_fee', "Quota d'iscrizione per persona", required: true)->default(0),
                    ]),
                Section::make('Note')
                    ->description("Fasce d'età, assicurazioni, blocca prezzo, fee agenzie e acconto sono globali: si impostano in Impostazioni e Assicurazioni.")
                    ->schema([]),
            ]);
    }

    private static function conditionsTab(): Tab
    {
        return Tab::make('Condizioni')
            ->icon('heroicon-o-scale')
            ->schema([
                Textarea::make('cancellation_policy')
                    ->label('Condizioni di cancellazione')
                    ->rows(6),
                Textarea::make('notes')
                    ->label('Note')
                    ->rows(3),
            ]);
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
     * @param  array<string, mixed>  $state
     */
    private static function periodRange(array $state): string
    {
        if (blank($state['start_date'] ?? null) || blank($state['end_date'] ?? null)) {
            return '';
        }

        return '('.date('d/m/Y', strtotime($state['start_date'])).' - '.date('d/m/Y', strtotime($state['end_date'])).')';
    }
}
