<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'agencies' => Tab::make('Agenzie')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Agency)),
            'admins' => Tab::make('Amministratori')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Admin)),
            'all' => Tab::make('Tutti'),
        ];
    }
}
