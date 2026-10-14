<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome e cognome (operatore)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(30),
                        Select::make('role')
                            ->label('Ruolo')
                            ->options(UserRole::class)
                            ->default(UserRole::Agency)
                            ->required()
                            ->live(),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Lascia vuoto per non modificarla.' : null),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->helperText('Un utente disattivato non può accedere.')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Dati agenzia')
                    ->relationship('agency')
                    ->visible(fn (Get $get) => self::isAgency($get('role')))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Ragione sociale')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('Partita IVA')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        TextInput::make('tax_code')
                            ->label('Codice fiscale')
                            ->maxLength(20),
                        TextInput::make('license_number')
                            ->label('Licenza / autorizzazione')
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Indirizzo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Città')
                            ->required(),
                        TextInput::make('zip_code')
                            ->label('CAP')
                            ->required()
                            ->maxLength(10),
                        TextInput::make('province')
                            ->label('Provincia')
                            ->maxLength(5),
                        TextInput::make('country')
                            ->label('Nazione (ISO)')
                            ->default('IT')
                            ->length(2),
                        TextInput::make('phone')
                            ->label('Telefono agenzia')
                            ->tel(),
                        TextInput::make('email')
                            ->label('Email agenzia')
                            ->email(),
                        TextInput::make('pec')
                            ->label('PEC')
                            ->email(),
                        TextInput::make('sdi_code')
                            ->label('Codice SDI')
                            ->maxLength(10),
                        TextInput::make('contact_person')
                            ->label('Referente')
                            ->maxLength(255),
                    ]),
            ]);
    }

    private static function isAgency(mixed $role): bool
    {
        return ($role instanceof UserRole ? $role : UserRole::tryFrom((string) $role)) === UserRole::Agency;
    }
}
