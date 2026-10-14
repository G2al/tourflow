<?php

namespace App\Filament\Resources\Insurances;

use App\Filament\Resources\Insurances\Pages\CreateInsurance;
use App\Filament\Resources\Insurances\Pages\EditInsurance;
use App\Filament\Resources\Insurances\Pages\ListInsurances;
use App\Filament\Resources\Insurances\Schemas\InsuranceForm;
use App\Filament\Resources\Insurances\Tables\InsurancesTable;
use App\Models\Insurance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InsuranceResource extends Resource
{
    protected static ?string $model = Insurance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogo';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'assicurazione';

    protected static ?string $pluralModelLabel = 'assicurazioni';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InsuranceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InsurancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInsurances::route('/'),
            'create' => CreateInsurance::route('/create'),
            'edit' => EditInsurance::route('/{record}/edit'),
        ];
    }
}
