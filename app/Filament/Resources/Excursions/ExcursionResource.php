<?php

namespace App\Filament\Resources\Excursions;

use App\Filament\Resources\Excursions\Pages\CreateExcursion;
use App\Filament\Resources\Excursions\Pages\EditExcursion;
use App\Filament\Resources\Excursions\Pages\ListExcursions;
use App\Filament\Resources\Excursions\Schemas\ExcursionForm;
use App\Filament\Resources\Excursions\Tables\ExcursionsTable;
use App\Models\Excursion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExcursionResource extends Resource
{
    protected static ?string $model = Excursion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogo';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'escursione';

    protected static ?string $pluralModelLabel = 'escursioni';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ExcursionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExcursionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExcursions::route('/'),
            'create' => CreateExcursion::route('/create'),
            'edit' => EditExcursion::route('/{record}/edit'),
        ];
    }
}
