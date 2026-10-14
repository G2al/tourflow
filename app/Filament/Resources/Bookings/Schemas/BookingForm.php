<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Dall'admin si modificano solo stato, note e contatti: prezzi e
 * partecipanti arrivano dalla richiesta del frontend.
 */
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stato')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->label('Stato')
                            ->options(BookingStatus::class)
                            ->required(),
                        Textarea::make('admin_notes')
                            ->label('Note interne')
                            ->helperText('Visibili solo agli amministratori.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Referente')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('contact_first_name')
                            ->label('Nome')
                            ->required(),
                        TextInput::make('contact_last_name')
                            ->label('Cognome')
                            ->required(),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->required(),
                        TextInput::make('contact_phone')
                            ->label('Telefono')
                            ->tel()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Note del cliente')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
