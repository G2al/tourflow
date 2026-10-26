<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

class PriceFields
{
    /**
     * Campo importo in euro (prezzo al pubblico).
     */
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
