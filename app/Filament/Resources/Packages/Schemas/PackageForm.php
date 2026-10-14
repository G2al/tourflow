<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Enums\BoardType;
use App\Enums\PackageType;
use App\Enums\PriceUnit;
use App\Filament\Support\PriceFields;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Select::make('type')
                    ->label('Tipo')
                    ->options(PackageType::class)
                    ->default(PackageType::Stay)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $type = $state instanceof PackageType ? $state : PackageType::tryFrom((string) $state);
                        $set('price_unit', $type?->defaultPriceUnit());

                        if ($type !== PackageType::Stay) {
                            $set('nights', 0);
                            $set('hotel_id', null);
                        }
                    }),
                Select::make('price_unit')
                    ->label('Prezzo')
                    ->options(PriceUnit::class)
                    ->default(PriceUnit::PerPerson)
                    ->required()
                    ->live()
                    ->helperText('"A servizio": un prezzo unico per tutto il gruppo (es. transfer privato).'),
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
                    ->label('LocalitÃ ')
                    ->placeholder('Es. Hurghada Â· Mar Rosso')
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
                    ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
                    ->disabled(fn (Get $get) => blank($get('destination_id')))
                    ->helperText('Facoltativo. Prima seleziona la destinazione.'),
                TextInput::make('accommodation')
                    ->label('Sistemazione / servizio')
                    ->placeholder('Es. Resort 5 stelle, Auto privata con autista')
                    ->maxLength(255),
                TextInput::make('nights')
                    ->label('Notti')
                    ->numeric()
                    ->minValue(0)
                    ->default(7)
                    ->required()
                    ->helperText('0 per escursioni e transfer in giornata. I giorni sono calcolati: notti + 1.'),
                TextInput::make('board')
                    ->label('Trattamento / formula')
                    ->datalist(BoardType::suggestions())
                    ->placeholder('Es. All Inclusive, Pranzo incluso')
                    ->maxLength(255),
                TextInput::make('badge')
                    ->label('Badge sulla foto')
                    ->placeholder('Es. PiÃ¹ richiesto, Premium')
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
                Section::make('ValiditÃ ')
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
                            ->placeholder('Es. Giorno 1, Giorni 2â€“3')
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
                    ->itemLabel(fn (array $state) => trim(($state['day'] ?? '').' Â· '.($state['title'] ?? ''), ' Â·'))
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
                    ->helperText('Il prezzo "a partire da" mostrato nel catalogo Ã¨ il piÃ¹ basso tra i periodi futuri.')
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
                        PriceFields::pair('adult_price', 'Quota base', required: true)
                            ->label(fn (Get $get) => self::isPerService($get, '../../')
                                ? 'Prezzo del servizio (per tutto il gruppo)'
                                : 'Quota base adulto (in doppia)')
                            ->columnSpanFull(),
                        Grid::make(1)
                            ->visible(fn (Get $get) => ! self::isPerService($get, '../../'))
                            ->columnSpanFull()
                            ->schema([
                                PriceFields::pair('extra_bed_adult_price', '3Â°/4Â° letto adulto', helperText: 'Se vuoto si applica la quota base.'),
                                PriceFields::pair('child_price', 'Bambino', helperText: 'Se vuoto si applica la quota base.'),
                                PriceFields::pair('infant_price', 'Infant', helperText: "Se vuoto l'infant Ã¨ gratuito."),
                                PriceFields::pair('single', 'Supplemento singola', grossSuffix: '_supplement_gross', netSuffix: '_supplement_net'),
                            ]),
                    ]),
            ]);
    }

    private static function feesTab(): Tab
    {
        return Tab::make('Quote ed extra')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Section::make('Trasferimenti')
                    ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
                    ->schema([
                        Toggle::make('transfer_included')
                            ->label('Trasferimenti inclusi nella quota')
                            ->default(true)
                            ->live(),
                        PriceFields::pair('transfer_price', 'Prezzo trasferimenti per persona', required: true)
                            ->visible(fn (Get $get) => ! $get('transfer_included')),
                    ]),
                Section::make("Quota d'iscrizione")
                    ->description('Per ogni partecipante pagante (infant esclusi). Non si applica ai prodotti a servizio.')
                    ->schema([
                        PriceFields::pair('registration_fee', "Quota d'iscrizione", required: true, default: 0),
                    ]),
                Section::make("Fasce d'etÃ ")
                    ->description('EtÃ  compiuta alla data di partenza.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('infant_max_age')
                            ->label('Infant fino a (anni)')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required(),
                        TextInput::make('child_max_age')
                            ->label('Bambino fino a (anni)')
                            ->numeric()
                            ->gt('infant_max_age')
                            ->default(11)
                            ->required(),
                    ]),
                Section::make('Extra proposti in fase di prenotazione')
                    ->schema([
                        CheckboxList::make('excursions')
                            ->label('Escursioni facoltative')
                            ->helperText('Escursioni extra della destinazione selezionata, aggiungibili al soggiorno.')
                            ->relationship(
                                'excursions',
                                'name',
                                fn (Builder $query, Get $get) => $query->where('destination_id', $get('destination_id')),
                            )
                            ->visible(fn (Get $get) => self::type($get) === PackageType::Stay)
                            ->bulkToggleable()
                            ->columns(2),
                        CheckboxList::make('insurances')
                            ->label('Assicurazioni proposte')
                            ->relationship('insurances', 'name')
                            ->bulkToggleable()
                            ->columns(2),
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
                    ->rows(6),
                Textarea::make('notes')
                    ->label('Note')
                    ->rows(3),
            ]);
    }

    private static function type(Get $get, string $path = ''): ?PackageType
    {
        $state = $get($path.'type');

        return $state instanceof PackageType ? $state : PackageType::tryFrom((string) $state);
    }

    private static function isPerService(Get $get, string $path = ''): bool
    {
        $state = $get($path.'price_unit');

        return ($state instanceof PriceUnit ? $state : PriceUnit::tryFrom((string) $state)) === PriceUnit::PerService;
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
