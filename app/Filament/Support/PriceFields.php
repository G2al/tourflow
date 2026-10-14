<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;

class PriceFields
{
    /**
     * Coppia di campi "{prefix}_gross" (prezzo al pubblico) e "{prefix}_net"
     * (prezzo agenzia), mostrati affiancati.
     */
    public static function pair(string $prefix, string $label, bool $required = false, ?string $helperText = null, string $grossSuffix = '_gross', string $netSuffix = '_net', ?float $default = null): Fieldset
    {
        return Fieldset::make($label)
            ->columns(2)
            ->schema([
                self::money($prefix.$grossSuffix, 'Lordo (pubblico)', $required)->default($default)->helperText($helperText),
                self::money($prefix.$netSuffix, 'Netto (agenzia)', $required)
                    ->default($default)
                    ->lte($prefix.$grossSuffix)
                    ->validationMessages(['lte' => 'Il netto non può superare il lordo.']),
            ]);
    }

    public static function money(string $name, string $label, bool $required = false): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->prefix('€')
            ->required($required);
    }
}
