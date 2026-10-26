<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return "Pratica {$this->getRecord()->code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (Booking $record) => 'https://wa.me/'.preg_replace('/\D+/', '', $record->contact_phone), shouldOpenInNewTab: true),
            ActionGroup::make([
                Action::make('quote_pdf')
                    ->label('Preventivo PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Booking $record) => route('admin.bookings.document', [$record, 'quote']), shouldOpenInNewTab: true),
                Action::make('proforma_pdf')
                    ->label('Proforma PDF')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Booking $record) => $record->confirmed_at !== null)
                    ->url(fn (Booking $record) => route('admin.bookings.document', [$record, 'proforma']), shouldOpenInNewTab: true),
            ])->label('Documenti')->icon('heroicon-o-document')->button()->color('gray'),
            $this->statusAction('deposit', 'Acconto ricevuto', BookingStatus::DepositReceived, 'primary', 'heroicon-o-banknotes',
                [BookingStatus::Confirmed, BookingStatus::ProformaSent, BookingStatus::ProformaViewed]),
            $this->statusAction('paid', 'Saldata', BookingStatus::Paid, 'success', 'heroicon-o-check-badge',
                [BookingStatus::Confirmed, BookingStatus::ProformaSent, BookingStatus::ProformaViewed, BookingStatus::DepositReceived]),
            $this->statusAction('cancel', 'Annulla', BookingStatus::Cancelled, 'danger', 'heroicon-o-x-circle',
                array_filter(BookingStatus::cases(), fn (BookingStatus $status) => ! $status->isFinal())),
            EditAction::make(),
        ];
    }

    /**
     * @param  array<int, BookingStatus>  $from  stati da cui l'azione è disponibile
     */
    private function statusAction(string $name, string $label, BookingStatus $status, string $color, string $icon, array $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading("{$label}: pratica")
            ->modalDescription($status === BookingStatus::Cancelled ? 'La disponibilità di camere e voli tornerà libera.' : null)
            ->visible(fn (Booking $record) => in_array($record->status, $from, true))
            ->action(function (Booking $record) use ($status) {
                $record->update([
                    'status' => $status,
                    ...($status === BookingStatus::Cancelled ? ['cancelled_at' => now()] : []),
                ]);

                Notification::make()
                    ->title("Pratica: {$status->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
