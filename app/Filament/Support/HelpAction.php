<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/**
 * Pulsante "Come funziona?" che apre un breve tutorial con esempio
 * (vista in resources/views/filament/help).
 */
class HelpAction
{
    public static function make(string $topic, string $title): Action
    {
        return Action::make("help_{$topic}")
            ->label('Come funziona?')
            ->icon('heroicon-o-question-mark-circle')
            ->color('info')
            ->modalHeading($title)
            ->modalIcon('heroicon-o-academic-cap')
            ->modalContent(view("filament.help.{$topic}"))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Ho capito');
    }
}
