<?php

namespace App\Filament\Resources\Excursions\Pages;

use App\Filament\Resources\Excursions\ExcursionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExcursion extends EditRecord
{
    protected static string $resource = ExcursionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
