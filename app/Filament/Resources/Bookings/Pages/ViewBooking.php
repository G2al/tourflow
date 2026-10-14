<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return "Prenotazione {$this->getRecord()->code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('Scrivi su WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (Booking $record) => 'https://wa.me/'.preg_replace('/\D+/', '', $record->contact_phone), shouldOpenInNewTab: true),
            $this->statusAction('confirm', 'Conferma', BookingStatus::Confirmed, 'success', 'heroicon-o-check-circle'),
            $this->statusAction('cancel', 'Annulla', BookingStatus::Cancelled, 'danger', 'heroicon-o-x-circle'),
            EditAction::make(),
        ];
    }

    private function statusAction(string $name, string $label, BookingStatus $status, string $color, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading("{$label} prenotazione")
            ->visible(fn (Booking $record) => $record->status !== $status)
            ->action(function (Booking $record) use ($status) {
                $record->update(['status' => $status]);

                Notification::make()
                    ->title("Prenotazione {$status->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
