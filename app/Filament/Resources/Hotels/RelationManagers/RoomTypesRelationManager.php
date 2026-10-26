<?php

namespace App\Filament\Resources\Hotels\RelationManagers;

use App\Filament\Support\HelpAction;
use App\Filament\Support\PriceFields;
use App\Models\RoomType;
use App\Services\Inventory;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * Tipologie di camera dell'hotel con supplemento e allotment (camere
 * acquistate per ogni notte di un periodo).
 */
class RoomTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomTypes';

    protected static ?string $title = 'Camere e disponibilità';

    protected static ?string $modelLabel = 'tipologia di camera';

    protected static ?string $pluralModelLabel = 'tipologie di camera';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Tipologia')
                    ->placeholder('Es. Deluxe vista mare')
                    ->required()
                    ->maxLength(255),
                PriceFields::money('supplement', 'Supplemento a persona a notte')
                    ->helperText('0 per la camera base. Infant esclusi.')
                    ->default(0)
                    ->required(),
                Select::make('max_occupancy')
                    ->label('Occupazione massima')
                    ->options([1 => '1 persona', 2 => '2 persone', 3 => '3 persone', 4 => '4 persone'])
                    ->default(4)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Ordinamento')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Attiva')
                    ->default(true),
                Repeater::make('allotments')
                    ->label('Allotment (camere acquistate)')
                    ->helperText('Numero di camere disponibili OGNI NOTTE del periodo. Periodi sovrapposti si sommano.')
                    ->relationship()
                    ->reorderable(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->addActionLabel('Aggiungi periodo')
                    ->defaultItems(0)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Dal')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Al (ultima notte)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('start_date')
                            ->required(),
                        TextInput::make('rooms')
                            ->label('Camere')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('notes')
                            ->label('Note')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('allotments'))
            ->columns([
                TextColumn::make('name')
                    ->label('Tipologia')
                    ->description(fn (RoomType $record) => $record->description),
                TextColumn::make('supplement')
                    ->label('Supplemento')
                    ->money('EUR')
                    ->description('a persona a notte'),
                TextColumn::make('max_occupancy')
                    ->label('Max persone'),
                TextColumn::make('allotment_today')
                    ->label('Acquistate stanotte')
                    ->state(fn (RoomType $record) => $record->allotmentOn(today())),
                TextColumn::make('available_today')
                    ->label('Libere stanotte')
                    ->state(fn (RoomType $record) => app(Inventory::class)->roomsAvailable($record, today(), today()->addDay()))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                ToggleColumn::make('is_active')
                    ->label('Attiva'),
            ])
            ->headerActions([
                HelpAction::make('rooms', 'Camere e disponibilità'),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
